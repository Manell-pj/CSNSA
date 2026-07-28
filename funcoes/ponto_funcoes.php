<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_with_message($type, $message, $params = [])
{
    $params = array_merge($params, [
        'type' => $type,
        'message' => $message,
    ]);

    header('Location: ponto.php?' . http_build_query($params));
    exit;
}

function movimento_label($tipo)
{
    $labels = [
        'entrada' => 'Entrada',
        'entrada_segundo_turno' => 'Entrada (2.º turno)',
        'saida' => 'Saída',
        'saida_segundo_turno' => 'Saída (2.º turno)',
        'inicio_pausa' => 'Início de pausa',
        'fim_pausa' => 'Fim de pausa',
    ];

    return $labels[$tipo] ?? $tipo;
}

function movimento_badge($tipo)
{
    $classes = [
        'entrada' => 'success',
        'entrada_segundo_turno' => 'success',
        'saida' => 'danger',
        'saida_segundo_turno' => 'danger',
        'inicio_pausa' => 'warning',
        'fim_pausa' => 'info',
    ];

    return $classes[$tipo] ?? 'secondary';
}

function ponto_atualizar_resumo_assiduidade($conn, $dataReferencia, $funcionarioId)
{
    if (!function_exists('calcular_resumo_diario_assiduidade') || empty($dataReferencia) || (int) $funcionarioId <= 0) {
        return false;
    }

    try {
        $resultado = calcular_resumo_diario_assiduidade($conn, $dataReferencia, (int) $funcionarioId);

        return empty($resultado['erros']);
    } catch (Throwable $e) {
        return false;
    }
}

function ponto_validar_movimento_cronologico($conn, $funcionarioId, $tipo, $dataHoraSql)
{
    $tiposSaida = ['saida', 'saida_segundo_turno'];
    $tiposEntrada = ['entrada', 'entrada_segundo_turno'];
    $tiposSequenciais = array_merge($tiposEntrada, $tiposSaida);

    if (ponto_existe_movimento_mesma_hora($conn, $funcionarioId, $dataHoraSql)) {
        redirect_with_message('danger', 'Já existe um movimento registado para este funcionário à mesma hora.');
    }

    $anterior = ponto_obter_movimento_adjacente($conn, $funcionarioId, $dataHoraSql, 'anterior');
    $seguinte = ponto_obter_movimento_adjacente($conn, $funcionarioId, $dataHoraSql, 'seguinte');

    foreach ([$anterior, $seguinte] as $movimento) {
        if ($movimento && $movimento['tipo'] === $tipo && in_array($tipo, $tiposSequenciais, true)) {
            redirect_with_message('danger', 'Movimento inválido: movimento igual ao movimento adjacente.');
        }
    }

    if (in_array($tipo, $tiposSaida, true) && (!$anterior || !in_array($anterior['tipo'], $tiposEntrada, true))) {
        redirect_with_message('danger', 'Saída inválida: não existe entrada anterior registada.');
    }

    if (in_array($tipo, $tiposEntrada, true) && $seguinte && in_array($seguinte['tipo'], $tiposEntrada, true)) {
        redirect_with_message('danger', 'Entrada inválida: já existe entrada antes da próxima saída.');
    }
}

function ponto_obter_movimento_adjacente($conn, $funcionarioId, $dataHoraSql, $direcao)
{
    if ($direcao === 'anterior') {
        $operador = '<';
        $ordem = 'DESC';
    } else {
        $operador = '>';
        $ordem = 'ASC';
    }

    $stmt = mysqli_prepare($conn, "SELECT tipo, data_hora FROM registos_ponto WHERE funcionario_id = ? AND data_hora $operador ? ORDER BY data_hora $ordem, id $ordem LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'is', $funcionarioId, $dataHoraSql);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $movimento = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $movimento ?: null;
}

function ponto_existe_movimento_mesma_hora($conn, $funcionarioId, $dataHoraSql, $ignorarRegistoId = null)
{
    $sql = 'SELECT id FROM registos_ponto WHERE funcionario_id = ? AND data_hora = ?';
    $params = [(int) $funcionarioId, $dataHoraSql];
    $types = 'is';

    if ($ignorarRegistoId !== null && (int) $ignorarRegistoId > 0) {
        $sql .= ' AND id <> ?';
        $params[] = (int) $ignorarRegistoId;
        $types .= 'i';
    }

    $sql .= ' LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $existe = (bool) mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $existe;
}
