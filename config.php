<?php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'gestor_assiduidade';

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die('Erro na ligação à base de dados: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

$appConfig = [
    'app_name' => 'CSNSA - Gestor de Assiduidade',
    'app_title' => 'Gestor de Assiduidade',
    'company_name' => 'Centro Social Nossa Senhora Auxiliadora',
    'logo_light' => 'assets/img/kaiadmin/logo_light.svg',
    'logo_dark' => 'assets/img/kaiadmin/logo_dark.svg',
];
?>

