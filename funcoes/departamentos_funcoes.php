<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_with_message($type, $message)
{
    header('Location: departamentos.php?' . http_build_query([
        'type' => $type,
        'message' => $message,
    ]));
    exit;
}

function get_post_value($key)
{
    return trim($_POST[$key] ?? '');
}

function nullable_text($value)
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function gerar_codigo_equipa()
{
    return 'EQ-' . date('YmdHis') . '-' . random_int(100, 999);
}

function equipa_setor_padrao($conn)
{
    if (!fe_table_exists($conn, 'setores')) {
        return null;
    }

    $stmt = mysqli_prepare($conn, 'SELECT id FROM setores WHERE ativo = 1 ORDER BY id ASC LIMIT 1');
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return isset($row['id']) ? (int) $row['id'] : null;
}

function equipa_remover_membros($conn, $equipaId)
{
    $equipaId = (int) $equipaId;
    if ($equipaId <= 0 || !fe_column_exists($conn, 'funcionarios', 'equipa_id')) {
        return;
    }

    $stmt = mysqli_prepare($conn, 'UPDATE funcionarios SET equipa_id = NULL WHERE equipa_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $equipaId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
