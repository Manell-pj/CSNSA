<?php

require_once 'resposta.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Rats\Zkteco\Lib\ZKTeco;

function ligarTerminal()
{
    $terminal = new ZKTeco(
        getenv('BIOMETRIC_TERMINAL_HOST') ?: '10.42.190.177',
        (int) (getenv('BIOMETRIC_TERMINAL_PORT') ?: 4370)
    );

    if (!$terminal->connect()) {
        responder(
            false,
            "Não foi possível ligar ao terminal."
        );
    }

    return $terminal;
}

function criarFuncionarioTerminal($terminal, $uid, $nome, $password, $userid = null)
{
    try {

        $terminal->disableDevice();

        $nome = trim($nome);
        $nome = iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $nome
        );

        $terminal->setUser(
            (int)$uid,      // UID interno do terminal
            (string)($userid ?: $uid),   // USER ID numerico devolvido nas picagens
            $nome,          // Nome
            (string)$password, // Password/PIN
            0,              // Privilégio (0 = utilizador normal)
            0               // Card Number
        );

        $terminal->enableDevice();

        return [
            "success" => true,
            "message" => "Funcionário criado com sucesso."
        ];

    } catch (Throwable $e) {

        try {
            $terminal->enableDevice();
        } catch (Throwable $ignore) {
        }

        return [
            "success" => false,
            "message" => $e->getMessage()
        ];
    }
}
