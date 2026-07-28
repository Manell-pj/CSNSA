<?php

require_once __DIR__ . '/calcular_resumo_diario_assiduidade.php';

function escala_mensal_e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function escala_mensal_redirect($type, $message, $params = [])
{
    $params = array_merge($params, [
        'type' => $type,
        'message' => $message,
    ]);

    header('Location: escala_mensal.php?' . http_build_query($params));
    exit;
}
 
function escala_mensal_table_exists($conn, $table)
{
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    mysqli_stmt_bind_param($stmt, 's', $table);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function escala_mensal_column_exists($conn, $table, $column)
{
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function escala_mensal_month_name($month)
{
    $months = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
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

    return $months[(int) $month] ?? '';
}

function escala_mensal_weekday_short($date)
{
    $weekdays = [
        1 => 'Seg',
        2 => 'Ter',
        3 => 'Qua',
        4 => 'Qui',
        5 => 'Sex',
        6 => 'Sab',
        7 => 'Dom',
    ];

    return $weekdays[(int) date('N', strtotime($date))] ?? '';
}

function escala_mensal_tipo_label($tipo)
{
    $labels = [
        'turno' => 'Turno',
        'folga' => 'Folga',
        'ferias' => 'Férias',
        'falta' => 'Falta',
        'baixa' => 'Baixa',
        'substituicao' => 'Substituição',
        'licenca_amamentacao' => 'Lic. amamentação',
    ];

    return $labels[$tipo] ?? $tipo;
}

if (!function_exists('e')) {
    function e($value)
    {
        return escala_mensal_e($value);
    }
}

if (!function_exists('month_name')) {
    function month_name($month)
    {
        return escala_mensal_month_name($month);
    }
}

if (!function_exists('weekday_short')) {
    function weekday_short($date)
    {
        return escala_mensal_weekday_short($date);
    }
}

if (!function_exists('tipo_label')) {
    function tipo_label($tipo)
    {
        return escala_mensal_tipo_label($tipo);
    }
}

function escala_mensal_tipos_dia()
{
    return ['turno', 'folga', 'ferias', 'falta', 'baixa', 'substituicao', 'licenca_amamentacao'];
}

function escala_mensal_contexto_request()
{
    $anoAtual = (int) date('Y');
    $mesAtual = (int) date('n');
    $ano = (int) ($_REQUEST['ano'] ?? $anoAtual);
    $mes = (int) ($_REQUEST['mes'] ?? $mesAtual);
    $equipaId = (int) ($_REQUEST['equipa_id'] ?? 0);
    $setorId = (int) ($_REQUEST['setor_id'] ?? 0);

    if ($ano < 2000 || $ano > 2100) {
        $ano = $anoAtual;
    }

    if ($mes < 1 || $mes > 12) {
        $mes = $mesAtual;
    }

    return [
        'ano' => $ano,
        'mes' => $mes,
        'setor_id' => $setorId,
        'equipa_id' => $equipaId,
        'dias_no_mes' => cal_days_in_month(CAL_GREGORIAN, $mes, $ano),
    ];
}

function escala_mensal_base_params($contexto)
{
    return [
        'ano' => $contexto['ano'],
        'mes' => $contexto['mes'],
        'equipa_id' => $contexto['equipa_id'],
        'setor_id' => $contexto['setor_id'] ?? 0,
    ];
}

function escala_mensal_tabelas_em_falta($conn)
{
    $requiredTables = ['funcionarios', 'equipas', 'turnos', 'escala_funcionarios'];
    $missingTables = [];

    foreach ($requiredTables as $table) {
        if (!escala_mensal_table_exists($conn, $table)) {
            $missingTables[] = $table;
        }
    }

    return $missingTables;
}

function escala_mensal_adicionar_recalculo(&$recalculos, $contexto, $funcionarioId, $dia)
{
    if ((int) $funcionarioId <= 0 || (int) $dia < 1 || (int) $dia > $contexto['dias_no_mes']) {
        return;
    }

    $data = sprintf('%04d-%02d-%02d', $contexto['ano'], $contexto['mes'], (int) $dia);
    $recalculos[$data] = [
        'data' => $data,
    ];
}

function escala_mensal_recalcular_resumos($conn, $recalculos)
{
    if (!function_exists('calcular_resumo_diario_assiduidade') || empty($recalculos)) {
        return 0;
    }

    $erros = 0;

    foreach ($recalculos as $recalculo) {
        try {
            $resultado = calcular_resumo_diario_assiduidade($conn, $recalculo['data']);
            if (!empty($resultado['erros'])) {
                $erros++;
            }
        } catch (Throwable $e) {
            $erros++;
        }
    }

    return $erros;
}

function escala_mensal_processar_post($conn, $contexto, $missingTables)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $acaoPost = $_POST['acao'] ?? '';

    $baseParams = escala_mensal_base_params($contexto);

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        escala_mensal_redirect('danger', 'Token CSRF inválido.', $baseParams);
    }

    if (!empty($missingTables)) {
        escala_mensal_redirect('danger', 'Execute primeiro a migration SQL da adaptação para lar de idosos.', $baseParams);
    }

    if ($acaoPost === 'guardar') {
        $escala = $_POST['escala'] ?? [];
        $recalculos = [];

        if (!is_array($escala)) {
            escala_mensal_redirect('danger', 'Dados da escala inválidos.', $baseParams);
        }

        mysqli_begin_transaction($conn);

        try {
            $stmtFuncionario = mysqli_prepare($conn, 'SELECT id, utilizador_id, equipa_id FROM funcionarios WHERE id = ? AND estado = "ativo" LIMIT 1');
            $stmtGuardar = mysqli_prepare($conn, "INSERT INTO escala_funcionarios
                (funcionario_id, utilizador_id, setor_id, equipa_id, ano, mes, data_escala, dia, tipo_dia, turno_id, substitui_funcionario_id, folga_trabalhada, observacoes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    utilizador_id = VALUES(utilizador_id),
                    setor_id = VALUES(setor_id),
                    equipa_id = VALUES(equipa_id),
                    ano = VALUES(ano),
                    mes = VALUES(mes),
                    dia = VALUES(dia),
                    tipo_dia = VALUES(tipo_dia),
                    turno_id = VALUES(turno_id),
                    substitui_funcionario_id = VALUES(substitui_funcionario_id),
                    folga_trabalhada = VALUES(folga_trabalhada),
                    observacoes = VALUES(observacoes)");

            foreach ($escala as $funcionarioId => $dias) {
                escala_mensal_guardar_funcionario($conn, $stmtFuncionario, $stmtGuardar, $contexto, (int) $funcionarioId, $dias, $recalculos);
            }

            mysqli_stmt_close($stmtFuncionario);
            mysqli_stmt_close($stmtGuardar);
            mysqli_commit($conn);

            $errosRecalculo = escala_mensal_recalcular_resumos($conn, $recalculos);
            $mensagem = 'Escala mensal guardada com sucesso.';
            if ($errosRecalculo > 0) {
                $mensagem .= ' Alguns resumos serão atualizados posteriormente.';
            }

            escala_mensal_redirect('success', $mensagem, $baseParams);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            escala_mensal_redirect('danger', 'Não foi possível guardar a escala mensal.', $baseParams);
        }
    }

    if ($acaoPost === 'bulk_assign') {
        $funcionarioIds = $_POST['funcionario_ids'] ?? [];
        $recalculos = [];
        if (is_string($funcionarioIds) && $funcionarioIds !== '') {
            $funcionarioIds = array_filter(array_map('intval', explode(',', $funcionarioIds)));
        }
        $dias = [];
        $diaInicio = isset($_POST['dia_inicio']) ? (int)$_POST['dia_inicio'] : 1;
        $diaFim = isset($_POST['dia_fim']) ? (int)$_POST['dia_fim'] : $contexto['dias_no_mes'];
        $diaInicio = max(1, $diaInicio);
        $diaFim = min($contexto['dias_no_mes'], $diaFim);
        if ($diaInicio <= $diaFim) {
            for ($d = $diaInicio; $d <= $diaFim; $d++) $dias[] = $d;
        }
        $turnoId = isset($_POST['turno_id']) && (int)$_POST['turno_id'] > 0 ? (int)$_POST['turno_id'] : null;
        $tipoDia = $_POST['tipo_dia'] ?? 'turno';

        if (!is_array($funcionarioIds) || empty($funcionarioIds) || !is_array($dias) || empty($dias)) {
            escala_mensal_redirect('danger', 'Parâmetros inválidos para atribuição em massa.', $baseParams);
        }

        mysqli_begin_transaction($conn);
        try {
            $stmtFuncionario = mysqli_prepare($conn, 'SELECT id, utilizador_id, equipa_id FROM funcionarios WHERE id = ? AND estado = "ativo" LIMIT 1');
            $stmtGuardar = mysqli_prepare($conn, "INSERT INTO escala_funcionarios
                (funcionario_id, utilizador_id, setor_id, equipa_id, ano, mes, data_escala, dia, tipo_dia, turno_id, substitui_funcionario_id, folga_trabalhada, observacoes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    utilizador_id = VALUES(utilizador_id),
                    setor_id = VALUES(setor_id),
                    equipa_id = VALUES(equipa_id),
                    ano = VALUES(ano),
                    mes = VALUES(mes),
                    dia = VALUES(dia),
                    tipo_dia = VALUES(tipo_dia),
                    turno_id = VALUES(turno_id),
                    substitui_funcionario_id = VALUES(substitui_funcionario_id),
                    folga_trabalhada = VALUES(folga_trabalhada),
                    observacoes = VALUES(observacoes)");

            foreach ($funcionarioIds as $fid) {
                $fid = (int) $fid;
                if ($fid <= 0) continue;
                mysqli_stmt_bind_param($stmtFuncionario, 'i', $fid);
                mysqli_stmt_execute($stmtFuncionario);
                $res = mysqli_stmt_get_result($stmtFuncionario);
                $func = mysqli_fetch_assoc($res);
                if (!$func) continue;

                foreach ($dias as $dia) {
                    $dia = (int) $dia;
                    if ($dia < 1 || $dia > $contexto['dias_no_mes']) continue;
                    $dadosDia = [
                        'tipo_dia' => $tipoDia,
                        'turno_id' => $turnoId,
                    ];
                    escala_mensal_guardar_dia($stmtGuardar, $contexto, $fid, $func, $dia, $dadosDia);
                    escala_mensal_adicionar_recalculo($recalculos, $contexto, $fid, $dia);
                }
            }

            mysqli_stmt_close($stmtFuncionario);
            mysqli_stmt_close($stmtGuardar);
            mysqli_commit($conn);

            $errosRecalculo = escala_mensal_recalcular_resumos($conn, $recalculos);
            $mensagem = 'Atribuição em massa aplicada com sucesso.';
            if ($errosRecalculo > 0) {
                $mensagem .= ' Alguns resumos serão atualizados posteriormente.';
            }

            escala_mensal_redirect('success', $mensagem, $baseParams);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            escala_mensal_redirect('danger', 'Falha na atribuição em massa.', $baseParams);
        }
    }
}

function escala_mensal_guardar_funcionario($conn, $stmtFuncionario, $stmtGuardar, $contexto, $funcionarioId, $dias, &$recalculos = [])
{
    if ($funcionarioId <= 0 || !is_array($dias)) {
        return;
    }

    mysqli_stmt_bind_param($stmtFuncionario, 'i', $funcionarioId);
    mysqli_stmt_execute($stmtFuncionario);
    $resultFuncionario = mysqli_stmt_get_result($stmtFuncionario);
    $funcionario = mysqli_fetch_assoc($resultFuncionario);

    if (!$funcionario) {
        return;
    }

    foreach ($dias as $dia => $dadosDia) {
        escala_mensal_guardar_dia($stmtGuardar, $contexto, $funcionarioId, $funcionario, (int) $dia, $dadosDia);
        escala_mensal_adicionar_recalculo($recalculos, $contexto, $funcionarioId, (int) $dia);
    }
}

function escala_mensal_guardar_dia($stmtGuardar, $contexto, $funcionarioId, $funcionario, $dia, $dadosDia)
{
    if ($dia < 1 || $dia > $contexto['dias_no_mes'] || !is_array($dadosDia)) {
        return;
    }

    $tiposDia = escala_mensal_tipos_dia();
    $tipoDia = $dadosDia['tipo_dia'] ?? 'turno';

    if (!in_array($tipoDia, $tiposDia, true)) {
        $tipoDia = 'turno';
    }

    $turnoId = isset($dadosDia['turno_id']) && (int) $dadosDia['turno_id'] > 0 ? (int) $dadosDia['turno_id'] : null;
    $substituiFuncionarioId = isset($dadosDia['substitui_funcionario_id']) && (int) $dadosDia['substitui_funcionario_id'] > 0 ? (int) $dadosDia['substitui_funcionario_id'] : null;
    $folgaTrabalhada = isset($dadosDia['folga_trabalhada']) ? 1 : 0;
    $observacoes = trim($dadosDia['observacoes'] ?? '');
    $observacoes = $observacoes === '' ? null : $observacoes;
    $dataEscala = sprintf('%04d-%02d-%02d', $contexto['ano'], $contexto['mes'], $dia);
    $utilizadorId = $funcionario['utilizador_id'] === null ? null : (int) $funcionario['utilizador_id'];
    $setorFuncionarioId = null;
    $equipaFuncionarioId = $funcionario['equipa_id'] === null ? null : (int) $funcionario['equipa_id'];

    if ($tipoDia !== 'turno' && $tipoDia !== 'substituicao' && $folgaTrabalhada === 0) {
        $turnoId = null;
    }

    if ($tipoDia !== 'substituicao') {
        $substituiFuncionarioId = null;
    }

    mysqli_stmt_bind_param(
        $stmtGuardar,
        'iiiiiisisiiis',
        $funcionarioId,
        $utilizadorId,
        $setorFuncionarioId,
        $equipaFuncionarioId,
        $contexto['ano'],
        $contexto['mes'],
        $dataEscala,
        $dia,
        $tipoDia,
        $turnoId,
        $substituiFuncionarioId,
        $folgaTrabalhada,
        $observacoes
    );
    mysqli_stmt_execute($stmtGuardar);
}

function escala_mensal_carregar_dados($conn, $contexto, $missingTables)
{
    $dados = [
        'equipas' => [],
        'turnos' => [],
        'funcionarios' => [],
        'escala_guardada' => [],
    ];

    if (!empty($missingTables)) {
        return $dados;
    }

    $dados['equipas'] = escala_mensal_carregar_equipas($conn);
    $dados['turnos'] = escala_mensal_carregar_turnos($conn);
    $dados['funcionarios'] = escala_mensal_carregar_funcionarios($conn, $contexto['equipa_id'], $contexto['setor_id'] ?? 0);
    $dados['escala_guardada'] = escala_mensal_carregar_escala_guardada($conn, $contexto['ano'], $contexto['mes']);
    $dados['setores'] = escala_mensal_carregar_setores($conn);

    return $dados;
}

function escala_mensal_carregar_equipas($conn)
{
    $rows = [];
    $stmt = mysqli_prepare($conn, 'SELECT id, nome FROM equipas WHERE ativo = 1 ORDER BY nome ASC');

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);
    return $rows;
}

function escala_mensal_carregar_setores($conn)
{
    $rows = [];
    if (!escala_mensal_table_exists($conn, 'setores')) {
        return $rows;
    }

    $stmt = mysqli_prepare($conn, 'SELECT id, nome FROM setores WHERE ativo = 1 ORDER BY nome ASC');
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function escala_mensal_carregar_turnos($conn)
{
    $rows = [];
    $stmt = mysqli_prepare($conn, 'SELECT id, nome, codigo, hora_entrada, hora_saida FROM turnos WHERE ativo = 1 ORDER BY hora_entrada ASC, nome ASC');
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);
    return $rows;
}

function escala_mensal_carregar_funcionarios($conn, $equipaId, $setorId = 0)
{
    $rows = [];
    $temSetorFuncionarios = escala_mensal_column_exists($conn, 'funcionarios', 'setor_id');
    $temSetorEquipas = escala_mensal_column_exists($conn, 'equipas', 'setor_id');
    if ($temSetorFuncionarios && $temSetorEquipas) {
        $selectSetorId = 'COALESCE(f.setor_id, e.setor_id)';
    } elseif ($temSetorFuncionarios) {
        $selectSetorId = 'f.setor_id';
    } elseif ($temSetorEquipas) {
        $selectSetorId = 'e.setor_id';
    } else {
        $selectSetorId = 'NULL';
    }
    $joinSetores = ($temSetorFuncionarios || $temSetorEquipas) && escala_mensal_table_exists($conn, 'setores');
    $sql = "SELECT f.id, f.utilizador_id, f.nome, f.numero_mecanografico, f.funcao, f.equipa_id,
                   e.nome AS equipa_nome, f.data_nascimento,
                   {$selectSetorId} AS setor_id,
                   " . ($joinSetores ? 's.nome' : 'NULL') . " AS setor_nome
            FROM funcionarios f
            LEFT JOIN equipas e ON e.id = f.equipa_id" .
            ($joinSetores ? " LEFT JOIN setores s ON s.id = {$selectSetorId}" : '') . "
            WHERE f.estado = 'ativo'";

    if ($equipaId > 0) {
        $sql .= ' AND f.equipa_id = ?';
    }

    if ($setorId > 0 && ($temSetorFuncionarios || $temSetorEquipas)) {
        $sql .= ' AND ' . $selectSetorId . ' = ?';
    }

    $sql .= ' ORDER BY setor_nome ASC, e.nome ASC, f.nome ASC';
    $stmt = mysqli_prepare($conn, $sql);

    if ($equipaId > 0 && $setorId > 0 && ($temSetorFuncionarios || $temSetorEquipas)) {
        mysqli_stmt_bind_param($stmt, 'ii', $equipaId, $setorId);
    } elseif ($equipaId > 0) {
        mysqli_stmt_bind_param($stmt, 'i', $equipaId);
    } elseif ($setorId > 0 && ($temSetorFuncionarios || $temSetorEquipas)) {
        mysqli_stmt_bind_param($stmt, 'i', $setorId);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);
    return $rows;
}

function escala_mensal_carregar_escala_guardada($conn, $ano, $mes)
{
    $escala = [];
    $stmt = mysqli_prepare($conn, 'SELECT * FROM escala_funcionarios WHERE ano = ? AND mes = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $ano, $mes);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $escala[(int) $row['funcionario_id']][(int) $row['dia']] = $row;
    }

    mysqli_stmt_close($stmt);
    // carregar períodos/segundos turnos se existir tabela
    if (escala_mensal_table_exists($conn, 'escala_periodos')) {
        $stmt2 = mysqli_prepare($conn, 'SELECT * FROM escala_periodos WHERE YEAR(data_escala) = ? AND MONTH(data_escala) = ?');
        mysqli_stmt_bind_param($stmt2, 'ii', $ano, $mes);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);
        while ($r = mysqli_fetch_assoc($res2)) {
            $fid = (int) $r['funcionario_id'];
            $day = (int) date('j', strtotime($r['data_escala']));
            if (!isset($escala[$fid][$day]['periodos'])) {
                $escala[$fid][$day]['periodos'] = [];
            }
            $escala[$fid][$day]['periodos'][] = $r;
        }
        mysqli_stmt_close($stmt2);
    }
    return $escala;
}

function escala_mensal_minutos_entre_horas($entrada, $saida)
{
    if (empty($entrada) || empty($saida)) return 0;
    $fmtE = DateTime::createFromFormat('H:i', $entrada);
    $fmtS = DateTime::createFromFormat('H:i', $saida);
    if (!$fmtE || !$fmtS) return 0;
    if ($fmtS <= $fmtE) {
        $fmtS->modify('+1 day');
    }
    return (int) (($fmtS->getTimestamp() - $fmtE->getTimestamp()) / 60);
}

