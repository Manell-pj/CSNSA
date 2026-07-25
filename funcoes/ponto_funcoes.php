<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_with_message($type, $message)
{
    header('Location: ponto.php?' . http_build_query([
        'type' => $type,
        'message' => $message,
    ]));
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
