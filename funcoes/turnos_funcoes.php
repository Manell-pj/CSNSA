<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_with_message($type, $message)
{
    header('Location: turnos.php?' . http_build_query([
        'type' => $type,
        'message' => $message,
    ]));
    exit;
}

function get_post_value($key)
{
    return trim($_POST[$key] ?? '');
}

function nullable_time($value)
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function nullable_int($value)
{
    return $value === '' ? null : (int) $value;
}

function hora_valida($hora)
{
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora) === 1;
}

function hora_para_minutos($hora)
{
    if (!hora_valida($hora)) {
        return null;
    }

    [$h, $m] = explode(':', $hora);
    return ((int) $h) * 60 + ((int) $m);
}

function periodos_para_intervalos(array $periodos)
{
    $intervalos = [];

    foreach ($periodos as $idx => $periodo) {
        $inicio = hora_para_minutos($periodo['inicio']);
        $fim = hora_para_minutos($periodo['fim']);

        if ($inicio === null || $fim === null) {
            continue;
        }

        if ($fim > $inicio) {
            $intervalos[] = ['start' => $inicio, 'end' => $fim, 'orig' => $idx];
            $intervalos[] = ['start' => $inicio + 1440, 'end' => $fim + 1440, 'orig' => $idx];
        } else {
            $intervalos[] = ['start' => $inicio, 'end' => $fim + 1440, 'orig' => $idx];
        }
    }

    return $intervalos;
}

function sobreposicao_periodos(array $periodos)
{
    $intervalos = periodos_para_intervalos($periodos);

    foreach ($intervalos as $i => $primeiro) {
        foreach ($intervalos as $j => $segundo) {
            if ($i === $j || $primeiro['orig'] === $segundo['orig']) {
                continue;
            }

            if ($primeiro['start'] < $segundo['end'] && $segundo['start'] < $primeiro['end']) {
                return true;
            }
        }
    }

    return false;
}

function limpar_periodos_post(array $inicios, array $fims)
{
    $periodos = [];

    foreach ($inicios as $indice => $inicio) {
        $inicio = trim((string) $inicio);
        $fim = trim((string) ($fims[$indice] ?? ''));

        if ($inicio === '' && $fim === '') {
            continue;
        }

        $periodos[] = [
            'inicio' => $inicio,
            'fim' => $fim,
        ];
    }

    return $periodos;
}

function validar_periodos(array $periodos, &$erro = null)
{
    if (empty($periodos)) {
        $erro = 'Adicione pelo menos um período de turno.';
        return false;
    }

    foreach ($periodos as $periodo) {
        if (!hora_valida($periodo['inicio']) || !hora_valida($periodo['fim'])) {
            $erro = 'Cada período deve ter início e fim válidos no formato HH:MM.';
            return false;
        }

        if ($periodo['inicio'] === $periodo['fim']) {
            $erro = 'O início e a saída de um período não podem ser iguais.';
            return false;
        }
    }

    if (sobreposicao_periodos($periodos)) {
        $erro = 'Os períodos não podem sobrepor-se.';
        return false;
    }

    return true;
}

function periodo_resumo(array $periodos)
{
    if (empty($periodos)) {
        return '-';
    }

    $resumos = [];
    foreach ($periodos as $periodo) {
        $inicio = $periodo['inicio'] ?? $periodo['hora_inicio'] ?? '';
        $fim = $periodo['fim'] ?? $periodo['hora_fim'] ?? '';
        $resumos[] = sprintf('%s–%s', $inicio, $fim);
    }

    return implode(', ', $resumos);
}

function excluir_periodos_turno($conn, $turnoId)
{
    if (!$turnoId || !fe_table_exists($conn, 'turno_periodos')) {
        return;
    }

    $stmt = mysqli_prepare($conn, 'DELETE FROM turno_periodos WHERE turno_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $turnoId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function salvar_periodos_turno($conn, $turnoId, array $periodos, int $toleranciaAtraso, int $toleranciaSaida, float $horasPrevistas)
{
    if (!$turnoId || empty($periodos) || !fe_table_exists($conn, 'turno_periodos')) {
        return;
    }

    $stmt = mysqli_prepare($conn, 'INSERT INTO turno_periodos (turno_id, sequencia, hora_inicio, hora_fim, cruza_dia, tolerancia_antes_min, tolerancia_depois_min, minutos_previstos) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

    foreach ($periodos as $indice => $periodo) {
        $sequencia = $indice + 1;
        $inicioMinutos = hora_para_minutos($periodo['inicio']);
        $fimMinutos = hora_para_minutos($periodo['fim']);
        $cruzaDia = $fimMinutos <= $inicioMinutos ? 1 : 0;
        $minutosPrevistos = $fimMinutos > $inicioMinutos
            ? $fimMinutos - $inicioMinutos
            : ($fimMinutos + 1440) - $inicioMinutos;

        mysqli_stmt_bind_param(
            $stmt,
            'iissiiii',
            $turnoId,
            $sequencia,
            $periodo['inicio'],
            $periodo['fim'],
            $cruzaDia,
            $toleranciaAtraso,
            $toleranciaSaida,
            $minutosPrevistos
        );
        mysqli_stmt_execute($stmt);
    }

    mysqli_stmt_close($stmt);
}

function dia_semana_nome($dia)
{
    $dias = [
        1 => 'Segunda-feira',
        2 => 'Terca-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sabado',
        7 => 'Domingo',
    ];

    return $dias[(int) $dia] ?? 'Todos os dias';
}

function obter_periodos_turno($conn, $turnoId)
{
    if (!$turnoId || !fe_table_exists($conn, 'turno_periodos')) {
        return [];
    }

    $periodos = [];
    $stmt = mysqli_prepare($conn, 'SELECT id, sequencia, hora_inicio, hora_fim, cruza_dia FROM turno_periodos WHERE turno_id = ? AND ativo = 1 ORDER BY sequencia ASC');
    mysqli_stmt_bind_param($stmt, 'i', $turnoId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $periodos[] = $row;
    }

    mysqli_stmt_close($stmt);
    return $periodos;
}
