<<<<<<< HEAD
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
=======
﻿<?php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'gestor_assiduidade';
>>>>>>> 989be8b6888fc6188a374134f79a4b4a1ff25c65

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
<<<<<<< HEAD
    error_log('Erro na ligacao a base de dados: ' . mysqli_connect_error());
    http_response_code(500);
    die('Erro na ligacao a base de dados. Verifique as credenciais configuradas no alojamento.');
=======
    die('Erro na ligação à base de dados: ' . mysqli_connect_error());
>>>>>>> 989be8b6888fc6188a374134f79a4b4a1ff25c65
}

mysqli_set_charset($conn, 'utf8mb4');

$appConfig = [
    'app_name' => 'CSNSA - Gestor de Assiduidade',
    'app_title' => 'Gestor de Assiduidade',
    'company_name' => 'Centro Social Nossa Senhora Auxiliadora',
<<<<<<< HEAD
    'logo_light' => 'assets/img/kaiadmin/logo_light.svg',
    'logo_dark' => 'assets/img/kaiadmin/logo_dark.svg',
];
?>
    
=======
    'logo' => 'assets/img/csnsa/logo-nsa.png',
    'logo_vertical_light' => 'assets/img/csnsa/logo-nsa-vertical-light.png',
    'logo_vertical_dark' => 'assets/img/csnsa/logo-nsa-vertical-dark.png',
    'logo_light' => 'assets/img/csnsa/logo-nsa-horizontal-light.png',
    'logo_dark' => 'assets/img/csnsa/logo-nsa-horizontal-dark.png',
    'favicon' => 'assets/img/csnsa/favicon-nsa.png',
];
?>

>>>>>>> 989be8b6888fc6188a374134f79a4b4a1ff25c65
