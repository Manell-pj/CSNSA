<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function permissoes_redirect($type, $message)
{
    header('Location: permissoes.php?' . http_build_query(['type' => $type, 'message' => $message]));
    exit;
}
