<?php

if (!function_exists('nt_table_exists')) {
    function nt_table_exists($conn, $table)
    {
        $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        return (int) ($row['total'] ?? 0) > 0;
    }
}

if (!function_exists('nt_column_exists')) {
    function nt_column_exists($conn, $table, $column)
    {
        $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        return (int) ($row['total'] ?? 0) > 0;
    }
}

function nt_schema_ready($conn)
{
    foreach (['notificacoes', 'notificacao_destinatarios', 'notificacoes_config', 'diuturnidades_atribuicoes'] as $table) {
        if (!nt_table_exists($conn, $table)) {
            return false;
        }
    }

    return nt_table_exists($conn, 'funcionarios')
        && nt_column_exists($conn, 'funcionarios', 'data_nascimento')
        && nt_column_exists($conn, 'funcionarios', 'data_admissao')
        && nt_column_exists($conn, 'funcionarios', 'diuturnidade_ciclo_anos')
        && nt_column_exists($conn, 'funcionarios', 'diuturnidade_ativa');
}

function nt_has_permission($conn, $utilizadorId, $codigo)
{
    $utilizadorId = (int) $utilizadorId;
    if ($utilizadorId <= 0) {
        return false;
    }

    if (!nt_table_exists($conn, 'permissoes') || !nt_table_exists($conn, 'utilizador_permissoes')) {
        return true;
    }

    $sql = "SELECT COUNT(*) AS total
            FROM utilizador_permissoes upm
            INNER JOIN permissoes pe ON pe.id = upm.permissao_id
            WHERE upm.utilizador_id = ?
              AND upm.efeito = 'permitir'
              AND pe.codigo = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'is', $utilizadorId, $codigo);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function nt_config_int($conn, $chave, $default)
{
    if (!nt_table_exists($conn, 'notificacoes_config')) {
        return (int) $default;
    }

    $stmt = mysqli_prepare($conn, 'SELECT valor FROM notificacoes_config WHERE chave = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $chave);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $value = (int) ($row['valor'] ?? $default);
    return $value > 0 ? $value : (int) $default;
}

function nt_set_config_int($conn, $chave, $valor)
{
    $valor = max(1, (int) $valor);
    $valorTexto = (string) $valor;
    $stmt = mysqli_prepare($conn, 'INSERT INTO notificacoes_config (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
    mysqli_stmt_bind_param($stmt, 'ss', $chave, $valorTexto);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function nt_event_date_for_year($date, $year)
{
    $monthDay = date('m-d', strtotime($date));
    if ($monthDay === '02-29' && !checkdate(2, 29, (int) $year)) {
        return $year . '-02-28';
    }

    return $year . '-' . $monthDay;
}

function nt_sync_destinatarios($conn, $notificacaoId, $permissaoCodigo)
{
    if (!nt_table_exists($conn, 'permissoes') || !nt_table_exists($conn, 'utilizador_permissoes')) {
        $sql = "INSERT IGNORE INTO notificacao_destinatarios (notificacao_id, utilizador_id)
                SELECT ?, id FROM utilizadores WHERE estado = 'ativo'";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $notificacaoId);
    } else {
        $sql = "INSERT IGNORE INTO notificacao_destinatarios (notificacao_id, utilizador_id)
                SELECT DISTINCT ?, u.id
                FROM utilizadores u
                INNER JOIN utilizador_permissoes upm ON upm.utilizador_id = u.id
                INNER JOIN permissoes pe ON pe.id = upm.permissao_id
                WHERE u.estado = 'ativo'
                  AND upm.efeito = 'permitir'
                  AND pe.codigo = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'is', $notificacaoId, $permissaoCodigo);
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function nt_insert_notificacao($conn, $tipo, $funcionarioId, $titulo, $mensagem, $dataEvento, $referenciaAno, $cicloNumero = null)
{
    $permissao = 'notificacoes.ver';
    $cicloNumero = $cicloNumero === null ? 0 : (int) $cicloNumero;
    $stmt = mysqli_prepare($conn, "INSERT INTO notificacoes
        (tipo, funcionario_id, titulo, mensagem, data_evento, referencia_ano, ciclo_numero, permissao_codigo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            titulo = VALUES(titulo),
            mensagem = VALUES(mensagem),
            data_evento = VALUES(data_evento),
            permissao_codigo = VALUES(permissao_codigo),
            estado = IF(estado = 'ocultada', estado, 'ativa')");
    mysqli_stmt_bind_param($stmt, 'sisssiis', $tipo, $funcionarioId, $titulo, $mensagem, $dataEvento, $referenciaAno, $cicloNumero, $permissao);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, 'SELECT id FROM notificacoes WHERE tipo = ? AND funcionario_id = ? AND referencia_ano = ? AND (ciclo_numero <=> ?) LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'siii', $tipo, $funcionarioId, $referenciaAno, $cicloNumero);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row) {
        nt_sync_destinatarios($conn, (int) $row['id'], $permissao);
    }
}

function nt_generate_notifications($conn)
{
    if (!nt_schema_ready($conn)) {
        return;
    }

    $birthdayDays = nt_config_int($conn, 'aniversarios_dias_aviso', 30);
    $diuturnidadeDays = nt_config_int($conn, 'diuturnidades_dias_aviso', 60);
    $defaultCycleYears = nt_config_int($conn, 'diuturnidades_ciclo_anos', 5);
    $today = new DateTimeImmutable('today');

    $stmt = mysqli_prepare($conn, "SELECT id, nome, estado, data_nascimento, data_admissao, diuturnidade_ciclo_anos, diuturnidade_ativa
        FROM funcionarios
        WHERE data_nascimento IS NOT NULL
           OR (data_admissao IS NOT NULL AND diuturnidade_ativa = 1)");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($funcionario = mysqli_fetch_assoc($result)) {
        $funcionarioId = (int) $funcionario['id'];

        if (!empty($funcionario['data_nascimento'])) {
            foreach ([(int) $today->format('Y'), (int) $today->format('Y') + 1] as $year) {
                $eventDate = nt_event_date_for_year($funcionario['data_nascimento'], $year);
                $diff = (int) $today->diff(new DateTimeImmutable($eventDate))->format('%r%a');
                if ($diff >= 0 && $diff <= $birthdayDays) {
                    $titulo = 'Aniversário de ' . $funcionario['nome'];
                    $mensagem = $diff === 0
                        ? $funcionario['nome'] . ' faz anos hoje.'
                        : $funcionario['nome'] . ' faz anos dentro de ' . $diff . ' dia(s).';
                    nt_insert_notificacao($conn, 'aniversario', $funcionarioId, $titulo, $mensagem, $eventDate, $year, null);
                    break;
                }
            }
        }

        if (!empty($funcionario['data_admissao']) && (int) $funcionario['diuturnidade_ativa'] === 1) {
            $cycleYears = (int) ($funcionario['diuturnidade_ciclo_anos'] ?: $defaultCycleYears);
            if ($cycleYears <= 0) {
                continue;
            }

            $dataBase = new DateTimeImmutable($funcionario['data_admissao']);
            for ($cycle = 1; $cycle <= 80; $cycle++) {
                $due = $dataBase->modify('+' . ($cycle * $cycleYears) . ' years');
                $diff = (int) $today->diff($due)->format('%r%a');
                if ($diff > $diuturnidadeDays) {
                    break;
                }

                if ($diff < -365) {
                    continue;
                }

                $dueDate = $due->format('Y-m-d');
                $existsStmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM diuturnidades_atribuicoes WHERE funcionario_id = ? AND ciclo_numero = ? AND data_vencimento = ?');
                mysqli_stmt_bind_param($existsStmt, 'iis', $funcionarioId, $cycle, $dueDate);
                mysqli_stmt_execute($existsStmt);
                $existsResult = mysqli_stmt_get_result($existsStmt);
                $existsRow = mysqli_fetch_assoc($existsResult);
                mysqli_stmt_close($existsStmt);

                if ((int) ($existsRow['total'] ?? 0) > 0) {
                    continue;
                }

                $titulo = 'Diuturnidade de ' . $funcionario['nome'];
                $mensagem = $diff < 0
                    ? 'Diuturnidade vencida em ' . date('d/m/Y', strtotime($dueDate)) . '. Confirmacao manual pendente.'
                    : 'Diuturnidade prevista para ' . date('d/m/Y', strtotime($dueDate)) . '. Confirmacao manual pendente.';
                nt_insert_notificacao($conn, 'diuturnidade', $funcionarioId, $titulo, $mensagem, $dueDate, (int) $due->format('Y'), $cycle);
            }
        }
    }

    mysqli_stmt_close($stmt);
}

function nt_count_unread($conn, $utilizadorId)
{
    if (!nt_schema_ready($conn)) {
        return 0;
    }

    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total
        FROM notificacao_destinatarios nd
        INNER JOIN notificacoes n ON n.id = nd.notificacao_id
        WHERE nd.utilizador_id = ?
          AND nd.lida_at IS NULL
          AND n.estado = 'ativa'");
    mysqli_stmt_bind_param($stmt, 'i', $utilizadorId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0);
}

function nt_list_notifications($conn, $utilizadorId, $limit = 50, $showInativos = false, $onlyUnread = false)
{
    if (!nt_schema_ready($conn)) {
        return [];
    }

    $limit = max(1, (int) $limit);
    $where = ["nd.utilizador_id = ?", "n.estado = 'ativa'"];
    if (!$showInativos) {
        $where[] = "f.estado <> 'inativo'";
    }
    if ($onlyUnread) {
        $where[] = 'nd.lida_at IS NULL';
    }

    $sql = "SELECT n.*, nd.lida_at, f.nome AS funcionario_nome, f.estado AS funcionario_estado, f.data_nascimento,
                   f.data_admissao, f.diuturnidade_ciclo_anos
            FROM notificacao_destinatarios nd
            INNER JOIN notificacoes n ON n.id = nd.notificacao_id
            INNER JOIN funcionarios f ON f.id = n.funcionario_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY n.data_evento ASC, n.created_at DESC
            LIMIT $limit";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $utilizadorId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $items;
}

function nt_mark_read($conn, $notificacaoId, $utilizadorId, $read)
{
    $readAt = $read ? date('Y-m-d H:i:s') : null;
    $stmt = mysqli_prepare($conn, 'UPDATE notificacao_destinatarios SET lida_at = ? WHERE notificacao_id = ? AND utilizador_id = ?');
    mysqli_stmt_bind_param($stmt, 'sii', $readAt, $notificacaoId, $utilizadorId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function nt_delete_notification($conn, $notificacaoId)
{
    $notificacaoId = (int) $notificacaoId;
    if ($notificacaoId <= 0 || !nt_schema_ready($conn)) {
        return false;
    }

    $stmt = mysqli_prepare($conn, "UPDATE notificacoes SET estado = 'ocultada' WHERE id = ? AND estado <> 'ocultada'");
    mysqli_stmt_bind_param($stmt, 'i', $notificacaoId);
    mysqli_stmt_execute($stmt);
    $updated = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $updated;
}

function nt_confirm_diuturnidade($conn, $notificacaoId, $utilizadorId, $observacoes = null)
{
    $stmt = mysqli_prepare($conn, "SELECT n.*, f.data_admissao, f.diuturnidade_ciclo_anos
        FROM notificacoes n
        INNER JOIN funcionarios f ON f.id = n.funcionario_id
        WHERE n.id = ? AND n.tipo = 'diuturnidade' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $notificacaoId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $notificacao = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$notificacao) {
        return false;
    }

    $cycleYears = (int) ($notificacao['diuturnidade_ciclo_anos'] ?: nt_config_int($conn, 'diuturnidades_ciclo_anos', 5));
    if ($cycleYears <= 0 || empty($notificacao['data_admissao']) || empty($notificacao['ciclo_numero'])) {
        return false;
    }

    mysqli_begin_transaction($conn);
    try {
        $funcionarioId = (int) $notificacao['funcionario_id'];
        $dataBase = $notificacao['data_admissao'];
        $cicloNumero = (int) $notificacao['ciclo_numero'];
        $dataVencimento = $notificacao['data_evento'];
        $stmt = mysqli_prepare($conn, "INSERT INTO diuturnidades_atribuicoes
            (funcionario_id, data_base, ciclo_anos, ciclo_numero, data_vencimento, confirmado_por, observacoes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE observacoes = VALUES(observacoes)");
        mysqli_stmt_bind_param(
            $stmt,
            'isiisis',
            $funcionarioId,
            $dataBase,
            $cycleYears,
            $cicloNumero,
            $dataVencimento,
            $utilizadorId,
            $observacoes
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "UPDATE notificacoes SET estado = 'resolvida' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $notificacaoId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        return true;
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}
