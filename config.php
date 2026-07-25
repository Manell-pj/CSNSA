
<?php
mysqli_report(MYSQLI_REPORT_OFF);

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'u130245564.csnsa';
$password = getenv('DB_PASSWORD') ?: 'Csnsa2026#';
$database = getenv('DB_NAME') ?: 'u130245564_csnsa';

$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    require $localConfig;
}

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'gestor_assiduidade';

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die('Erro na ligacao a base de dados: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');
?>
