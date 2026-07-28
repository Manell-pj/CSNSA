<?php

require_once 'terminal.php';
require_once 'resposta.php';

$dados = json_decode(file_get_contents('php://input'), true);

if (!is_array($dados)) {
    responder(false, 'JSON invalido.');
}

if (
    !isset($dados['uid']) ||
    !isset($dados['nome']) ||
    !isset($dados['password'])
) {
    responder(false, 'Campos obrigatorios em falta.');
}

$userid = $dados['userid'] ?? $dados['uid'];
if (!preg_match('/^\d{1,9}$/', (string) $userid)) {
    responder(false, 'O USER ID biometrico deve ter ate 9 algarismos.');
}

$terminal = ligarTerminal();

$resultado = criarFuncionarioTerminal(
    $terminal,
    $dados['uid'],
    $dados['nome'],
    $dados['password'],
    $userid
);

$terminal->disconnect();

if (!$resultado['success']) {
    responder(false, $resultado['message']);
}

responder(true, 'Funcionario criado com sucesso no terminal.');
