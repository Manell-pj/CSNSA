<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function dashboard_idade_evento($birthDate, $eventDate)
{
    if (!$birthDate || !$eventDate) {
        return null;
    }

    return (new DateTimeImmutable($birthDate))->diff(new DateTimeImmutable($eventDate))->y;
}
