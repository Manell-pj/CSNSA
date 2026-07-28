<?php

function responder($success, $message, $dados = [])
{
    echo json_encode([
        "success" => $success,
        "message" => $message,
        "dados" => $dados
    ]);

    exit;
}