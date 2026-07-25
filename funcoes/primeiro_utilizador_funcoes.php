<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function total_utilizadores($conn)
{
    $result = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM utilizadores');
    $row = $result ? mysqli_fetch_assoc($result) : ['total' => 0];
    return (int) ($row['total'] ?? 0);
}
