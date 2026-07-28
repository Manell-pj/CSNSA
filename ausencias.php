<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/funcoes/ausencias_funcoes.php';
require_once __DIR__ . '/funcoes/calcular_resumo_diario_assiduidade.php';

function mes_nome_pt($mes)
{
    $nomes = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Marco',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro',
    ];

    return $nomes[(int) $mes] ?? '';
}
    
$utilizadorSessao = require_login($conn);
ac_require_any($conn, $utilizadorSessao, ['ausencias.pedir', 'ausencias.gerir', 'ferias.gerir', 'justificacoes.validar']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$utilizadorAutenticadoId = (int) ($_SESSION['utilizador_id'] ?? $_SESSION['user_id'] ?? 0);
$utilizadorAutenticado = null;
$temPermissaoAprovar = false;
$temPermissaoPedir = ac_can_any($conn, $utilizadorAutenticadoId, ['ausencias.pedir', 'ausencias.gerir']);
$pedidosSuportamFuncionario = ac_table_exists($conn, 'pedidos_ausencia') && ausencias_column_exists($conn, 'pedidos_ausencia', 'funcionario_id');

if ($utilizadorAutenticadoId > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT id, nome, email, estado FROM utilizadores WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $utilizadorAutenticadoId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $utilizadorAutenticado = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($utilizadorAutenticado) {
        $temPermissaoAprovar = pode_aprovar($conn, $utilizadorAutenticadoId);
    }
}

garantir_tipos_ausencia($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo 'Token CSRF inválido.';
        exit;
    }

    if (!$utilizadorAutenticado || $utilizadorAutenticado['estado'] !== 'ativo') {
        redirect_with_message('danger', 'Precisa de estar autenticado com um utilizador ativo.');
    }

    if ($acao === 'pedir') {
        ac_require_any($conn, $utilizadorSessao, ['ausencias.pedir', 'ausencias.gerir']);
        $tipoAusenciaId = (int) ($_POST['tipo_ausencia_id'] ?? 0);
        $funcionarioId = $temPermissaoAprovar ? (int) ($_POST['funcionario_id'] ?? 0) : (int) (get_funcionario_id_from_utilizador($conn, $utilizadorAutenticadoId) ?? 0);
        $utilizadorPedidoId = $utilizadorAutenticadoId;
        $dataInicio = trim($_POST['data_inicio'] ?? '');
        $dataFim = trim($_POST['data_fim'] ?? '');
        $motivo = trim($_POST['motivo'] ?? '');

        if ($tipoAusenciaId <= 0 || $dataInicio === '' || $dataFim === '' || $motivo === '') {
            redirect_with_message('danger', 'Preencha todos os campos obrigatórios.');
        }

        if ($dataFim < $dataInicio) {
            redirect_with_message('danger', 'A data fim não pode ser anterior à data início.');
        }

        if ($funcionarioId > 0) {
            $stmt = mysqli_prepare($conn, "SELECT id, utilizador_id FROM funcionarios WHERE id = ? AND estado = 'ativo' LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'i', $funcionarioId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $funcionarioPedido = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$funcionarioPedido) {
                redirect_with_message('danger', 'Funcionario invalido.');
            }

            if (!empty($funcionarioPedido['utilizador_id'])) {
                $utilizadorPedidoId = (int) $funcionarioPedido['utilizador_id'];
            }
        } else {
            $funcionarioId = null;
        }

        $stmt = mysqli_prepare($conn, 'SELECT id FROM tipos_ausencia WHERE id = ? AND ativo = 1 LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $tipoAusenciaId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $tipoExiste = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$tipoExiste) {
            redirect_with_message('danger', 'Tipo de ausência inválido.');
        }

        try {
            $anexo = guardar_anexo_seguro($_FILES['anexo'] ?? null);
            $dataInicioObj = new DateTime($dataInicio);
            $dataFimObj = new DateTime($dataFim);
            $totalDias = (float) $dataInicioObj->diff($dataFimObj)->days + 1;

            // Se for férias, tentar calcular dias reais usando a escala. Se não houver regras, manter cálculo por calendário.
            $computedNote = '';
            $calc = ['conflitos'=>[]];
            $stmtT = mysqli_prepare($conn, 'SELECT slug FROM tipos_ausencia WHERE id = ? LIMIT 1');
            mysqli_stmt_bind_param($stmtT, 'i', $tipoAusenciaId);
            mysqli_stmt_execute($stmtT);
            $rt = mysqli_stmt_get_result($stmtT);
            $trow = mysqli_fetch_assoc($rt);
            mysqli_stmt_close($stmtT);

            if ($trow && $trow['slug'] === 'ferias') {
                $calc = calcular_dias_ferias_por_escala($conn, $funcionarioId, $dataInicio, $dataFim);
                if ($calc['computado_por_escala']) {
                    $totalDias = $calc['dias'];
                    $computedNote = ' (total calculado pela escala: ' . $calc['dias'] . ' dias)';
                    if (!empty($calc['conflitos'])) {
                        $computedNote .= ' [Conflitos detetados: ' . count($calc['conflitos']) . ']';
                    }
                } else {
                    $computedNote = ' (sem regras de escala para este período; calculado por calendário)';
                }
            }

            if ($pedidosSuportamFuncionario) {
                $stmt = mysqli_prepare($conn, "INSERT INTO pedidos_ausencia
                    (utilizador_id, funcionario_id, tipo_ausencia_id, data_inicio, data_fim, total_dias, motivo, ficheiro_justificativo, estado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendente')");
                mysqli_stmt_bind_param($stmt, 'iiissdss', $utilizadorPedidoId, $funcionarioId, $tipoAusenciaId, $dataInicio, $dataFim, $totalDias, $motivo, $anexo);
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO pedidos_ausencia
                    (utilizador_id, tipo_ausencia_id, data_inicio, data_fim, total_dias, motivo, ficheiro_justificativo, estado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')");
                mysqli_stmt_bind_param($stmt, 'iissdss', $utilizadorPedidoId, $tipoAusenciaId, $dataInicio, $dataFim, $totalDias, $motivo, $anexo);
            }
            mysqli_stmt_execute($stmt);
            $insertId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Se foram detetados conflitos, guarda uma nota em observacoes_aprovacao para ajudar a aprovação.
            if (!empty($calc['conflitos'])) {
                $note = 'Conflitos detetados no pedido: ' . json_encode($calc['conflitos']);
                $stmtN = mysqli_prepare($conn, 'UPDATE pedidos_ausencia SET observacoes_aprovacao = CONCAT(IFNULL(observacoes_aprovacao, ""), ?) WHERE id = ?');
                mysqli_stmt_bind_param($stmtN, 'si', $note, $insertId);
                mysqli_stmt_execute($stmtN);
                mysqli_stmt_close($stmtN);
            }

            redirect_with_message('success', 'Pedido registado com sucesso. Ficou pendente de aprovação.' . $computedNote);
        } catch (RuntimeException $e) {
            redirect_with_message('danger', $e->getMessage());
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível registar o pedido.');
        }
    }

    if ($acao === 'aprovar' || $acao === 'recusar') {
        ac_require_any($conn, $utilizadorSessao, ['justificacoes.validar', 'ferias.gerir']);
        if (!$temPermissaoAprovar) {
            redirect_with_message('danger', 'Não tem permissão para aprovar ou recusar pedidos.');
        }

        $pedidoId = (int) ($_POST['id'] ?? 0);
        $novoEstado = $acao === 'aprovar' ? 'aprovado' : 'rejeitado';
        $observacoes = trim($_POST['observacoes_aprovacao'] ?? '');

        if ($pedidoId <= 0) {
            redirect_with_message('danger', 'Pedido inválido.');
        }

        $stmt = mysqli_prepare($conn, "UPDATE pedidos_ausencia
            SET estado = ?, aprovado_por = ?, aprovado_at = NOW(), observacoes_aprovacao = ?
            WHERE id = ? AND estado = 'pendente'");
        mysqli_stmt_bind_param($stmt, 'sisi', $novoEstado, $utilizadorAutenticadoId, $observacoes, $pedidoId);
        mysqli_stmt_execute($stmt);
        $linhasAfetadas = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($linhasAfetadas < 1) {
            redirect_with_message('danger', 'O pedido já foi tratado ou não existe.');
        }

        // Se aprovado e for férias, insere em ferias_ausencias e marca dias na escala (preservando histórico).
        if ($novoEstado === 'aprovado') {
            $stmtP = mysqli_prepare($conn, 'SELECT p.*, t.slug FROM pedidos_ausencia p INNER JOIN tipos_ausencia t ON t.id = p.tipo_ausencia_id WHERE p.id = ? LIMIT 1');
            mysqli_stmt_bind_param($stmtP, 'i', $pedidoId);
            mysqli_stmt_execute($stmtP);
            $rp = mysqli_stmt_get_result($stmtP);
            $pedidoRow = mysqli_fetch_assoc($rp);
            mysqli_stmt_close($stmtP);

            if ($pedidoRow) {
                // Obter funcionário.
                $funcionarioId = ($pedidosSuportamFuncionario && !empty($pedidoRow['funcionario_id'])) ? (int) $pedidoRow['funcionario_id'] : null;
                if (!$funcionarioId) {
                    $stmtF = mysqli_prepare($conn, 'SELECT id, utilizador_id FROM funcionarios WHERE utilizador_id = ? LIMIT 1');
                    mysqli_stmt_bind_param($stmtF, 'i', $pedidoRow['utilizador_id']);
                    mysqli_stmt_execute($stmtF);
                    $rf = mysqli_stmt_get_result($stmtF);
                    $funcRow = mysqli_fetch_assoc($rf);
                    mysqli_stmt_close($stmtF);
                    $funcionarioId = $funcRow['id'] ?? null;
                }

                $pedidoAusenciaId = (int) $pedidoRow['id'];
                $funcionarioIdParam = $funcionarioId ? (int) $funcionarioId : null;
                $pedidoUtilizadorId = (int) $pedidoRow['utilizador_id'];
                $tipoAusenciaId = (int) $pedidoRow['tipo_ausencia_id'];
                $dataInicioPedido = $pedidoRow['data_inicio'];
                $dataFimPedido = $pedidoRow['data_fim'];
                $horaInicioPedido = $pedidoRow['hora_inicio'] ?? null;
                $horaFimPedido = $pedidoRow['hora_fim'] ?? null;
                $diaCompleto = 1;
                $minutosJustificados = null;
                $estadoPedido = $pedidoRow['estado'];
                $motivoPedido = $pedidoRow['motivo'] ?? null;
                $ficheiroPedido = $pedidoRow['ficheiro_justificativo'] ?? null;
                $aprovadoPor = (int) $utilizadorAutenticadoId;
                $aprovadoAt = date('Y-m-d H:i:s');

                $stmtIns = mysqli_prepare($conn, 'INSERT INTO ferias_ausencias
                    (pedido_ausencia_id, funcionario_id, utilizador_id, tipo_ausencia_id, data_inicio, data_fim, hora_inicio, hora_fim, dia_completo, minutos_justificados, estado, motivo, ficheiro_justificativo, aprovado_por, aprovado_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param(
                    $stmtIns,
                    'iiiissssiisssis',
                    $pedidoAusenciaId,
                    $funcionarioIdParam,
                    $pedidoUtilizadorId,
                    $tipoAusenciaId,
                    $dataInicioPedido,
                    $dataFimPedido,
                    $horaInicioPedido,
                    $horaFimPedido,
                    $diaCompleto,
                    $minutosJustificados,
                    $estadoPedido,
                    $motivoPedido,
                    $ficheiroPedido,
                    $aprovadoPor,
                    $aprovadoAt
                );
                mysqli_stmt_execute($stmtIns);
                mysqli_stmt_close($stmtIns);

                // Garantir tabela de histórico existe.
                if ($pedidoRow['slug'] === 'ferias' && $funcionarioId) {
                $tabelaEscalaAprovacao = ac_table_exists($conn, 'escala_funcionarios') ? 'escala_funcionarios' : (ac_table_exists($conn, 'escala_mensal_dias') ? 'escala_mensal_dias' : null);
                $usarHistoricoEscalaAntiga = $tabelaEscalaAprovacao === 'escala_mensal_dias';

                if ($usarHistoricoEscalaAntiga) {
                    ensure_escala_hist_table($conn);
                }
                // Atualizar escala_mensal_dias: para cada dia no período, se existir entrada, guardar histórico e marcar férias.
                $cur = new DateTime($pedidoRow['data_inicio']);
                $end = new DateTime($pedidoRow['data_fim']);
                while ($tabelaEscalaAprovacao && $cur <= $end) {
                    $d = $cur->format('Y-m-d');
                    $q = mysqli_prepare($conn, "SELECT id, funcionario_id, tipo_dia, turno_id, 0 AS minutos_previstos, observacoes FROM $tabelaEscalaAprovacao WHERE funcionario_id = ? AND data_escala = ? LIMIT 1");
                    mysqli_stmt_bind_param($q, 'is', $funcionarioId, $d);
                    mysqli_stmt_execute($q);
                    $resq = mysqli_stmt_get_result($q);
                    $esc = mysqli_fetch_assoc($resq);
                    mysqli_stmt_close($q);

                    if ($esc) {
                        $esc_id = (int) $esc['id'];
                        $esc_func = (int) $esc['funcionario_id'];
                        $esc_turno = isset($esc['turno_id']) ? (int) $esc['turno_id'] : null;
                        $esc_tipo = (string) $esc['tipo_dia'];
                        $esc_min = (int)$esc['minutos_previstos'];
                        $esc_obs = $esc['observacoes'] ?? '';

                        if ($usarHistoricoEscalaAntiga) {
                            $stmtHist = mysqli_prepare($conn, 'INSERT INTO escala_mensal_dias_hist
                                (escala_dia_id, funcionario_id, data_escala, turno_id, tipo_dia, minutos_previstos, observacoes, alterado_por)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                            mysqli_stmt_bind_param($stmtHist, 'iisisisi', $esc_id, $esc_func, $d, $esc_turno, $esc_tipo, $esc_min, $esc_obs, $aprovadoPor);
                            mysqli_stmt_execute($stmtHist);
                            mysqli_stmt_close($stmtHist);
                        }

                        $note = 'Férias aprovadas (pedido ' . $pedidoId . ') por ' . mysqli_real_escape_string($conn, $utilizadorAutenticado['nome']) . ' em ' . date('Y-m-d H:i');
                        $qup = mysqli_prepare($conn, "UPDATE $tabelaEscalaAprovacao SET tipo_dia = ?, observacoes = CONCAT(IFNULL(observacoes, ''), ?) WHERE id = ?");
                        $tfer = 'ferias';
                        mysqli_stmt_bind_param($qup, 'ssi', $tfer, $note, $esc_id);
                        mysqli_stmt_execute($qup);
                        mysqli_stmt_close($qup);
                    }

                    $cur->modify('+1 day');
                }
                }
             }
         }

         redirect_with_message('success', $acao === 'aprovar' ? 'Pedido aprovado com sucesso.' : 'Pedido recusado com sucesso.');
    }
}

$tiposAusencia = [];
$stmt = mysqli_prepare($conn, "SELECT id, nome, slug
    FROM tipos_ausencia
    WHERE ativo = 1 AND slug IN ('ferias', 'falta-justificada', 'baixa-medica', 'folga')
    ORDER BY FIELD(slug, 'ferias', 'falta-justificada', 'baixa-medica', 'folga')");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $tiposAusencia[] = $row;
}
mysqli_stmt_close($stmt);

$destinatariosAusencia = carregar_destinatarios_ausencia($conn);
$pedidos = [];

if ($utilizadorAutenticado) {
    if ($temPermissaoAprovar) {
        $joinFuncionario = $pedidosSuportamFuncionario ? 'LEFT JOIN funcionarios f ON f.id = pa.funcionario_id' : 'LEFT JOIN funcionarios f ON f.utilizador_id = pa.utilizador_id';
        $sql = "SELECT pa.*, ta.nome AS tipo_nome, u.nome AS utilizador_nome, f.nome AS funcionario_nome, aprovador.nome AS aprovador_nome
            FROM pedidos_ausencia pa
            INNER JOIN tipos_ausencia ta ON ta.id = pa.tipo_ausencia_id
            INNER JOIN utilizadores u ON u.id = pa.utilizador_id
            $joinFuncionario
            LEFT JOIN utilizadores aprovador ON aprovador.id = pa.aprovado_por
            ORDER BY pa.created_at DESC, pa.id DESC";
        $stmt = mysqli_prepare($conn, $sql);
    } else {
        $funcionarioProprioId = get_funcionario_id_from_utilizador($conn, $utilizadorAutenticadoId);
        $joinFuncionario = $pedidosSuportamFuncionario ? 'LEFT JOIN funcionarios f ON f.id = pa.funcionario_id' : 'LEFT JOIN funcionarios f ON f.utilizador_id = pa.utilizador_id';
        $whereProprio = $pedidosSuportamFuncionario ? 'pa.utilizador_id = ? OR (? IS NOT NULL AND pa.funcionario_id = ?)' : 'pa.utilizador_id = ?';
        $sql = "SELECT pa.*, ta.nome AS tipo_nome, u.nome AS utilizador_nome, f.nome AS funcionario_nome, aprovador.nome AS aprovador_nome
            FROM pedidos_ausencia pa
            INNER JOIN tipos_ausencia ta ON ta.id = pa.tipo_ausencia_id
            INNER JOIN utilizadores u ON u.id = pa.utilizador_id
            $joinFuncionario
            LEFT JOIN utilizadores aprovador ON aprovador.id = pa.aprovado_por
            WHERE $whereProprio
            ORDER BY pa.created_at DESC, pa.id DESC";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            redirect_with_message('danger', 'Erro ao preparar historico de pedidos: ' . mysqli_error($conn));
        }
        if ($pedidosSuportamFuncionario) {
            mysqli_stmt_bind_param($stmt, 'iii', $utilizadorAutenticadoId, $funcionarioProprioId, $funcionarioProprioId);
        } else {
            mysqli_stmt_bind_param($stmt, 'i', $utilizadorAutenticadoId);
        }
    }

    if (!$stmt) {
        redirect_with_message('danger', 'Erro ao preparar historico de pedidos: ' . mysqli_error($conn));
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $pedidos[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Férias futuras (aprovadas) - área própria
$ausenciasAprovadas = [];
$joinFuncionarioAprovadas = $pedidosSuportamFuncionario ? 'LEFT JOIN funcionarios f ON f.id = pa.funcionario_id' : 'LEFT JOIN funcionarios f ON f.utilizador_id = pa.utilizador_id';
$stmt = mysqli_prepare($conn, "SELECT pa.id, pa.utilizador_id, u.nome AS utilizador_nome, f.nome AS funcionario_nome, ta.nome AS tipo_nome, pa.data_inicio, pa.data_fim, pa.total_dias
    FROM pedidos_ausencia pa
    INNER JOIN tipos_ausencia ta ON ta.id = pa.tipo_ausencia_id
    INNER JOIN utilizadores u ON u.id = pa.utilizador_id
    $joinFuncionarioAprovadas
    WHERE pa.estado = 'aprovado'
    ORDER BY pa.data_inicio DESC, pa.id DESC
    LIMIT 200");
if (!$stmt) {
    redirect_with_message('danger', 'Erro ao preparar ausencias aprovadas: ' . mysqli_error($conn));
}
mysqli_stmt_execute($stmt);
$resf = mysqli_stmt_get_result($stmt);
while ($r = mysqli_fetch_assoc($resf)) { $ausenciasAprovadas[] = $r; }
mysqli_stmt_close($stmt);

$equipasRelatorio = [];
if (ac_table_exists($conn, 'equipas')) {
    $stmt = mysqli_prepare($conn, 'SELECT id, nome FROM equipas WHERE ativo = 1 ORDER BY nome ASC');
    mysqli_stmt_execute($stmt);
    $resEq = mysqli_stmt_get_result($stmt);
    while ($eq = mysqli_fetch_assoc($resEq)) {
        $equipasRelatorio[] = $eq;
    }
    mysqli_stmt_close($stmt);
}

// Relatório de ausências: aceitar filtros via GET
$relatorio = [];
if (isset($_GET['relatorio']) && $temPermissaoAprovar) {
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';
    $year = isset($_GET['year']) ? (int) $_GET['year'] : null;
    $month = isset($_GET['month']) ? (int) $_GET['month'] : null;
    // If year+month provided, compute start/end
    if ($year && $month) {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $endDt = new DateTime($start);
        $endDt->modify('last day of this month');
        $end = $endDt->format('Y-m-d');
    } elseif ($year && $month === 0 && $start === '' && $end === '') {
        $start = sprintf('%04d-01-01', $year);
        $end = sprintf('%04d-12-31', $year);
    }

    // validar datas
    $startOk = DateTime::createFromFormat('Y-m-d', $start) !== false;
    $endOk = DateTime::createFromFormat('Y-m-d', $end) !== false;
    if ($startOk && $endOk && $start <= $end) {
        // impedir períodos futuros em relatórios concluídos: se end > hoje e não for admin, recusar
        $today = date('Y-m-d');
        if ($end > $today && !$temPermissaoAprovar) {
            $relatorio['erro'] = 'Não é permitido gerar relatórios com períodos futuros.';
        } elseif (!ac_table_exists($conn, 'resumo_diario_assiduidade')) {
            $relatorio['erro'] = 'Ainda não existem resumos diários para gerar o relatório. Calcule a assiduidade do período primeiro.';
        } else {
            // agregação usando resumo_diario_assiduidade (recomenda-se executar o calculador primeiro)
            $errosCalculo = [];
            $cursorCalculo = new DateTime($start);
            $fimCalculo = new DateTime($end);
            while ($cursorCalculo <= $fimCalculo) {
                try {
                    $resultadoCalculo = calcular_resumo_diario_assiduidade($conn, $cursorCalculo->format('Y-m-d'));
                    if (!empty($resultadoCalculo['erros'])) {
                        $errosCalculo = array_merge($errosCalculo, $resultadoCalculo['erros']);
                    }
                } catch (Throwable $e) {
                    $errosCalculo[] = $cursorCalculo->format('Y-m-d') . ': ' . $e->getMessage();
                }
                $cursorCalculo->modify('+1 day');
            }

            if (!empty($errosCalculo)) {
                $relatorio['aviso'] = 'Alguns dias nao foram recalculados: ' . implode(' | ', array_slice($errosCalculo, 0, 5));
            }

            $sql = "SELECT r.funcionario_id, f.nome AS funcionario_nome, SUM(r.falta) AS dias_falta, SUM(GREATEST(r.minutos_previstos - r.minutos_trabalhados, 0)) AS minutos_falta, SUM(r.minutos_atraso) AS minutos_atraso, SUM(r.minutos_extra) AS minutos_extra FROM resumo_diario_assiduidade r INNER JOIN funcionarios f ON f.id = r.funcionario_id WHERE r.data BETWEEN ? AND ?";
            $params = [$start, $end];
            $types = 'ss';
            if (!empty($_GET['equipa_id'])) {
                $sql .= ' AND r.equipa_id = ?'; $params[] = (int) $_GET['equipa_id']; $types .= 'i';
            }
            if (!empty($_GET['tipo_falta']) && in_array($_GET['tipo_falta'], ['ausente','ferias','baixa'])) {
                // filter by estado
                $sql .= ' AND r.estado = ?'; $params[] = $_GET['tipo_falta']; $types .= 's';
            }
            $sql .= ' GROUP BY r.funcionario_id, f.nome ORDER BY f.nome ASC';

            $stmt = mysqli_prepare($conn, $sql);
            // bind params dynamically
            $refs = [];
            foreach ($params as $k => $v) $refs[$k] = &$params[$k];
            array_unshift($refs, $types);
            call_user_func_array([$stmt, 'bind_param'], $refs);
            mysqli_stmt_execute($stmt);
            $rset = mysqli_stmt_get_result($stmt);
            $rows = [];
            while ($row = mysqli_fetch_assoc($rset)) $rows[] = $row;
            mysqli_stmt_close($stmt);
            $relatorio['rows'] = $rows;
            $relatorio['start'] = $start; $relatorio['end'] = $end;
                    // export CSV if requested
                    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
                        header('Content-Type: text/csv; charset=utf-8');
                        header('Content-Disposition: attachment; filename="relatorio_ausencias_' . $start . '_to_' . $end . '.csv"');
                        $out = fopen('php://output', 'w');
                        fputcsv($out, ['Funcionário','Dias falta','Minutos falta','Minutos atraso','Minutos extra']);
                        foreach ($rows as $r) {
                            fputcsv($out, [$r['funcionario_nome'], $r['dias_falta'], $r['minutos_falta'], $r['minutos_atraso'], $r['minutos_extra']]);
                        }
                        fclose($out);
                        exit;
                    }
        }
    } else {
        $relatorio['erro'] = 'Datas inválidas.';
    }
}

$alertType = $_GET['type'] ?? '';
$alertMessage = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt">
<?php include 'includes/head.php'; ?>

<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-panel">
            <div class="main-header">
                <?php include 'includes/header.php'; ?>
            </div>

            <div class="container">
                <div class="page-inner">
                    <div class="page-header">
                        <h3 class="fw-bold mb-3">Ausências</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="principal.php">
                                    <i class="icon-home"></i>
                                </a>
                            </li>
                            <li class="separator">
                                <i class="icon-arrow-right"></i>
                            </li>
                            <li class="nav-item">
                                <a href="ausencias.php">Ausências</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h4 class="card-title">Relatório de ausências</h4>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($relatorio['erro'])): ?>
                                <div class="alert alert-danger"><?php echo e($relatorio['erro']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($relatorio['aviso'])): ?>
                                <div class="alert alert-warning"><?php echo e($relatorio['aviso']); ?></div>
                            <?php endif; ?>
                            <form method="get" class="row g-2 align-items-end mb-3">
                                <input type="hidden" name="relatorio" value="1">
                                <div class="col-md-2"><label class="form-label">Ano</label>
                                    <select id="rel_year" name="year" class="form-select">
                                        <?php $cy = (int) date('Y'); for ($y = $cy; $y >= $cy-5; $y--): ?>
                                            <option value="<?php echo $y; ?>" <?php echo (isset($_GET['year']) && (int)$_GET['year'] === $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-2"><label class="form-label">Mês</label>
                                    <select id="rel_month" name="month" class="form-select">
                                        <option value="0">-- Todos --</option>
                                        <?php for ($m=1;$m<=12;$m++): ?>
                                            <option value="<?php echo $m; ?>" <?php echo (isset($_GET['month']) && (int)$_GET['month'] === $m) ? 'selected' : ''; ?>><?php echo e(mes_nome_pt($m)); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3"><label class="form-label">Ou intervalo (opcional)</label><div class="input-group"><input type="date" name="start" class="form-control" value="<?php echo e($_GET['start'] ?? ''); ?>"><input type="date" name="end" class="form-control" value="<?php echo e($_GET['end'] ?? ''); ?>"></div></div>
                                <div class="col-md-2"><label class="form-label">Equipa</label>
                                    <select name="equipa_id" class="form-select">
                                        <option value="">Todas</option>
                                        <?php foreach ($equipasRelatorio as $equipaRelatorio): ?>
                                            <option value="<?php echo (int) $equipaRelatorio['id']; ?>" <?php echo (int) ($_GET['equipa_id'] ?? 0) === (int) $equipaRelatorio['id'] ? 'selected' : ''; ?>>
                                                <?php echo e($equipaRelatorio['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2"><label class="form-label">Tipo</label>
                                    <select name="tipo_falta" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="ausente" <?php echo ($_GET['tipo_falta'] ?? '') === 'ausente' ? 'selected' : ''; ?>>Ausente</option>
                                        <option value="ferias" <?php echo ($_GET['tipo_falta'] ?? '') === 'ferias' ? 'selected' : ''; ?>>Férias</option>
                                        <option value="baixa" <?php echo ($_GET['tipo_falta'] ?? '') === 'baixa' ? 'selected' : ''; ?>>Baixa</option>
                                    </select>
                                </div>
                                <div class="col-md-2"><button class="btn btn-primary">Gerar</button></div>
                            </form>

                            <?php if (!empty($relatorio['rows'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead><tr><th>Funcionário</th><th>Dias falta</th><th>Minutos falta</th><th>Minutos atraso</th><th>Minutos extra</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($relatorio['rows'] as $r): ?>
                                            <tr>
                                                <td><?php echo e($r['funcionario_nome']); ?></td>
                                                <td><?php echo e($r['dias_falta']); ?></td>
                                                <td><?php echo e($r['minutos_falta']); ?></td>
                                                <td><?php echo e($r['minutos_atraso']); ?></td>
                                                <td><?php echo e($r['minutos_extra']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($alertMessage !== ''): ?>
                        <div class="alert alert-<?php echo e($alertType ?: 'info'); ?> alert-dismissible fade show" role="alert">
                            <?php echo e($alertMessage); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!$utilizadorAutenticado): ?>
                        <div class="alert alert-warning" role="alert">
                            Não existe utilizador autenticado na sessão. Depois de criares o login, define
                            <strong>$_SESSION['utilizador_id']</strong> com o ID do utilizador autenticado.
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <h4 class="card-title">
                                    Histórico de pedidos
                                    <?php if ($temPermissaoAprovar): ?>
                                        <span class="badge badge-primary ms-2">Aprovação</span>
                                    <?php endif; ?>
                                </h4>
                                <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#modalPedirAusencia" <?php echo (!$utilizadorAutenticado || !$temPermissaoPedir) ? 'disabled' : ''; ?>>
                                    <i class="fa fa-plus"></i>
                                    Novo pedido
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-ausencias" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <?php if ($temPermissaoAprovar): ?>
                                                <th>Funcionario / Utilizador</th>
                                            <?php endif; ?>
                                            <th>Tipo</th>
                                            <th>Início</th>
                                            <th>Fim</th>
                                            <th>Dias</th>
                                            <th>Estado</th>
                                            <th>Anexo</th>
                                            <?php if ($temPermissaoAprovar): ?>
                                                <th style="width: 130px">Ações</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pedidos as $pedido): ?>
                                            <tr>
                                                <?php if ($temPermissaoAprovar): ?>
                                                    <td><?php echo e($pedido['funcionario_nome'] ?: $pedido['utilizador_nome']); ?></td>
                                                <?php endif; ?>
                                                <td><?php echo e($pedido['tipo_nome']); ?></td>
                                                <td><?php echo e(date('d/m/Y', strtotime($pedido['data_inicio']))); ?></td>
                                                <td><?php echo e(date('d/m/Y', strtotime($pedido['data_fim']))); ?></td>
                                                <td><?php echo e($pedido['total_dias']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo e(estado_badge($pedido['estado'])); ?>">
                                                        <?php echo e(estado_label($pedido['estado'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($pedido['ficheiro_justificativo']): ?>
                                                        <a href="download_ausencia.php?id=<?php echo (int)$pedido['id']; ?>">Ver</a>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <?php if ($temPermissaoAprovar): ?>
                                                    <td>
                                                        <?php if ($pedido['estado'] === 'pendente'): ?>
                                                            <div class="form-button-action">
                                                                <button type="button" class="btn btn-link btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#modalAprovarPedido<?php echo (int) $pedido['id']; ?>" title="Aprovar">
                                                                    <i class="fa fa-check"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-link btn-danger" data-bs-toggle="modal" data-bs-target="#modalRecusarPedido<?php echo (int) $pedido['id']; ?>" title="Recusar">
                                                                    <i class="fa fa-times"></i>
                                                                </button>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">Tratado</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header d-flex align-items-center">
                            <h4 class="card-title">Ferias / Justificacoes aprovadas</h4>
                        </div>
                        <div class="card-body">
                            <?php if (empty($ausenciasAprovadas)): ?>
                                <div class="alert alert-info">Sem ausencias aprovadas.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead><tr><th>Funcionario / Utilizador</th><th>Tipo</th><th>Inicio</th><th>Fim</th><th>Dias</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($ausenciasAprovadas as $ausenciaAprovada): ?>
                                            <tr>
                                                <td><?php echo e($ausenciaAprovada['funcionario_nome'] ?: $ausenciaAprovada['utilizador_nome']); ?></td>
                                                <td><?php echo e($ausenciaAprovada['tipo_nome']); ?></td>
                                                <td><?php echo e(date('d/m/Y', strtotime($ausenciaAprovada['data_inicio']))); ?></td>
                                                <td><?php echo e(date('d/m/Y', strtotime($ausenciaAprovada['data_fim']))); ?></td>
                                                <td><?php echo e($ausenciaAprovada['total_dias']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <div class="modal fade" id="modalPedirAusencia" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="post" enctype="multipart/form-data" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="acao" value="pedir">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Novo pedido de ausência</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <?php if ($temPermissaoAprovar): ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Funcionario</label>
                            <select name="funcionario_id" class="form-select">
                                <option value="">Associar ao utilizador autenticado</option>
                                <?php foreach ($destinatariosAusencia as $destinatarioAusencia): ?>
                                    <option value="<?php echo (int) $destinatarioAusencia['funcionario_id']; ?>"><?php echo e($destinatarioAusencia['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo *</label>
                            <select name="tipo_ausencia_id" class="form-select" required>
                                <option value="">Selecionar tipo</option>
                                <?php foreach ($tiposAusencia as $tipo): ?>
                                    <option value="<?php echo (int) $tipo['id']; ?>"><?php echo e($tipo['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Selecione o tipo de ausência.</div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Data início *</label>
                            <input type="date" name="data_inicio" class="form-control" required>
                            <div class="invalid-feedback">Indique a data início.</div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Data fim *</label>
                            <input type="date" name="data_fim" class="form-control" required>
                            <div class="invalid-feedback">Indique a data fim.</div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Motivo *</label>
                            <textarea name="motivo" class="form-control" rows="4" required></textarea>
                            <div class="invalid-feedback">Indique o motivo.</div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Anexo</label>
                            <input type="file" name="anexo" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="form-text text-muted">PDF, JPG ou PNG até 5 MB.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Submeter pedido</button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($temPermissaoAprovar): ?>
        <?php foreach ($pedidos as $pedido): ?>
            <?php if ($pedido['estado'] !== 'pendente') {
                continue;
            } ?>
            <div class="modal fade" id="modalAprovarPedido<?php echo (int) $pedido['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form method="post" class="modal-content">
                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="acao" value="aprovar">
                        <input type="hidden" name="id" value="<?php echo (int) $pedido['id']; ?>">
                        <div class="modal-header border-0">
                            <h5 class="modal-title">Aprovar pedido</h5>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>Confirmar aprovação do pedido de <strong><?php echo e($pedido['utilizador_nome']); ?></strong>?</p>
                            <?php if (!empty($pedido['observacoes_aprovacao'])): ?>
                                <div class="alert alert-info"><strong>Notas do pedido:</strong><br><pre style="white-space:pre-wrap;"><?php echo e($pedido['observacoes_aprovacao']); ?></pre></div>
                            <?php endif; ?>
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes_aprovacao" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="submit" class="btn btn-success">Aprovar</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal fade" id="modalRecusarPedido<?php echo (int) $pedido['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form method="post" class="modal-content">
                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="acao" value="recusar">
                        <input type="hidden" name="id" value="<?php echo (int) $pedido['id']; ?>">
                        <div class="modal-header border-0">
                            <h5 class="modal-title">Recusar pedido</h5>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>Confirmar recusa do pedido de <strong><?php echo e($pedido['utilizador_nome']); ?></strong>?</p>
                            <label class="form-label">Motivo da recusa</label>
                            <textarea name="observacoes_aprovacao" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="submit" class="btn btn-danger">Recusar</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php include 'includes/scripts.php'; ?>
    <script>
        $(document).ready(function () {
            $('#tabela-ausencias').DataTable({
                pageLength: 10,
                order: [[<?php echo $temPermissaoAprovar ? 2 : 1; ?>, 'desc']],
                language: {
                    search: 'Pesquisar:',
                    lengthMenu: 'Mostrar _MENU_ registos',
                    info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
                    infoEmpty: 'Sem registos',
                    zeroRecords: 'Nenhum pedido encontrado',
                    paginate: {
                        first: 'Primeiro',
                        last: 'Último',
                        next: 'Seguinte',
                        previous: 'Anterior'
                    }
                }
            });

            $('.needs-validation').on('submit', function (event) {
                if (!this.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                $(this).addClass('was-validated');
            });

            // month selector: limit months to current month when year is current
            function updateMonthLimits() {
                var year = parseInt($('#rel_year').val(), 10);
                var curYear = new Date().getFullYear();
                var curMonth = new Date().getMonth() + 1;
                if (year === curYear) {
                    $('#rel_month option').each(function() {
                        var m = parseInt($(this).val(), 10);
                        if (m > curMonth) $(this).prop('disabled', true);
                        else $(this).prop('disabled', false);
                    });
                } else {
                    $('#rel_month option').prop('disabled', false);
                }
            }
            $('#rel_year').on('change', updateMonthLimits);
            updateMonthLimits();
        });
    </script>
</body>

</html>


