<?php

function rm_table_exists($conn, $table)
{
    if (function_exists('fe_table_exists')) {
        return fe_table_exists($conn, $table);
    }

    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    mysqli_stmt_bind_param($stmt, 's', $table);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function rm_column_exists($conn, $table, $column)
{
    if (function_exists('fe_column_exists')) {
        return fe_column_exists($conn, $table, $column);
    }

    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function rm_bind_params($stmt, $types, $params)
{
    if ($types === '') {
        return;
    }

    $refs = [$types];
    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function rm_periodo_mensal($ano, $mes)
{
    $inicio = sprintf('%04d-%02d-01', $ano, $mes);
    return [$inicio, date('Y-m-t', strtotime($inicio))];
}

function rm_datas_periodo($inicio, $fim)
{
    $datas = [];
    $dt = new DateTimeImmutable($inicio);
    $end = new DateTimeImmutable($fim);

    while ($dt <= $end) {
        $datas[] = $dt->format('Y-m-d');
        $dt = $dt->modify('+1 day');
    }

    return $datas;
}

function rm_formatar_minutos($minutos)
{
    $minutos = (int) $minutos;
    $sinal = $minutos < 0 ? '-' : '';
    $minutos = abs($minutos);
    return $sinal . sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
}

function rm_formatar_data($data)
{
    return $data ? date('d/m/Y', strtotime($data)) : '-';
}

function rm_formatar_hora($datetime)
{
    return $datetime ? date('H:i', strtotime($datetime)) : '-';
}

function rm_minutos_entre($inicio, $fim)
{
    if (!$inicio || !$fim) {
        return 0;
    }

    return max(0, (int) floor((strtotime($fim) - strtotime($inicio)) / 60));
}

function rm_calcular_pausas($movimentos)
{
    $total = 0;
    $inicio = null;

    foreach ($movimentos as $movimento) {
        if ($movimento['tipo'] === 'inicio_pausa') {
            $inicio = $movimento['data_hora'];
            continue;
        }

        if ($movimento['tipo'] === 'fim_pausa' && $inicio !== null) {
            $total += rm_minutos_entre($inicio, $movimento['data_hora']);
            $inicio = null;
        }
    }

    return $total;
}

function rm_entradas_saidas($movimentos)
{
    $partes = [];
    foreach ($movimentos as $movimento) {
        if (in_array($movimento['tipo'], ['entrada', 'entrada_segundo_turno', 'saida', 'saida_segundo_turno'], true)) {
            $label = strpos($movimento['tipo'], 'entrada') === 0 ? 'E' : 'S';
            $partes[] = $label . ' ' . rm_formatar_hora($movimento['data_hora']);
        }
    }

    return implode(' | ', $partes);
}

function rm_carregar_regras_extra($conn)
{
    if (!rm_table_exists($conn, 'horas_extra_regras')) {
        return [['codigo' => 'extra', 'nome' => 'Horas extra', 'porcentagem' => 0]];
    }

    $regras = [];
    $result = mysqli_query($conn, 'SELECT codigo, nome, porcentagem FROM horas_extra_regras WHERE ativo = 1 AND codigo <> "noturno" ORDER BY porcentagem ASC, prioridade DESC');
    while ($row = mysqli_fetch_assoc($result)) {
        $regras[] = $row;
    }

    return $regras ?: [['codigo' => 'extra', 'nome' => 'Horas extra', 'porcentagem' => 0]];
}

function rm_distribuir_extra($minutos, $regras)
{
    $minutos = max(0, (int) $minutos);
    $bucket = [];

    foreach ($regras as $regra) {
        $key = (string) (int) $regra['porcentagem'];
        $bucket[$key] = 0;
    }

    if ($minutos > 0) {
        $regra = end($regras);
        $key = (string) (int) $regra['porcentagem'];
        $bucket[$key] = $minutos;
    }

    return $bucket;
}

function rm_carregar_funcionarios_relatorio($conn, $funcionarioId, $equipaId)
{
    $selectEquipa = rm_table_exists($conn, 'equipas') && rm_column_exists($conn, 'funcionarios', 'equipa_id') ? 'e.nome AS equipa_nome' : 'NULL AS equipa_nome';
    $joinEquipa = rm_table_exists($conn, 'equipas') && rm_column_exists($conn, 'funcionarios', 'equipa_id') ? 'LEFT JOIN equipas e ON e.id = f.equipa_id' : '';
    $where = ["f.estado <> 'inativo'"];
    $params = [];
    $types = '';

    if ($funcionarioId > 0) {
        $where[] = 'f.id = ?';
        $params[] = $funcionarioId;
        $types .= 'i';
    }

    if ($equipaId > 0) {
        $where[] = 'f.equipa_id = ?';
        $params[] = $equipaId;
        $types .= 'i';
    }

    $sql = "SELECT f.id, f.utilizador_id, f.nome, f.numero_mecanografico, f.funcao, f.equipa_id,
                   f.carga_horaria_semanal, $selectEquipa
            FROM funcionarios f
            $joinEquipa
            WHERE " . implode(' AND ', $where) . "
            ORDER BY f.nome ASC";
    $stmt = mysqli_prepare($conn, $sql);
    rm_bind_params($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $funcionarios = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $funcionarios[(int) $row['id']] = $row;
    }
    mysqli_stmt_close($stmt);

    return $funcionarios;
}

function rm_carregar_por_funcionario_data($conn, $table, $dataColumn, $inicio, $fim, $funcionarioIds, $extraWhere = '')
{
    if (empty($funcionarioIds) || !rm_table_exists($conn, $table) || !rm_column_exists($conn, $table, 'funcionario_id')) {
        return [];
    }

    $ids = implode(',', array_map('intval', $funcionarioIds));
    $sql = "SELECT * FROM $table WHERE funcionario_id IN ($ids) AND $dataColumn BETWEEN ? AND ? $extraWhere";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $inicio, $fim);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[(int) $row['funcionario_id']][$row[$dataColumn]][] = $row;
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

function rm_carregar_relatorio_mensal($conn, $ano, $mes, $funcionarioId, $equipaId, $geradoPor)
{
    [$inicio, $fim] = rm_periodo_mensal($ano, $mes);
    $datas = rm_datas_periodo($inicio, $fim);
    $funcionariosBase = rm_carregar_funcionarios_relatorio($conn, $funcionarioId, $equipaId);
    $funcionarioIds = array_keys($funcionariosBase);
    $regrasExtra = rm_carregar_regras_extra($conn);

    $resumos = rm_carregar_por_funcionario_data($conn, 'resumo_diario_assiduidade', 'data', $inicio, $fim, $funcionarioIds);
    $escalas = rm_carregar_por_funcionario_data($conn, 'escala_funcionarios', 'data_escala', $inicio, $fim, $funcionarioIds);
    $registos = rm_carregar_registos_ponto($conn, $inicio, $fim, $funcionarioIds);
    $banco = rm_carregar_banco_horas_resumo($conn, $inicio, $fim, $funcionarioIds);
    $correcoes = rm_carregar_correcoes($conn, $inicio, $fim, $funcionarioIds);
    $pendentes = rm_carregar_incidencias_pendentes($conn, $inicio, $fim, $funcionarioIds);

    $funcionarios = [];
    $totaisEquipa = rm_totais_vazios($regrasExtra);

    foreach ($funcionariosBase as $fid => $funcionario) {
        $cargaSemanalMin = (int) round(((float) ($funcionario['carga_horaria_semanal'] ?? 0)) * 60);
        $funcionarioRel = [
            'id' => $fid,
            'numero_mecanografico' => $funcionario['numero_mecanografico'],
            'nome' => $funcionario['nome'],
            'equipa' => $funcionario['equipa_nome'] ?: '-',
            'funcao' => $funcionario['funcao'] ?: '-',
            'carga_diaria_minutos' => $cargaSemanalMin > 0 ? (int) round($cargaSemanalMin / 5) : 0,
            'carga_semanal_minutos' => $cargaSemanalMin,
            'dias' => [],
            'totais' => rm_totais_vazios($regrasExtra),
        ];

        foreach ($datas as $data) {
            $resumo = $resumos[$fid][$data][0] ?? [];
            $escala = $escalas[$fid][$data][0] ?? [];
            $movimentos = $registos[$fid][$data] ?? [];
            $tipoDia = $escala['tipo_dia'] ?? ($resumo['estado'] ?? 'sem_escala');
            $minutosPrevistos = (int) ($resumo['minutos_previstos'] ?? 0);
            $minutosTrabalhados = (int) ($resumo['minutos_trabalhados'] ?? 0);
            $minutosExtra = (int) ($resumo['minutos_extra'] ?? max(0, $minutosTrabalhados - $minutosPrevistos));
            $folgaTrabalhada = (int) ($resumo['folga_trabalhada'] ?? ($escala['folga_trabalhada'] ?? 0));
            $minutosFolgaTrabalhada = $folgaTrabalhada ? $minutosTrabalhados : 0;
            $extraBuckets = rm_distribuir_extra($minutosExtra, $regrasExtra);
            $observacoes = array_filter([
                $escala['observacoes'] ?? null,
                $resumo['observacoes'] ?? null,
                isset($correcoes[$fid][$data]) ? 'Correções manuais: ' . $correcoes[$fid][$data] : null,
            ]);

            $dia = [
                'data' => $data,
                'tipo_dia' => $tipoDia,
                'entradas_saidas' => rm_entradas_saidas($movimentos),
                'minutos_previstos' => $minutosPrevistos,
                'minutos_trabalhados' => $minutosTrabalhados,
                'minutos_pausas' => rm_calcular_pausas($movimentos),
                'minutos_atraso' => (int) ($resumo['minutos_atraso'] ?? 0),
                'falta' => (int) ($resumo['falta'] ?? 0),
                'ferias' => $tipoDia === 'ferias' || ($resumo['estado'] ?? '') === 'ferias' ? 1 : 0,
                'baixa' => $tipoDia === 'baixa' ? 1 : 0,
                'folga' => $tipoDia === 'folga' || ($resumo['estado'] ?? '') === 'folga' ? 1 : 0,
                'folga_trabalhada' => $folgaTrabalhada,
                'minutos_folga_trabalhada' => $minutosFolgaTrabalhada,
                'minutos_extra' => $minutosExtra,
                'extra_percentagens' => $extraBuckets,
                'banco_horas_minutos' => (int) ($banco[$fid][$data] ?? 0),
                'correcoes_manuais' => (int) ($correcoes[$fid][$data] ?? 0),
                'incidencias_pendentes' => (int) ($pendentes[$fid][$data] ?? 0),
                'observacoes' => implode(' | ', array_unique($observacoes)),
            ];

            rm_acumular_totais($funcionarioRel['totais'], $dia);
            $funcionarioRel['dias'][] = $dia;
        }

        $funcionarios[$fid] = $funcionarioRel;
        rm_somar_totais($totaisEquipa, $funcionarioRel['totais']);
    }

    return [
        'meta' => [
            'ano' => $ano,
            'mes' => $mes,
            'inicio' => $inicio,
            'fim' => $fim,
            'periodo' => rm_formatar_data($inicio) . ' a ' . rm_formatar_data($fim),
            'gerado_em' => date('Y-m-d H:i:s'),
            'gerado_por' => $geradoPor,
            'instituicao' => $GLOBALS['appConfig']['company_name'] ?? 'Instituicao',
            'titulo' => 'Relatorio mensal de assiduidade',
            'tipo' => $funcionarioId > 0 ? 'individual' : 'equipa',
        ],
        'regras_extra' => $regrasExtra,
        'funcionarios' => $funcionarios,
        'totais_equipa' => $totaisEquipa,
    ];
}

function rm_totais_vazios($regrasExtra)
{
    $extras = [];
    foreach ($regrasExtra as $regra) {
        $extras[(string) (int) $regra['porcentagem']] = 0;
    }

    return [
        'dias_previstos' => 0,
        'dias_trabalhados' => 0,
        'minutos_previstos' => 0,
        'minutos_trabalhados' => 0,
        'minutos_pausas' => 0,
        'minutos_atraso' => 0,
        'faltas' => 0,
        'ferias' => 0,
        'baixas' => 0,
        'folgas' => 0,
        'folgas_trabalhadas' => 0,
        'minutos_folga_trabalhada' => 0,
        'minutos_extra' => 0,
        'extra_percentagens' => $extras,
        'banco_horas_minutos' => 0,
        'correcoes_manuais' => 0,
        'incidencias_pendentes' => 0,
    ];
}

function rm_acumular_totais(&$totais, $dia)
{
    $totais['dias_previstos'] += $dia['minutos_previstos'] > 0 ? 1 : 0;
    $totais['dias_trabalhados'] += $dia['minutos_trabalhados'] > 0 ? 1 : 0;
    $totais['minutos_previstos'] += $dia['minutos_previstos'];
    $totais['minutos_trabalhados'] += $dia['minutos_trabalhados'];
    $totais['minutos_pausas'] += $dia['minutos_pausas'];
    $totais['minutos_atraso'] += $dia['minutos_atraso'];
    $totais['faltas'] += $dia['falta'];
    $totais['ferias'] += $dia['ferias'];
    $totais['baixas'] += $dia['baixa'];
    $totais['folgas'] += $dia['folga'];
    $totais['folgas_trabalhadas'] += $dia['folga_trabalhada'];
    $totais['minutos_folga_trabalhada'] += $dia['minutos_folga_trabalhada'];
    $totais['minutos_extra'] += $dia['minutos_extra'];
    $totais['banco_horas_minutos'] += $dia['banco_horas_minutos'];
    $totais['correcoes_manuais'] += $dia['correcoes_manuais'];
    $totais['incidencias_pendentes'] += $dia['incidencias_pendentes'];

    foreach ($dia['extra_percentagens'] as $percentagem => $minutos) {
        $totais['extra_percentagens'][$percentagem] = ($totais['extra_percentagens'][$percentagem] ?? 0) + $minutos;
    }
}

function rm_somar_totais(&$destino, $origem)
{
    foreach ($origem as $key => $value) {
        if ($key === 'extra_percentagens') {
            foreach ($value as $percentagem => $minutos) {
                $destino[$key][$percentagem] = ($destino[$key][$percentagem] ?? 0) + $minutos;
            }
            continue;
        }

        $destino[$key] += $value;
    }
}

function rm_carregar_banco_horas_resumo($conn, $inicio, $fim, $funcionarioIds)
{
    if (empty($funcionarioIds) || !rm_table_exists($conn, 'banco_horas')) {
        return [];
    }

    $ids = implode(',', array_map('intval', $funcionarioIds));
    $colFuncionario = rm_column_exists($conn, 'banco_horas', 'funcionario_id') ? 'funcionario_id' : null;
    if (!$colFuncionario) {
        return [];
    }

    $stmt = mysqli_prepare($conn, "SELECT funcionario_id, data_movimento,
        SUM(CASE WHEN tipo_movimento IN ('credito','compensacao','ajuste') THEN minutos ELSE -minutos END) AS minutos
        FROM banco_horas
        WHERE funcionario_id IN ($ids) AND data_movimento BETWEEN ? AND ?
        GROUP BY funcionario_id, data_movimento");
    mysqli_stmt_bind_param($stmt, 'ss', $inicio, $fim);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[(int) $row['funcionario_id']][$row['data_movimento']] = (int) $row['minutos'];
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

function rm_carregar_registos_ponto($conn, $inicio, $fim, $funcionarioIds)
{
    if (empty($funcionarioIds) || !rm_table_exists($conn, 'registos_ponto') || !rm_column_exists($conn, 'registos_ponto', 'funcionario_id')) {
        return [];
    }

    $ids = implode(',', array_map('intval', $funcionarioIds));
    $dataExpr = rm_column_exists($conn, 'registos_ponto', 'data_referencia') ? 'COALESCE(data_referencia, DATE(data_hora))' : 'DATE(data_hora)';
    $stmt = mysqli_prepare($conn, "SELECT id, funcionario_id, tipo, data_hora, estado, $dataExpr AS data_registo
        FROM registos_ponto
        WHERE funcionario_id IN ($ids)
          AND $dataExpr BETWEEN ? AND ?
          AND estado IN ('valido','corrigido','pendente')
        ORDER BY funcionario_id ASC, data_registo ASC, data_hora ASC, id ASC");
    mysqli_stmt_bind_param($stmt, 'ss', $inicio, $fim);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[(int) $row['funcionario_id']][$row['data_registo']][] = $row;
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

function rm_carregar_correcoes($conn, $inicio, $fim, $funcionarioIds)
{
    if (empty($funcionarioIds) || !rm_table_exists($conn, 'registos_ponto') || !rm_column_exists($conn, 'registos_ponto', 'funcionario_id')) {
        return [];
    }

    $ids = implode(',', array_map('intval', $funcionarioIds));
    $dataExpr = rm_column_exists($conn, 'registos_ponto', 'data_referencia') ? 'COALESCE(data_referencia, DATE(data_hora))' : 'DATE(data_hora)';
    $manualExpr = rm_column_exists($conn, 'registos_ponto', 'registo_manual') ? 'registo_manual = 1' : "origem = 'manual'";
    $stmt = mysqli_prepare($conn, "SELECT funcionario_id, $dataExpr AS data_registo, COUNT(*) AS total
        FROM registos_ponto
        WHERE funcionario_id IN ($ids) AND $dataExpr BETWEEN ? AND ? AND $manualExpr
        GROUP BY funcionario_id, data_registo");
    mysqli_stmt_bind_param($stmt, 'ss', $inicio, $fim);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[(int) $row['funcionario_id']][$row['data_registo']] = (int) $row['total'];
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

function rm_carregar_incidencias_pendentes($conn, $inicio, $fim, $funcionarioIds)
{
    if (empty($funcionarioIds) || !rm_table_exists($conn, 'registos_ponto') || !rm_column_exists($conn, 'registos_ponto', 'funcionario_id')) {
        return [];
    }

    $ids = implode(',', array_map('intval', $funcionarioIds));
    $dataExpr = rm_column_exists($conn, 'registos_ponto', 'data_referencia') ? 'COALESCE(data_referencia, DATE(data_hora))' : 'DATE(data_hora)';
    $stmt = mysqli_prepare($conn, "SELECT funcionario_id, $dataExpr AS data_registo, COUNT(*) AS total
        FROM registos_ponto
        WHERE funcionario_id IN ($ids) AND $dataExpr BETWEEN ? AND ? AND estado = 'pendente'
        GROUP BY funcionario_id, data_registo");
    mysqli_stmt_bind_param($stmt, 'ss', $inicio, $fim);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[(int) $row['funcionario_id']][$row['data_registo']] = (int) $row['total'];
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

function rm_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rm_xlsx_cell($value, $row, $col)
{
    $ref = rm_xlsx_col($col) . $row;

    if (is_int($value) || is_float($value)) {
        return '<c r="' . $ref . '"><v>' . $value . '</v></c>';
    }

    $value = htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    return '<c r="' . $ref . '" t="inlineStr"><is><t>' . $value . '</t></is></c>';
}

function rm_xlsx_xml_text($value)
{
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function rm_xlsx_col($index)
{
    $letters = '';
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letters = chr(65 + $mod) . $letters;
        $index = intdiv($index - $mod, 26);
    }

    return $letters;
}

function rm_xlsx_sheet_xml($rows)
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

    foreach ($rows as $rowIndex => $row) {
        $excelRow = $rowIndex + 1;
        $xml .= '<row r="' . $excelRow . '">';
        foreach (array_values($row) as $colIndex => $value) {
            $xml .= rm_xlsx_cell($value, $excelRow, $colIndex + 1);
        }
        $xml .= '</row>';
    }

    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function rm_xlsx_rows_resumo($relatorio)
{
    $rows = [
        [$relatorio['meta']['instituicao']],
        [$relatorio['meta']['titulo']],
        ['Período', $relatorio['meta']['periodo']],
        ['Gerado em', rm_formatar_data($relatorio['meta']['gerado_em']) . ' ' . date('H:i', strtotime($relatorio['meta']['gerado_em']))],
        ['Gerado por', $relatorio['meta']['gerado_por']],
        [],
        array_merge(
            ['Funcionário', 'Equipa', 'Função', 'Carga diária', 'Carga semanal', 'Dias previstos', 'Dias trabalhados', 'Horas previstas', 'Horas trabalhadas', 'Pausas', 'Atrasos', 'Faltas', 'Férias', 'Baixas', 'Folgas', 'Folgas trabalhadas', 'Horas em folga', 'Horas extra'],
            array_map(fn($r) => 'Extra ' . (int) $r['porcentagem'] . '%', $relatorio['regras_extra']),
            ['Banco horas', 'Correções', 'Incidências pendentes']
        ),
    ];

    foreach ($relatorio['funcionarios'] as $funcionario) {
        $t = $funcionario['totais'];
        $row = [
            $funcionario['nome'],
            $funcionario['equipa'],
            $funcionario['funcao'],
            rm_formatar_minutos($funcionario['carga_diaria_minutos']),
            rm_formatar_minutos($funcionario['carga_semanal_minutos']),
            $t['dias_previstos'],
            $t['dias_trabalhados'],
            rm_formatar_minutos($t['minutos_previstos']),
            rm_formatar_minutos($t['minutos_trabalhados']),
            rm_formatar_minutos($t['minutos_pausas']),
            rm_formatar_minutos($t['minutos_atraso']),
            $t['faltas'],
            $t['ferias'],
            $t['baixas'],
            $t['folgas'],
            $t['folgas_trabalhadas'],
            rm_formatar_minutos($t['minutos_folga_trabalhada']),
            rm_formatar_minutos($t['minutos_extra']),
        ];
        foreach ($relatorio['regras_extra'] as $regra) {
            $percentagem = (string) (int) $regra['porcentagem'];
            $row[] = rm_formatar_minutos($t['extra_percentagens'][$percentagem] ?? 0);
        }
        $row[] = rm_formatar_minutos($t['banco_horas_minutos']);
        $row[] = $t['correcoes_manuais'];
        $row[] = $t['incidencias_pendentes'];
        $rows[] = $row;
    }

    $t = $relatorio['totais_equipa'];
    $rows[] = [];
    $totalRow = ['Total da equipa', '', '', '', '', $t['dias_previstos'], $t['dias_trabalhados'], rm_formatar_minutos($t['minutos_previstos']), rm_formatar_minutos($t['minutos_trabalhados']), rm_formatar_minutos($t['minutos_pausas']), rm_formatar_minutos($t['minutos_atraso']), $t['faltas'], $t['ferias'], $t['baixas'], $t['folgas'], $t['folgas_trabalhadas'], rm_formatar_minutos($t['minutos_folga_trabalhada']), rm_formatar_minutos($t['minutos_extra'])];
    foreach ($relatorio['regras_extra'] as $regra) {
        $percentagem = (string) (int) $regra['porcentagem'];
        $totalRow[] = rm_formatar_minutos($t['extra_percentagens'][$percentagem] ?? 0);
    }
    $totalRow[] = rm_formatar_minutos($t['banco_horas_minutos']);
    $totalRow[] = $t['correcoes_manuais'];
    $totalRow[] = $t['incidencias_pendentes'];
    $rows[] = $totalRow;

    return $rows;
}

function rm_xlsx_rows_detalhe($relatorio)
{
    $rows = [
        array_merge(
            ['Funcionário', 'Data', 'Tipo dia', 'Entradas e saídas', 'Horas previstas', 'Horas trabalhadas', 'Pausas', 'Atrasos', 'Falta', 'Férias', 'Baixa', 'Folga', 'Folga trabalhada', 'Horas em folga', 'Horas extra'],
            array_map(fn($r) => 'Extra ' . (int) $r['porcentagem'] . '%', $relatorio['regras_extra']),
            ['Banco horas', 'Correções manuais', 'Incidências pendentes', 'Observações']
        ),
    ];

    foreach ($relatorio['funcionarios'] as $funcionario) {
        foreach ($funcionario['dias'] as $dia) {
            $row = [
                $funcionario['nome'],
                rm_formatar_data($dia['data']),
                $dia['tipo_dia'],
                $dia['entradas_saidas'],
                rm_formatar_minutos($dia['minutos_previstos']),
                rm_formatar_minutos($dia['minutos_trabalhados']),
                rm_formatar_minutos($dia['minutos_pausas']),
                rm_formatar_minutos($dia['minutos_atraso']),
                $dia['falta'],
                $dia['ferias'],
                $dia['baixa'],
                $dia['folga'],
                $dia['folga_trabalhada'],
                rm_formatar_minutos($dia['minutos_folga_trabalhada']),
                rm_formatar_minutos($dia['minutos_extra']),
            ];
            foreach ($relatorio['regras_extra'] as $regra) {
                $percentagem = (string) (int) $regra['porcentagem'];
                $row[] = rm_formatar_minutos($dia['extra_percentagens'][$percentagem] ?? 0);
            }
            $row[] = rm_formatar_minutos($dia['banco_horas_minutos']);
            $row[] = $dia['correcoes_manuais'];
            $row[] = $dia['incidencias_pendentes'];
            $row[] = $dia['observacoes'];
            $rows[] = $row;
        }
    }

    return $rows;
}

function rm_exportar_xlsx($relatorio, $filename)
{
    $xlsx = rm_zip_store([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
        'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>CSNSA</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop><HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>2</vt:i4></vt:variant></vt:vector></HeadingPairs><TitlesOfParts><vt:vector size="2" baseType="lpstr"><vt:lpstr>Resumo</vt:lpstr><vt:lpstr>Detalhe</vt:lpstr></vt:vector></TitlesOfParts><Company></Company><LinksUpToDate>false</LinksUpToDate><SharedDoc>false</SharedDoc><HyperlinksChanged>false</HyperlinksChanged><AppVersion>16.0300</AppVersion></Properties>',
        'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>' . rm_xlsx_xml_text($relatorio['meta']['titulo']) . '</dc:title><dc:creator>' . rm_xlsx_xml_text($relatorio['meta']['gerado_por']) . '</dc:creator><cp:lastModifiedBy>' . rm_xlsx_xml_text($relatorio['meta']['gerado_por']) . '</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:modified></cp:coreProperties>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Resumo" sheetId="1" r:id="rId1"/><sheet name="Detalhe" sheetId="2" r:id="rId2"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>',
        'xl/worksheets/sheet1.xml' => rm_xlsx_sheet_xml(rm_xlsx_rows_resumo($relatorio)),
        'xl/worksheets/sheet2.xml' => rm_xlsx_sheet_xml(rm_xlsx_rows_detalhe($relatorio)),
    ]);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/octet-stream');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . strlen($xlsx));
    echo $xlsx;
    exit;
}

function rm_zip_dos_time()
{
    $time = getdate();
    $dosTime = (($time['hours'] & 0x1f) << 11) | (($time['minutes'] & 0x3f) << 5) | ((int) floor($time['seconds'] / 2) & 0x1f);
    $dosDate = ((($time['year'] - 1980) & 0x7f) << 9) | (($time['mon'] & 0xf) << 5) | ($time['mday'] & 0x1f);
    return [$dosTime, $dosDate];
}

function rm_zip_store($files)
{
    [$dosTime, $dosDate] = rm_zip_dos_time();
    $local = '';
    $central = '';
    $offset = 0;

    foreach ($files as $name => $data) {
        $name = str_replace('\\', '/', $name);
        $crc = crc32($data);
        if ($crc < 0) {
            $crc += 4294967296;
        }

        $size = strlen($data);
        $nameLen = strlen($name);
        $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLen, 0) . $name;
        $centralHeader = pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLen, 0, 0, 0, 0, 0, $offset) . $name;

        $local .= $localHeader . $data;
        $central .= $centralHeader;
        $offset += strlen($localHeader) + $size;
    }

    $centralOffset = strlen($local);
    $centralSize = strlen($central);
    $count = count($files);
    $end = pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, $centralSize, $centralOffset, 0);

    return $local . $central . $end;
}
