<?php
declare(strict_types=1);

require_once "../includes/error.php";
require_once "conexao.php";

$url = "http://localhost/Gestor-de-Presen-as-Centro-Social-Nossa-Senhora-Auxiliadora/biometrico/teste_json.php";

$tipos = [
    0 => 'entrada',
    1 => 'inicio_pausa',
    2 => 'fim_pausa',
    3 => 'saida'
];

$json = file_get_contents($url);

if ($json === false) {
    throw new RuntimeException("Não foi possível obter os dados do biométrico.");
}

$logs = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

if (!is_array($logs)) {
    throw new RuntimeException("JSON recebido do biométrico não é válido.");
}

$verificarFuncionarioStmt = $pdo->prepare("
    SELECT id
    FROM funcionarios
    WHERE id = :funcionario_id
    LIMIT 1
");

$verificarDuplicadoStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM registos_ponto
    WHERE funcionario_id = :funcionario_id
    AND data_hora = :data_hora
");

$contarStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM registos_ponto
    WHERE funcionario_id = :funcionario_id
    AND DATE(data_hora) = :data_referencia
");

$insertStmt = $pdo->prepare("
    INSERT INTO registos_ponto (
        funcionario_id,
        tipo,
        data_hora
    ) VALUES (
        :funcionario_id,
        :tipo,
        :data_hora
    )
");

$inseridos = 0;
$ignorados = 0;

$pdo->beginTransaction();

foreach ($logs as $log) {

    if (!is_array($log)) {
        $ignorados++;
        continue;
    }

    $funcionarioId = filter_var($log['id'] ?? null, FILTER_VALIDATE_INT);
    $dataHoraOriginal = trim((string)($log['data_hora'] ?? ''));

    if (!$funcionarioId || $funcionarioId <= 0 || $dataHoraOriginal === '') {
        $ignorados++;
        continue;
    }

    $timestamp = strtotime($dataHoraOriginal);

    if ($timestamp === false) {
        $ignorados++;
        continue;
    }

    $dataHora = date('Y-m-d H:i:s', $timestamp);
    $dataReferencia = date('Y-m-d', $timestamp);

    $verificarFuncionarioStmt->execute([
        ':funcionario_id' => $funcionarioId
    ]);

    if (!$verificarFuncionarioStmt->fetchColumn()) {
        $ignorados++;
        continue;
    }

    $verificarDuplicadoStmt->execute([
        ':funcionario_id' => $funcionarioId,
        ':data_hora' => $dataHora
    ]);

    if ((int)$verificarDuplicadoStmt->fetchColumn() > 0) {
        $ignorados++;
        continue;
    }

    $contarStmt->execute([
        ':funcionario_id' => $funcionarioId,
        ':data_referencia' => $dataReferencia
    ]);

    $totalPicagensHoje = (int)$contarStmt->fetchColumn();

    if ($totalPicagensHoje >= 4) {
        $ignorados++;
        continue;
    }

    $tipo = $tipos[$totalPicagensHoje];

    $insertStmt->execute([
        ':funcionario_id' => $funcionarioId,
        ':tipo' => $tipo,
        ':data_hora' => $dataHora
    ]);

    $inseridos++;
}

$pdo->commit();

echo "Sincronização concluída.";






////-------------------------------------------------
