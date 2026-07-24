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
    'logo' => 'assets/img/csnsa/logo-nsa.png',
    'logo_vertical_light' => 'assets/img/csnsa/logo-nsa-vertical-light.png',
    'logo_vertical_dark' => 'assets/img/csnsa/logo-nsa-vertical-dark.png',
    'logo_light' => 'assets/img/csnsa/logo-nsa-horizontal-light.png',
    'logo_dark' => 'assets/img/csnsa/logo-nsa-horizontal-dark.png',
    'favicon' => 'assets/img/csnsa/favicon-nsa.png',
];
?>

