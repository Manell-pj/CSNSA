<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/permissoes.php';

function auth_user($conn)
{
    if (!ac_table_exists($conn, 'utilizadores')) {
        unset($_SESSION['utilizador_id'], $_SESSION['utilizador_nome']);
        return null;
    }

    $utilizadorId = (int) ($_SESSION['utilizador_id'] ?? 0);

    if ($utilizadorId <= 0) {
        return null;
    }

    $fotoSelect = auth_column_exists($conn, 'utilizadores', 'foto') ? 'foto,' : 'NULL AS foto,';
    $stmt = mysqli_prepare($conn, "SELECT id, nome, email, estado, $fotoSelect 1 AS auth_marker FROM utilizadores WHERE id = ? AND estado = 'ativo' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $utilizadorId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $utilizador = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$utilizador) {
        unset($_SESSION['utilizador_id'], $_SESSION['utilizador_nome']);
        return null;
    }

    return $utilizador;
}

function auth_column_exists($conn, $table, $column)
{
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function require_login($conn)
{
    $utilizador = auth_user($conn);

    if ($utilizador) {
        return $utilizador;
    }

    $destino = $_SERVER['REQUEST_URI'] ?? 'principal.php';
    header('Location: login.php?' . http_build_query(['redirect' => $destino]));
    exit;
}

function redirect_if_logged_in($conn)
{
    if (auth_user($conn)) {
        header('Location: principal.php');
        exit;
    }
}

function auth_safe_redirect($redirect)
{
    $redirect = trim((string) $redirect);

    if ($redirect === '' || preg_match('#^https?://#i', $redirect) || strpos($redirect, '//') === 0) {
        return 'principal.php';
    }

    return $redirect;
}
