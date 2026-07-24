<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function notificacoes_redirect($type, $message)
{
    header('Location: notificacoes.php?' . http_build_query([
        'type' => $type,
        'message' => $message,
    ]));
    exit;
}

function nt_format_date_pt($date)
{
    return $date ? date('d/m/Y', strtotime($date)) : '-';
}

function nt_age_at_event($birthDate, $eventDate)
{
    if (!$birthDate || !$eventDate) {
        return null;
    }

    return (new DateTimeImmutable($birthDate))->diff(new DateTimeImmutable($eventDate))->y;
}
