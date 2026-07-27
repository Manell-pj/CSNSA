<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_with_message($type, $message)
{
    header('Location: ausencias.php?' . http_build_query([
        'type' => $type,
        'message' => $message,
    ]));
    exit;
}

function estado_label($estado)
{
    $labels = [
        'pendente' => 'Pendente',
        'aprovado' => 'Aprovado',
        'rejeitado' => 'Recusado',
        'cancelado' => 'Cancelado',
    ];

    return $labels[$estado] ?? ucfirst((string) $estado);
}

function estado_badge($estado)
{
    $classes = [
        'pendente' => 'warning',
        'aprovado' => 'success',
        'rejeitado' => 'danger',
        'cancelado' => 'secondary',
    ];

    return $classes[$estado] ?? 'secondary';
}

function pode_aprovar($conn, $utilizadorId)
{
    return ac_can_any($conn, $utilizadorId, ['justificacoes.validar', 'ferias.gerir']);
}

function ausencias_column_exists($conn, $table, $column)
{
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function garantir_estrutura_ausencias($conn)
{
    if (ac_table_exists($conn, 'pedidos_ausencia') && !ausencias_column_exists($conn, 'pedidos_ausencia', 'funcionario_id')) {
        mysqli_query($conn, 'ALTER TABLE pedidos_ausencia ADD COLUMN funcionario_id INT UNSIGNED DEFAULT NULL AFTER utilizador_id');
        mysqli_query($conn, 'ALTER TABLE pedidos_ausencia ADD INDEX idx_pedidos_funcionario (funcionario_id)');
    }

    if (ac_table_exists($conn, 'permissoes')) {
        $stmt = mysqli_prepare($conn, 'INSERT INTO permissoes (codigo, nome, descricao) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao)');
        $codigo = 'ausencias.pedir';
        $nome = 'Pedir ausencias';
        $descricao = 'Criar pedidos de ausencia proprios';
        mysqli_stmt_bind_param($stmt, 'sss', $codigo, $nome, $descricao);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function guardar_anexo_seguro($file)
{
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível carregar o anexo.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('O anexo não pode exceder 5 MB.');
    }

    $extensoesPermitidas = ['pdf', 'jpg', 'jpeg', 'png'];
    $mimePermitidos = ['application/pdf', 'image/jpeg', 'image/png'];
    $extensao = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extensao, $extensoesPermitidas, true)) {
        throw new RuntimeException('Formato de anexo inválido. Use PDF, JPG ou PNG.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $mimePermitidos, true)) {
        throw new RuntimeException('O conteúdo do ficheiro não corresponde a um formato permitido.');
    }

    // store outside public uploads when possible
    $diretorio = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'ausencias';

    if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true)) {
        throw new RuntimeException('Não foi possível preparar a pasta de anexos.');
    }

    $nomeSeguro = bin2hex(random_bytes(16)) . '.' . $extensao;
    $destino = $diretorio . DIRECTORY_SEPARATOR . $nomeSeguro;

    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        throw new RuntimeException('Não foi possível guardar o anexo.');
    }

    // return stored filename (not public path). Use download_ausencia.php to serve.
    return $nomeSeguro;
}

function garantir_tipos_ausencia($conn)
{
    $tipos = [
        ['Férias', 'ferias', 'Pedido de férias', 1, 1, 0],
        ['Falta Justificada', 'falta-justificada', 'Falta com justificação', 1, 0, 1],
        ['Baixa Médica', 'baixa-medica', 'Baixa por motivo de saúde', 1, 0, 1],
        ['Folga', 'folga', 'Pedido de folga', 1, 0, 0],
    ];

    foreach ($tipos as $tipo) {
        [$nome, $slug, $descricao, $remunerada, $descontaFerias, $exigeJustificativo] = $tipo;

        $stmt = mysqli_prepare($conn, 'SELECT id FROM tipos_ausencia WHERE slug = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $slug);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existe = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$existe) {
            $stmt = mysqli_prepare($conn, 'INSERT INTO tipos_ausencia (nome, slug, descricao, remunerada, desconta_ferias, exige_justificativo) VALUES (?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sssiii', $nome, $slug, $descricao, $remunerada, $descontaFerias, $exigeJustificativo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

function get_funcionario_id_from_utilizador($conn, $utilizadorId)
{
    $stmt = mysqli_prepare($conn, 'SELECT id FROM funcionarios WHERE utilizador_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $utilizadorId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $r = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $r['id'] ?? null;
}

function get_utilizador_id_from_funcionario($conn, $funcionarioId)
{
    $stmt = mysqli_prepare($conn, 'SELECT utilizador_id FROM funcionarios WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $funcionarioId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $r = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $r['utilizador_id'] ?? null;
}

function carregar_destinatarios_ausencia($conn)
{
    $destinatarios = [];

    if (ac_table_exists($conn, 'funcionarios')) {
        $sql = "SELECT f.id AS funcionario_id, f.nome AS funcionario_nome, f.numero_mecanografico, f.utilizador_id, u.nome AS utilizador_nome
            FROM funcionarios f
            LEFT JOIN utilizadores u ON u.id = f.utilizador_id
            WHERE f.estado = 'ativo'
            ORDER BY f.nome ASC";
        $res = mysqli_query($conn, $sql);
        while ($res && $row = mysqli_fetch_assoc($res)) {
            $nome = $row['funcionario_nome'];
            if (!empty($row['numero_mecanografico'])) {
                $nome .= ' (' . $row['numero_mecanografico'] . ')';
            }
            if (!empty($row['utilizador_nome']) && $row['utilizador_nome'] !== $row['funcionario_nome']) {
                $nome .= ' - utilizador: ' . $row['utilizador_nome'];
            }
            $destinatarios[] = [
                'funcionario_id' => (int) $row['funcionario_id'],
                'utilizador_id' => $row['utilizador_id'] === null ? null : (int) $row['utilizador_id'],
                'nome' => $nome,
            ];
        }
    }

    return $destinatarios;
}

function calcular_dias_ferias_por_escala($conn, $funcionario_id, $dataInicio, $dataFim)
{
    // retorna ['computado_por_escala' => bool, 'dias' => int, 'detalhe' => [...], 'conflitos' => [...]]
    $out = ['computado_por_escala' => false, 'dias' => 0, 'detalhe' => [], 'conflitos' => []];
    if (!$funcionario_id) return $out;

    $dt = new DateTime($dataInicio);
    $end = new DateTime($dataFim);

    $tabelaEscala = ac_table_exists($conn, 'escala_funcionarios') ? 'escala_funcionarios' : 'escala_mensal_dias';
    if (!ac_table_exists($conn, $tabelaEscala)) {
        return $out;
    }

    // buscar entradas de escala entre as datas
    $stmt = mysqli_prepare($conn, "SELECT data_escala, tipo_dia, turno_id FROM $tabelaEscala WHERE funcionario_id = ? AND data_escala BETWEEN ? AND ?");
    mysqli_stmt_bind_param($stmt, 'iss', $funcionario_id, $dataInicio, $dataFim);
    mysqli_stmt_execute($stmt);
    $rset = mysqli_stmt_get_result($stmt);
    $escala = [];
    while ($row = mysqli_fetch_assoc($rset)) {
        $escala[$row['data_escala']] = $row;
    }
    mysqli_stmt_close($stmt);

    if (empty($escala)) {
        // sem regras de escala -> não inventar dias
        return $out;
    }

    $out['computado_por_escala'] = true;

    // detectar conflitos com outros pedidos/ferias
    $stmtC = mysqli_prepare($conn, 'SELECT id, tipo_ausencia_id, estado, data_inicio, data_fim FROM pedidos_ausencia WHERE utilizador_id = (SELECT utilizador_id FROM funcionarios WHERE id = ? LIMIT 1) AND data_inicio <= ? AND data_fim >= ? AND estado IN ("pendente","aprovado")');
    mysqli_stmt_bind_param($stmtC, 'iss', $funcionario_id, $dataFim, $dataInicio);
    mysqli_stmt_execute($stmtC);
    $rc = mysqli_stmt_get_result($stmtC);
    while ($cr = mysqli_fetch_assoc($rc)) {
        $out['conflitos'][] = ['tipo'=>'pedido', 'id'=>$cr['id'], 'estado'=>$cr['estado'], 'inicio'=>$cr['data_inicio'], 'fim'=>$cr['data_fim']];
    }
    mysqli_stmt_close($stmtC);

    // contar dias em que escala indica trabalho (tipo_dia = 'trabalho' ou turno_id not null)
    $curr = clone $dt;
    while ($curr <= $end) {
        $d = $curr->format('Y-m-d');
        if (isset($escala[$d])) {
            $row = $escala[$d];
            $isWork = (in_array($row['tipo_dia'], ['trabalho', 'turno', 'substituicao'], true) || !empty($row['turno_id']));
            $out['detalhe'][$d] = ['tipo' => $row['tipo_dia'], 'turno_id' => $row['turno_id'], 'conta' => $isWork ? 1 : 0];
            if ($isWork) $out['dias']++;
        } else {
            // sem entrada, não conta (não inventar)
            $out['detalhe'][$d] = ['tipo' => 'sem_escala', 'turno_id' => null, 'conta' => 0];
        }
        $curr->modify('+1 day');
    }

    return $out;
}

function ensure_escala_hist_table($conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS escala_mensal_dias_hist (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      escala_dia_id BIGINT UNSIGNED NOT NULL,
      funcionario_id INT UNSIGNED DEFAULT NULL,
      data_escala DATE NOT NULL,
      turno_id INT UNSIGNED DEFAULT NULL,
      tipo_dia ENUM('trabalho','folga','feriado','ferias','ausencia','descanso','formacao') NOT NULL DEFAULT 'trabalho',
      minutos_previstos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
      observacoes VARCHAR(255) DEFAULT NULL,
      alterado_por INT UNSIGNED DEFAULT NULL,
      alterado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY(id),
      KEY(idx_hist_funcionario_data) (funcionario_id, data_escala)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    mysqli_query($conn, $sql);
}
