<?php

function ac_table_exists($conn, $table)
{
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    mysqli_stmt_bind_param($stmt, 's', $table);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function ac_permissions_ready($conn)
{
    return ac_table_exists($conn, 'permissoes')
        && ac_table_exists($conn, 'utilizador_permissoes');
}

function ac_user_has_direct_permissions($conn, $utilizadorId)
{
    if (!ac_table_exists($conn, 'utilizador_permissoes') || !ac_table_exists($conn, 'permissoes')) {
        return false;
    }

    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM permissoes');
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    $totalPermissoes = (int) ($row['total'] ?? 0);

    if ($totalPermissoes <= 0) {
        return false;
    }

    $stmt = mysqli_prepare($conn, 'SELECT COUNT(DISTINCT permissao_id) AS total FROM utilizador_permissoes WHERE utilizador_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $utilizadorId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) >= $totalPermissoes;
}

function ac_user_has_role($conn, $utilizadorId, $slug)
{
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total
        FROM utilizador_papeis up
        INNER JOIN papeis p ON p.id = up.papel_id
        WHERE up.utilizador_id = ? AND p.slug = ? AND p.ativo = 1");
    mysqli_stmt_bind_param($stmt, 'is', $utilizadorId, $slug);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function ac_can($conn, $utilizadorId, $codigo)
{
    $utilizadorId = (int) $utilizadorId;
    if ($utilizadorId <= 0 || $codigo === '') {
        return false;
    }

    if (!ac_permissions_ready($conn)) {
        return ac_user_has_role($conn, $utilizadorId, 'administrador');
    }

    $stmt = mysqli_prepare($conn, "SELECT upm.efeito
        FROM utilizador_permissoes upm
        INNER JOIN permissoes pe ON pe.id = upm.permissao_id
        WHERE upm.utilizador_id = ? AND pe.codigo = ?
        LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'is', $utilizadorId, $codigo);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $override = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($override) {
        return $override['efeito'] === 'permitir';
    }

    if (ac_user_has_direct_permissions($conn, $utilizadorId)) {
        return false;
    }

    if (!ac_table_exists($conn, 'papel_permissoes')) {
        return false;
    }

    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total
        FROM utilizador_papeis up
        INNER JOIN papeis p ON p.id = up.papel_id
        INNER JOIN papel_permissoes pp ON pp.papel_id = p.id
        INNER JOIN permissoes pe ON pe.id = pp.permissao_id
        WHERE up.utilizador_id = ?
          AND p.ativo = 1
          AND pe.codigo = ?");
    mysqli_stmt_bind_param($stmt, 'is', $utilizadorId, $codigo);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function ac_can_any($conn, $utilizadorId, array $codigos)
{
    foreach ($codigos as $codigo) {
        if (ac_can($conn, $utilizadorId, $codigo)) {
            return true;
        }
    }

    return false;
}

function ac_permission_label($codigo, $fallback = '')
{
    $labels = [
        'funcionarios.consultar' => 'Consultar funcionários',
        'funcionarios.editar' => 'Editar funcionários',
        'funcionarios.dados_sensiveis' => 'Consultar dados pessoais sensíveis',
        'funcionarios.desativar' => 'Desativar funcionários',
        'equipas.gerir' => 'Gerir equipas',
        'turnos.gerir' => 'Gerir turnos',
        'escalas.gerir' => 'Gerir escalas',
        'escalas.importar' => 'Importar escalas',
        'ponto.consultar' => 'Consultar ponto',
        'ponto.corrigir' => 'Corrigir ponto',
        'ocorrencias.validar' => 'Validar ocorrências',
        'ausencias.gerir' => 'Gerir ausências',
        'justificacoes.validar' => 'Validar justificações',
        'ferias.gerir' => 'Gerir férias',
        'banco_horas.consultar' => 'Consultar banco de horas',
        'banco_horas.ajustar' => 'Ajustar banco de horas',
        'relatorios.consultar' => 'Consultar relatórios',
        'relatorios.exportar' => 'Exportar relatórios',
        'utilizadores.gerir' => 'Gerir utilizadores',
        'permissoes.gerir' => 'Gerir permissões',
        'logs.consultar' => 'Consultar logs',
        'notificacoes.ver' => 'Ver notificações',
        'notificacoes.gerir' => 'Gerir notificações',
        'funcionarios.ver_idade' => 'Ver idade dos funcionários',
    ];

    return $labels[$codigo] ?? ($fallback !== '' ? $fallback : $codigo);
}

function ac_abort_403($message = 'Acesso negado.')
{
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="pt"><head><meta charset="utf-8"><title>403</title></head><body>';
    echo '<h1>403 - Acesso negado</h1>';
    echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
    exit;
}

function ac_require_permission($conn, $utilizador, $codigo)
{
    $utilizadorId = (int) ($utilizador['id'] ?? $utilizador ?? 0);
    if (!ac_can($conn, $utilizadorId, $codigo)) {
        ac_log($conn, $utilizadorId ?: null, 'acesso_negado', 'permissoes', null, null, 'Permissão exigida: ' . $codigo);
        ac_abort_403('Não tem permissão para aceder a este recurso.');
    }
}

function ac_require_any($conn, $utilizador, array $codigos)
{
    $utilizadorId = (int) ($utilizador['id'] ?? $utilizador ?? 0);
    if (!ac_can_any($conn, $utilizadorId, $codigos)) {
        ac_log($conn, $utilizadorId ?: null, 'acesso_negado', 'permissoes', null, null, 'Permissões exigidas: ' . implode(', ', $codigos));
        ac_abort_403('Não tem permissão para aceder a este recurso.');
    }
}

function ac_log($conn, $utilizadorId, $acao, $modulo = null, $tabela = null, $registoId = null, $descricao = null, $dadosAnteriores = null, $dadosNovos = null)
{
    if (!ac_table_exists($conn, 'logs_sistema')) {
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $oldJson = $dadosAnteriores === null ? null : json_encode($dadosAnteriores, JSON_UNESCAPED_UNICODE);
    $newJson = $dadosNovos === null ? null : json_encode($dadosNovos, JSON_UNESCAPED_UNICODE);

    $stmt = mysqli_prepare($conn, 'INSERT INTO logs_sistema (utilizador_id, acao, modulo, tabela, registo_id, descricao, ip, user_agent, dados_anteriores, dados_novos) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'isssisssss', $utilizadorId, $acao, $modulo, $tabela, $registoId, $descricao, $ip, $userAgent, $oldJson, $newJson);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function ac_active_admin_count($conn, $excludeUserId = null)
{
    if (ac_permissions_ready($conn)) {
        $sql = "SELECT COUNT(*) AS total
            FROM (
                SELECT u.id
                FROM utilizadores u
                INNER JOIN utilizador_permissoes upm ON upm.utilizador_id = u.id
                INNER JOIN permissoes pe ON pe.id = upm.permissao_id
                WHERE u.estado = 'ativo'
                  AND upm.efeito = 'permitir'
                  AND pe.codigo IN ('permissoes.gerir', 'utilizadores.gerir')";

        if ($excludeUserId !== null) {
            $sql .= ' AND u.id <> ?';
        }

        $sql .= " GROUP BY u.id
                HAVING COUNT(DISTINCT pe.codigo) = 2
            ) gestores";
    } else {
        $sql = "SELECT COUNT(DISTINCT u.id) AS total
            FROM utilizadores u
            INNER JOIN utilizador_papeis up ON up.utilizador_id = u.id
            INNER JOIN papeis p ON p.id = up.papel_id
            WHERE u.estado = 'ativo'
              AND p.slug = 'administrador'
              AND p.ativo = 1";

        if ($excludeUserId !== null) {
            $sql .= ' AND u.id <> ?';
        }
    }

    $stmt = mysqli_prepare($conn, $sql);
    if ($excludeUserId !== null) {
        mysqli_stmt_bind_param($stmt, 'i', $excludeUserId);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0);
}

function ac_papel_slug($conn, $papelId)
{
    if (!$papelId) {
        return null;
    }

    $stmt = mysqli_prepare($conn, 'SELECT slug FROM papeis WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $papelId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row['slug'] ?? null;
}

function ac_would_remove_last_admin($conn, $targetUserId, $newEstado, $newPapelId)
{
    if (ac_permissions_ready($conn)) {
        return false;
    }

    $newRoleIsAdmin = ac_papel_slug($conn, $newPapelId) === 'administrador';
    if ($newEstado === 'ativo' && $newRoleIsAdmin) {
        return false;
    }

    return ac_active_admin_count($conn, (int) $targetUserId) === 0 && ac_user_has_role($conn, (int) $targetUserId, 'administrador');
}

function ac_requires_self_admin_confirmation($conn, $sessionUserId, $targetUserId, $newEstado, $newPapelId)
{
    if (ac_permissions_ready($conn)) {
        return false;
    }

    if ((int) $sessionUserId !== (int) $targetUserId) {
        return false;
    }

    if (!ac_user_has_role($conn, (int) $targetUserId, 'administrador')) {
        return false;
    }

    return $newEstado !== 'ativo' || ac_papel_slug($conn, $newPapelId) !== 'administrador';
}

