<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$page = $argv[1] ?? '';
$allowedPages = [
    'dashboard.php',
    'funcionarios.php',
    'departamentos.php',
    'ponto.php',
    'ausencias.php',
    'notificacoes.php',
    'turnos.php',
    'escala_mensal.php',
    'banco_horas.php',
    'relatorios_horas.php',
    'utilizadores.php',
    'permissoes.php',
    'login.php',
    'primeiro_utilizador.php',
    'verificar_ponto.php',
];

if (!in_array($page, $allowedPages, true)) {
    fwrite(STDERR, "Pagina nao permitida: {$page}\n");
    exit(2);
}

chdir(dirname(__DIR__));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION['utilizador_id'] = 1;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/' . $page;
$_SERVER['SCRIPT_NAME'] = '/' . $page;
$_SERVER['PHP_SELF'] = '/' . $page;
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$_GET = [];
$_POST = [];

ob_start();
require dirname(__DIR__) . DIRECTORY_SEPARATOR . $page;
ob_end_clean();

echo "OK {$page}\n";
