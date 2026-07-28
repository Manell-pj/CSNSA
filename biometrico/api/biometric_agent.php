<?php

ob_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/biometric_queue.php';
require_once __DIR__ . '/../../funcoes/funcionarios_estado.php';
require_once __DIR__ . '/../../funcoes/ponto_funcoes.php';
require_once __DIR__ . '/../../funcoes/calcular_resumo_diario_assiduidade.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function agent_response(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function agent_bearer_token(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

function biometric_agent_token(): string
{
    $token = getenv('BIOMETRIC_AGENT_TOKEN') ?: '';
    $secretFile = __DIR__ . '/../biometric_secret.php';
    if ($token === '' && is_file($secretFile)) {
        $secretConfig = require $secretFile;
        if (is_array($secretConfig)) {
            $token = (string) ($secretConfig['token'] ?? '');
        }
    }

    return $token;
}

function biometric_find_employee(mysqli $conn, string $terminalUserId): ?array
{
    if (fe_column_exists($conn, 'funcionarios', 'codigo_biometrico')) {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, utilizador_id FROM funcionarios WHERE codigo_biometrico = ? AND estado = 'ativo' LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, 's', $terminalUserId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($row) {
            return $row;
        }
    }

    if (preg_match('/^\d+$/', $terminalUserId) !== 1) {
        return null;
    }

    $funcionarioId = (int) $terminalUserId;
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, utilizador_id FROM funcionarios WHERE id = ? AND estado = 'ativo' LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $funcionarioId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function biometric_attendance_exists(mysqli $conn, int $funcionarioId, ?int $utilizadorId, string $timestamp): bool
{
    if (fe_column_exists($conn, 'registos_ponto', 'funcionario_id')) {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM registos_ponto WHERE funcionario_id = ? AND data_hora = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'is', $funcionarioId, $timestamp);
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM registos_ponto WHERE utilizador_id = ? AND data_hora = ? LIMIT 1');
        $lookupId = $utilizadorId ?: $funcionarioId;
        mysqli_stmt_bind_param($stmt, 'is', $lookupId, $timestamp);
    }

    mysqli_stmt_execute($stmt);
    $exists = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $exists;
}

function biometric_insert_attendance(mysqli $conn, int $funcionarioId, ?int $utilizadorId, string $type, string $timestamp, string $referenceDate): void
{
    $columns = [];
    $placeholders = [];
    $types = '';
    $params = [];

    if (fe_column_exists($conn, 'registos_ponto', 'funcionario_id')) {
        $columns[] = 'funcionario_id';
        $placeholders[] = '?';
        $types .= 'i';
        $params[] = $funcionarioId;
    }

    if (fe_column_exists($conn, 'registos_ponto', 'utilizador_id')) {
        $columns[] = 'utilizador_id';
        $placeholders[] = '?';
        $types .= 'i';
        $params[] = $utilizadorId ?: $funcionarioId;
    }

    $columns[] = 'tipo';
    $placeholders[] = '?';
    $types .= 's';
    $params[] = $type;

    $columns[] = 'data_hora';
    $placeholders[] = '?';
    $types .= 's';
    $params[] = $timestamp;

    if (fe_column_exists($conn, 'registos_ponto', 'data_referencia')) {
        $columns[] = 'data_referencia';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $referenceDate;
    }

    $columns[] = 'origem';
    $placeholders[] = "'dispositivo'";

    $columns[] = 'estado';
    $placeholders[] = "'valido'";

    if (fe_column_exists($conn, 'registos_ponto', 'observacoes')) {
        $columns[] = 'observacoes';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = 'Importado automaticamente do terminal biometrico';
    }

    if (fe_column_exists($conn, 'registos_ponto', 'registo_manual')) {
        $columns[] = 'registo_manual';
        $placeholders[] = '0';
    }

    $sql = 'INSERT INTO registos_ponto (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

$configuredToken = biometric_agent_token();
if (strlen($configuredToken) < 32) {
    agent_response(503, ['success' => false, 'message' => 'Agente biometrico nao configurado.']);
}

if (!hash_equals($configuredToken, agent_bearer_token())) {
    agent_response(401, ['success' => false, 'message' => 'Nao autorizado.']);
}

biometric_queue_ensure_schema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    mysqli_begin_transaction($conn);
    try {
        mysqli_query(
            $conn,
            "UPDATE biometric_commands
             SET status = 'pending', claimed_at = NULL
             WHERE status = 'processing'
               AND claimed_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
               AND attempts < 10"
        );

        $result = mysqli_query(
            $conn,
            "SELECT id, operation, payload
             FROM biometric_commands
             WHERE status = 'pending' AND available_at <= NOW() AND attempts < 10
             ORDER BY id
             LIMIT 1
             FOR UPDATE"
        );
        $command = mysqli_fetch_assoc($result);

        if (!$command) {
            mysqli_commit($conn);
            agent_response(200, ['success' => true, 'command' => null]);
        }

        $id = (int) $command['id'];
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE biometric_commands
             SET status = 'processing', claimed_at = NOW(), attempts = attempts + 1
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_commit($conn);

        agent_response(200, [
            'success' => true,
            'command' => [
                'id' => $id,
                'operation' => $command['operation'],
                'payload' => json_decode($command['payload'], true),
            ],
        ]);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        agent_response(500, ['success' => false, 'message' => 'Nao foi possivel obter a tarefa.']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        agent_response(422, ['success' => false, 'message' => 'JSON invalido.']);
    }

    if (isset($input['attendance']) && is_array($input['attendance'])) {
        $attendance = array_slice($input['attendance'], 0, 500);
        $typeMap = [
            0 => 'entrada',
            1 => 'saida',
            4 => 'entrada',
            5 => 'saida',
        ];
        $inserted = 0;
        $duplicates = 0;
        $rejected = 0;
        $unknownUsers = 0;
        $recalculos = [];

        mysqli_begin_transaction($conn);
        try {
            foreach ($attendance as $item) {
                $terminalUserId = trim((string) ($item['id'] ?? $item['userid'] ?? ''));
                $terminalType = (int) ($item['type'] ?? -1);
                $timestamp = trim((string) ($item['timestamp'] ?? ''));
                $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $timestamp);

                if (
                    $terminalUserId === ''
                    || preg_match('/^\d{1,9}$/', $terminalUserId) !== 1
                    || !isset($typeMap[$terminalType])
                    || !$dateTime
                    || $dateTime->format('Y-m-d H:i:s') !== $timestamp
                ) {
                    $rejected++;
                    continue;
                }

                $employee = biometric_find_employee($conn, $terminalUserId);
                if (!$employee) {
                    $unknownUsers++;
                    continue;
                }

                $funcionarioId = (int) $employee['id'];
                $utilizadorId = isset($employee['utilizador_id']) ? (int) $employee['utilizador_id'] : null;
                if (biometric_attendance_exists($conn, $funcionarioId, $utilizadorId, $timestamp)) {
                    $duplicates++;
                    continue;
                }

                $type = $typeMap[$terminalType];
                $referenceDate = $dateTime->format('Y-m-d');
                biometric_insert_attendance($conn, $funcionarioId, $utilizadorId, $type, $timestamp, $referenceDate);
                $recalculos[$referenceDate . ':' . $funcionarioId] = [$referenceDate, $funcionarioId];
                $inserted++;
            }

            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            agent_response(500, ['success' => false, 'message' => 'Nao foi possivel guardar as picagens.']);
        }

        foreach ($recalculos as [$referenceDate, $funcionarioId]) {
            ponto_atualizar_resumo_assiduidade($conn, $referenceDate, $funcionarioId);
        }

        agent_response(200, [
            'success' => true,
            'inserted' => $inserted,
            'duplicates' => $duplicates,
            'rejected' => $rejected,
            'unknown_users' => $unknownUsers,
        ]);
    }

    $id = (int) ($input['id'] ?? 0);
    $success = filter_var($input['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $error = substr(trim((string) ($input['error'] ?? '')), 0, 500);

    if ($id <= 0) {
        agent_response(422, ['success' => false, 'message' => 'ID invalido.']);
    }

    if ($success) {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE biometric_commands
             SET status = 'completed', completed_at = NOW(), last_error = NULL
             WHERE id = ? AND status = 'processing'"
        );
        mysqli_stmt_bind_param($stmt, 'i', $id);
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE biometric_commands
             SET status = IF(attempts >= 10, 'failed', 'pending'),
                 available_at = DATE_ADD(NOW(), INTERVAL 30 SECOND),
                 claimed_at = NULL,
                 last_error = ?
             WHERE id = ? AND status = 'processing'"
        );
        mysqli_stmt_bind_param($stmt, 'si', $error, $id);
    }

    mysqli_stmt_execute($stmt);
    $updated = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    agent_response($updated ? 200 : 409, ['success' => (bool) $updated]);
}

header('Allow: GET, POST');
agent_response(405, ['success' => false, 'message' => 'Metodo nao permitido.']);
