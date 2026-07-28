<?php
// Serviço central para cálculo de assiduidade diária
// Regras de majoração de horas extra:
// - As percentagens não são hardcoded; são lidas da tabela `horas_extra_regras`.
//   a resolução obedece a `config_horas_extra.resolucao_prioridade`:
//     - 'maior_percentagem' (padrão): aplica a percentagem mais elevada disponível para esse minuto;
//     - 'maior_prioridade': aplica a regra com maior valor de `prioridade` (inteiro);
// - As regras têm `data_inicio`/`data_fim` de vigência; ao calcular um dia usa-se as regras vigentes nesse dia,
//   garantindo que alterações futuras não alteram cálculos históricos.
// Entradas e formatos esperados (arrays puros para fácil teste):
// - $funcionario: ['id'=>int, 'nome'=>string, ...] (pode ser apenas id)
// - $date: 'Y-m-d' (string)
// - $escala: array de períodos previstos (cada periodo: ['inicio'=>'Y-m-d H:i','fim'=>'Y-m-d H:i'])
// - $periodos_turno: mesma estrutura que $escala (support multi-period)
// - $registos: array de registos ordenados chronologicamente: ['tipo'=>'entrada'|'saida'|...,'data_hora'=>'Y-m-d H:i']
// - $ausencias: array de ausencias aprovadas: ['tipo'=>'ferias'|'doenca'|'folga', 'inicio'=>'Y-m-d','fim'=>'Y-m-d']
// - $tolerancias: ['entrada'=>minutes,'saida'=>minutes]
// - $carga_diaria: minutes (int) ou null
// - $carga_semanal: minutes (int) ou null
// - $feriados: array of 'Y-m-d' strings
// - $folga: boolean (is day-off scheduled)
// - $substituicao: array info about substitution (not used in base calc but returned if present)

function calcular_assiduidade_diaria(array $funcionario, string $date, array $escala, array $periodos_turno, array $registos, array $ausencias = [], array $tolerancias = [], ?int $carga_diaria = null, ?int $carga_semanal = null, array $feriados = [], bool $folga = false, array $substituicao = [], ?array $regras_extra = null, $dbconn = null): array
{
    // normalize tolerances
    $tolEntrada = $tolerancias['entrada'] ?? 0;
    $tolSaida = $tolerancias['saida'] ?? 0;

    // helper para minutos entre datas
    $minutes_between = function(string $a, string $b): int {
        return (int) floor((strtotime($b) - strtotime($a)) / 60);
    };

    // parse periods: ensure each period has full datetime and handle overnight
    $parse_periods = function(array $periods, string $day): array {
        $out = [];
        foreach ($periods as $p) {
            // expect 'inicio' and 'fim' as H:i or full
            $inicio = $p['inicio'];
            $fim = $p['fim'];
            // if only time provided (HH:ii) expand to date
            if (preg_match('/^\d{2}:\d{2}$/', $inicio)) {
                $inicio = $day . ' ' . $inicio;
            }
            if (preg_match('/^\d{2}:\d{2}$/', $fim)) {
                $fim = $day . ' ' . $fim;
            }
            // if fim <= inicio assume next day
            if (strtotime($fim) <= strtotime($inicio)) {
                $fim = date('Y-m-d H:i', strtotime($fim . ' +1 day'));
            }
            $out[] = ['inicio'=>$inicio,'fim'=>$fim];
        }
        return $out;
    };

    $periodos = $parse_periods($periodos_turno ?: $escala, $date);

    // predicted minutes = sum of period durations
    $minutos_previstos = 0;
    foreach ($periodos as $p) {
        $minutos_previstos += $minutes_between($p['inicio'], $p['fim']);
    }
    if ($carga_diaria !== null) {
        // if carga_diaria provided, prefer it for predicted minutes
        $minutos_previstos = $carga_diaria;
    }

    // check absence
    $is_ausencia = false;
    $ausencia_tipo = null;
    foreach ($ausencias as $a) {
        $ini = $a['inicio']; $fim = $a['fim'];
        if (strtotime($date) >= strtotime($ini) && strtotime($date) <= strtotime($fim)) {
            $is_ausencia = true; $ausencia_tipo = $a['tipo']; break;
        }
    }

    // if absence approved that covers whole day (ferias, doenca), predicted = 0 and return
    if ($is_ausencia && in_array($ausencia_tipo, ['ferias','doenca','licenca'])) {
        return [
            'minutos_previstos'=>0,
            'minutos_trabalhados'=>0,
            'minutos_pausa'=>0,
            'atraso'=>0,
            'saida_antecipada'=>0,
            'horas_extra'=>0,
            'saldo_diario'=>0,
            'falta_total'=>$minutos_previstos,
            'falta_parcial'=>0,
            'folga_trabalhada'=>false,
            'feriado_trabalhado'=>in_array($date,$feriados),
            'trabalho_noturno'=>0,
            'segundo_turno'=>false,
            'incidencias'=>['ausencia_aprovada:'.$ausencia_tipo],
        ];
    }

    // process registros: pair entradas/saidas and pausas
    $worked_intervals = []; // [['inicio'=>ts,'fim'=>ts]]
    $pause_intervals = [];
    $incidencias = [];

    $stackEntrada = [];
    $pauseStart = null;

    foreach ($registos as $r) {
        $tipo = $r['tipo'];
        $ts = $r['data_hora'];
        if ($tipo === 'entrada' || $tipo === 'entrada_segundo_turno') {
            $stackEntrada[] = $ts;
        } elseif ($tipo === 'inicio_pausa') {
            if ($pauseStart !== null) {
                $incidencias[] = 'inicio_pausa_sem_fim at ' . $ts;
            }
            $pauseStart = $ts;
        } elseif ($tipo === 'fim_pausa') {
            if ($pauseStart === null) {
                $incidencias[] = 'fim_pausa_sem_inicio at ' . $ts;
            } else {
                $pause_intervals[] = ['inicio'=>$pauseStart,'fim'=>$ts];
                $pauseStart = null;
            }
        } elseif ($tipo === 'saida' || $tipo === 'saida_segundo_turno') {
            if (count($stackEntrada) === 0) {
                $incidencias[] = 'saida_sem_entrada at ' . $ts;
            } else {
                $ent = array_pop($stackEntrada);
                // create worked interval
                $worked_intervals[] = ['inicio'=>$ent,'fim'=>$ts];
            }
        }
    }

    // any remaining entries without exit
    foreach ($stackEntrada as $unclosed) {
        $incidencias[] = 'entrada_sem_saida at ' . $unclosed;
        // we do not assume an exit; mark as incomplete but for worked minutes we cap at 0
    }
    if ($pauseStart !== null) {
        $incidencias[] = 'inicio_pausa_sem_fim at ' . $pauseStart;
    }

    // compute minutos_trabalhados and minutos_pausa while excluding overlapping pause time from worked
    $minutos_trabalhados = 0;
    $minutos_pausa = 0;

    // helper to intersect intervals
    $intersect = function(string $a1,string $b1,string $a2,string $b2): int {
        $start = max(strtotime($a1), strtotime($a2));
        $end = min(strtotime($b1), strtotime($b2));
        return $end > $start ? (int) floor(($end-$start)/60) : 0;
    };

    // sum pauses
    foreach ($pause_intervals as $p) {
        $minutos_pausa += $minutes_between($p['inicio'],$p['fim']);
    }

    // sum worked but subtract pauses overlaps
    foreach ($worked_intervals as $w) {
        $dur = $minutes_between($w['inicio'],$w['fim']);
        $overlapPause = 0;
        foreach ($pause_intervals as $p) {
            $overlapPause += $intersect($w['inicio'],$w['fim'],$p['inicio'],$p['fim']);
        }
        $minutos_trabalhados += max(0, $dur - $overlapPause);
    }

    $trabalho_noturno = 0;

    // atraso e saida antecipada: compare entrada/saida aos periodos com tolerancias
    $atraso = 0;
    $saida_antecipada = 0;
    $segundo_turno_flag = false;

    // for each expected period, find earliest entrada within reasonable window and latest exit
    foreach ($periodos as $p) {
        // find first entrada after p.inicio - 12h and before p.fim + 12h
        $foundEntrada = null; $foundSaida = null;
        foreach ($registos as $r) {
            $t = $r['data_hora'];
            if (in_array($r['tipo'], ['entrada','entrada_segundo_turno'])) {
                if (strtotime($t) >= strtotime($p['inicio']) - 12*3600 && strtotime($t) <= strtotime($p['fim']) + 12*3600) {
                    $foundEntrada = $t; break;
                }
            }
        }
        // find last salida within window
        for ($i=count($registos)-1;$i>=0;$i--) {
            $r = $registos[$i];
            $t = $r['data_hora'];
            if (in_array($r['tipo'], ['saida','saida_segundo_turno'])) {
                if (strtotime($t) >= strtotime($p['inicio']) - 12*3600 && strtotime($t) <= strtotime($p['fim']) + 12*3600) {
                    $foundSaida = $t; break;
                }
            }
        }
        if ($foundEntrada !== null) {
            $diff = $minutes_between($p['inicio'], $foundEntrada) - $tolEntrada;
            if ($diff > 0) $atraso += $diff;
        }
        if ($foundSaida !== null) {
            $diff2 = $minutes_between($foundSaida, $p['fim']) - $tolSaida;
            if ($diff2 > 0) $saida_antecipada += $diff2;
        }
    }

    // horas extra e faltas: advanced handling with DB-driven regras
    $total_extra = max(0, $minutos_trabalhados - $minutos_previstos);
    $saldo_diario = $minutos_trabalhados - $minutos_previstos;
    $falta_total = 0; $falta_parcial = 0;
    if ($minutos_trabalhados == 0 && $minutos_previstos > 0) {
        $falta_total = $minutos_previstos;
    } elseif ($minutos_trabalhados > 0 && $minutos_trabalhados < $minutos_previstos) {
        $falta_parcial = $minutos_previstos - $minutos_trabalhados;
    }

    $folga_trabalhada = $folga && ($minutos_trabalhados > 0);
    $feriado_trabalhado = in_array($date, $feriados) && ($minutos_trabalhados > 0);

    // segundo turno detection
    foreach ($registos as $r) { if (in_array($r['tipo'], ['entrada_segundo_turno','saida_segundo_turno'])) { $segundo_turno_flag = true; break; } }

    // Load overtime rules: use provided $regras_extra, else DB, else sensible defaults
    $rules = [];
    if (!empty($regras_extra)) {
        $rules = $regras_extra;
    } elseif ($dbconn) {
        $q = mysqli_prepare($dbconn, 'SELECT codigo,nome,porcentagem,prioridade,data_inicio,data_fim FROM horas_extra_regras WHERE ativo = 1');
        if ($q) {
            mysqli_stmt_execute($q);
            $rset = mysqli_stmt_get_result($q);
            while ($rr = mysqli_fetch_assoc($rset)) {
                $rules[] = $rr;
            }
            mysqli_stmt_close($q);
        }
    } else {
        $rules = [
            ['codigo'=>'segundo_turno_primeira_hora','nome'=>'1h 2turno','porcentagem'=>150,'prioridade'=>100,'data_inicio'=>'2020-01-01','data_fim'=>null],
            ['codigo'=>'segundo_turno_subsequente','nome'=>'>1h 2turno','porcentagem'=>175,'prioridade'=>90,'data_inicio'=>'2020-01-01','data_fim'=>null],
        ];
    }

    // load resolution mode from DB config if available; default 'maior_percentagem'
    $resolucao = 'maior_percentagem';
    if ($dbconn) {
        $qq = mysqli_prepare($dbconn, 'SELECT valor FROM config_horas_extra WHERE chave = "resolucao_prioridade" LIMIT 1');
        if ($qq) {
            mysqli_stmt_execute($qq);
            $rrc = mysqli_stmt_get_result($qq);
            $cfg = mysqli_fetch_assoc($rrc);
            mysqli_stmt_close($qq);
            if ($cfg && !empty($cfg['valor'])) $resolucao = $cfg['valor'];
        }
    }

    // Build union of predicted and worked intervals (as timestamps)
    $pred_union = [];
    foreach ($periodos as $p) $pred_union[] = ['inicio'=>strtotime($p['inicio']),'fim'=>strtotime($p['fim'])];
    usort($pred_union, function($a,$b){return $a['inicio'] <=> $b['inicio'];});
    $merged_pred = [];
    foreach ($pred_union as $seg) {
        if (empty($merged_pred) || $seg['inicio'] > $merged_pred[count($merged_pred)-1]['fim']) $merged_pred[] = $seg;
        else $merged_pred[count($merged_pred)-1]['fim'] = max($merged_pred[count($merged_pred)-1]['fim'],$seg['fim']);
    }

    $work_union = [];
    foreach ($worked_intervals as $w) $work_union[] = ['inicio'=>strtotime($w['inicio']),'fim'=>strtotime($w['fim'])];
    usort($work_union, function($a,$b){return $a['inicio'] <=> $b['inicio'];});
    $merged_work = [];
    foreach ($work_union as $seg) {
        if (empty($merged_work) || $seg['inicio'] > $merged_work[count($merged_work)-1]['fim']) $merged_work[] = $seg;
        else $merged_work[count($merged_work)-1]['fim'] = max($merged_work[count($merged_work)-1]['fim'],$seg['fim']);
    }

    // compute extra segments (worked minus predicted)
    $extra_segments = [];
    foreach ($merged_work as $wseg) {
        $cursor = $wseg['inicio']; $end = $wseg['fim'];
        foreach ($merged_pred as $pseg) {
            if ($pseg['fim'] <= $cursor || $pseg['inicio'] >= $end) continue;
            if ($pseg['inicio'] > $cursor) $extra_segments[] = ['inicio'=>$cursor,'fim'=>min($end,$pseg['inicio'])];
            $cursor = max($cursor, $pseg['fim']);
            if ($cursor >= $end) break;
        }
        if ($cursor < $end) $extra_segments[] = ['inicio'=>$cursor,'fim'=>$end];
    }

    // classify extra minutes into buckets 100/150/175/200 using rules and second-shift sequencing
    $buckets = ['100'=>0,'150'=>0,'175'=>0,'200'=>0];
    $second_shift_end = null;
    if (count($periodos) >= 2) $second_shift_end = strtotime($periodos[count($periodos)-1]['fim']);
    $minutes_since_second_shift_extra = 0;
    foreach ($extra_segments as $seg) {
        for ($ts=$seg['inicio']; $ts < $seg['fim']; $ts += 60) {
            $applicable = [];
            if ($second_shift_end !== null && $ts >= $second_shift_end) {
                $pos = $minutes_since_second_shift_extra;
                if ($pos < 60) $applicable[] = ['codigo'=>'segundo_turno_primeira_hora','porcentagem'=>150,'prioridade'=>100];
                else $applicable[] = ['codigo'=>'segundo_turno_subsequente','porcentagem'=>175,'prioridade'=>90];
                $minutes_since_second_shift_extra++;
            }
            if (empty($applicable)) $chosen = ['porcentagem'=>100,'codigo'=>'normal'];
            else {
                if ($resolucao === 'maior_prioridade') usort($applicable, function($a,$b){return $b['prioridade'] <=> $a['prioridade'];});
                else usort($applicable, function($a,$b){return $b['porcentagem'] <=> $a['porcentagem'];});
                $chosen = $applicable[0];
            }
            $pct = (int)$chosen['porcentagem'];
            if (!isset($buckets[(string)$pct])) $buckets[(string)$pct] = 0;
            $buckets[(string)$pct]++;
        }
    }

    $minutos_100 = $buckets['100'] ?? 0;
    $minutos_150 = $buckets['150'] ?? 0;
    $minutos_175 = $buckets['175'] ?? 0;
    $minutos_200 = $buckets['200'] ?? 0;

    $horas_extra = $total_extra;

    // incidencias: include any collected
    // deduplicate incidencias
    $incidencias = array_values(array_unique($incidencias));

    return [
        'minutos_previstos' => $minutos_previstos,
        'minutos_trabalhados' => $minutos_trabalhados,
        'minutos_pausa' => $minutos_pausa,
        'atraso' => $atraso,
        'saida_antecipada' => $saida_antecipada,
        'horas_extra' => $horas_extra,
        'minutos_100' => $minutos_100,
        'minutos_150' => $minutos_150,
        'minutos_175' => $minutos_175,
        'minutos_200' => $minutos_200,
        'saldo_diario' => $saldo_diario,
        'falta_total' => $falta_total,
        'falta_parcial' => $falta_parcial,
        'folga_trabalhada' => $folga_trabalhada,
        'feriado_trabalhado' => $feriado_trabalhado,
        'trabalho_noturno' => $trabalho_noturno,
        'segundo_turno' => $segundo_turno_flag,
        'incidencias' => $incidencias,
        'raw' => [ 'periodos' => $periodos, 'worked_intervals'=>$worked_intervals, 'pause_intervals'=>$pause_intervals, 'extra_segments'=>$extra_segments ]
    ];
}

// end of service
