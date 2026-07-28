<?php

/**
 * Fila persistente entre a aplicação pública e o agente instalado na rede do
 * terminal. O servidor público nunca tenta aceder diretamente ao IP privado.
 */
function biometric_queue_ensure_schema(mysqli $conn): void
{
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS biometric_commands (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            operation ENUM('upsert_user', 'delete_user') NOT NULL,
            payload JSON NOT NULL,
            status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            claimed_at DATETIME NULL,
            completed_at DATETIME NULL,
            last_error VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_biometric_commands_queue (status, available_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function biometric_queue_add(mysqli $conn, string $operation, array $payload): int
{
    if (!in_array($operation, ['upsert_user', 'delete_user'], true)) {
        throw new InvalidArgumentException('Operação biométrica inválida.');
    }

    biometric_queue_ensure_schema($conn);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO biometric_commands (operation, payload) VALUES (?, ?)'
    );
    mysqli_stmt_bind_param($stmt, 'ss', $operation, $json);
    mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    return (int) $id;
}

