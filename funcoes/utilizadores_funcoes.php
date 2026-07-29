<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_with_message($type, $message)
{
    header('Location: utilizadores.php?' . http_build_query([
        'type' => $type,
        'message' => $message,
    ]));
    exit;
}

function get_post_value($key)
{
    return trim($_POST[$key] ?? '');
}

function nullable_int($value)
{
    return $value === '' ? null : (int) $value;
}

function utilizadores_column_exists($conn, $table, $column)
{
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function utilizadores_foto_column_ready($conn)
{
    static $ready = null;

    if ($ready === null) {
        $ready = utilizadores_column_exists($conn, 'utilizadores', 'foto');
    }

    return $ready;
}

function utilizadores_funcionario_link_ready($conn)
{
    static $ready = null;

    if ($ready === null) {
        $ready = utilizadores_column_exists($conn, 'utilizadores', 'funcionario_id')
            && utilizadores_column_exists($conn, 'funcionarios', 'utilizador_id');
    }

    return $ready;
}

function utilizadores_carregar_funcionarios_associaveis($conn)
{
    if (!utilizadores_funcionario_link_ready($conn)) {
        return [];
    }

    $sql = "SELECT f.id, f.nome, f.numero_mecanografico, f.estado, f.utilizador_id, u.nome AS utilizador_nome
            FROM funcionarios f
            LEFT JOIN utilizadores u ON u.id = f.utilizador_id
            WHERE f.estado <> 'inativo'
            ORDER BY f.nome ASC";
    $result = mysqli_query($conn, $sql);
    $funcionarios = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $funcionarios[(int) $row['id']] = $row;
        }
    }

    return $funcionarios;
}

function utilizadores_sincronizar_funcionario($conn, $utilizadorId, $funcionarioId)
{
    if (!utilizadores_funcionario_link_ready($conn)) {
        return;
    }

    $utilizadorId = (int) $utilizadorId;
    $funcionarioId = (int) $funcionarioId;

    if ($funcionarioId > 0) {
        $stmt = mysqli_prepare($conn, 'SELECT id, utilizador_id FROM funcionarios WHERE id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $funcionarioId);
        mysqli_stmt_execute($stmt);
        $funcionario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$funcionario) {
            throw new RuntimeException('Ficha de funcionario invalida.');
        }

        $funcionarioUtilizadorId = (int) ($funcionario['utilizador_id'] ?? 0);
        if ($funcionarioUtilizadorId > 0 && $funcionarioUtilizadorId !== $utilizadorId) {
            throw new RuntimeException('Esta ficha de funcionario ja esta associada a outro utilizador.');
        }

        $stmt = mysqli_prepare($conn, 'SELECT id FROM utilizadores WHERE funcionario_id = ? AND id <> ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'ii', $funcionarioId, $utilizadorId);
        mysqli_stmt_execute($stmt);
        $utilizadorComFuncionario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($utilizadorComFuncionario) {
            throw new RuntimeException('Esta ficha de funcionario ja esta associada a outro utilizador.');
        }
    }

    $stmt = mysqli_prepare($conn, 'UPDATE funcionarios SET utilizador_id = NULL WHERE utilizador_id = ? AND id <> ?');
    mysqli_stmt_bind_param($stmt, 'ii', $utilizadorId, $funcionarioId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($funcionarioId > 0) {
        $stmt = mysqli_prepare($conn, 'UPDATE utilizadores SET funcionario_id = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $funcionarioId, $utilizadorId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, 'UPDATE funcionarios SET utilizador_id = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $utilizadorId, $funcionarioId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return;
    }

    $stmt = mysqli_prepare($conn, 'UPDATE utilizadores SET funcionario_id = NULL WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $utilizadorId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function utilizadores_foto_path($foto)
{
    $foto = trim((string) $foto);
    if ($foto === '') {
        return '';
    }

    if (preg_match('#^(https?:)?//#i', $foto)) {
        return '';
    }

    return $foto;
}

function utilizadores_foto_url($foto)
{
    return utilizadores_foto_path($foto);
}

function utilizadores_apagar_foto($foto)
{
    $foto = utilizadores_foto_path($foto);
    if ($foto === '' || strpos($foto, 'uploads/utilizadores/') !== 0) {
        return;
    }

    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $foto);
    if (is_file($path)) {
        unlink($path);
    }
}

function utilizadores_guardar_foto($file, $fotoAtual = '')
{
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $fotoAtual;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Nao foi possivel carregar a fotografia.');
    }

    if ($file['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('A fotografia nao pode exceder 3 MB.');
    }

    $extensoesPermitidas = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!isset($extensoesPermitidas[$mime])) {
        throw new RuntimeException('Formato de fotografia invalido. Use JPG, PNG ou WebP.');
    }

    $diretorio = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'utilizadores';
    if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true)) {
        throw new RuntimeException('Nao foi possivel preparar a pasta de fotografias.');
    }

    $nomeSeguro = 'utilizador_' . bin2hex(random_bytes(16)) . '.' . $extensoesPermitidas[$mime];
    $destino = $diretorio . DIRECTORY_SEPARATOR . $nomeSeguro;

    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        throw new RuntimeException('Nao foi possivel guardar a fotografia.');
    }

    utilizadores_apagar_foto($fotoAtual);

    return 'uploads/utilizadores/' . $nomeSeguro;
}

function render_utilizador_avatar(array $utilizador, $size = 'sm')
{
    $nome = $utilizador['nome'] ?? '';
    $foto = utilizadores_foto_url($utilizador['foto'] ?? '');
    $classe = $size === 'lg' ? 'avatar-lg' : 'avatar-sm';
    $rounded = $size === 'lg' ? 'rounded' : 'rounded-circle';

    echo '<div class="' . $classe . '">';
    if ($foto !== '') {
        echo '<img src="' . e($foto) . '" alt="' . e($nome) . '" class="avatar-img ' . $rounded . '">';
    } else {
        echo '<span class="avatar-title ' . $rounded . ' bg-primary">' . e(strtoupper(substr($nome ?: 'U', 0, 1))) . '</span>';
    }
    echo '</div>';
}

function render_funcionario_associacao_select(array $funcionarios, $utilizadorId = 0, $selectedId = 0)
{
    echo '<select name="funcionario_id" class="form-select">';
    echo '<option value="">Sem ficha associada</option>';

    foreach ($funcionarios as $funcionario) {
        $funcionarioId = (int) $funcionario['id'];
        $linkedUserId = (int) ($funcionario['utilizador_id'] ?? 0);
        $disabled = $linkedUserId > 0 && $linkedUserId !== (int) $utilizadorId;
        $selected = $funcionarioId === (int) $selectedId ? ' selected' : '';
        $label = $funcionario['nome'];

        if (!empty($funcionario['numero_mecanografico'])) {
            $label .= ' (' . $funcionario['numero_mecanografico'] . ')';
        }

        if ($disabled) {
            $label .= ' - associado a ' . ($funcionario['utilizador_nome'] ?: 'outro utilizador');
        }

        echo '<option value="' . $funcionarioId . '"' . $selected . ($disabled ? ' disabled' : '') . '>' . e($label) . '</option>';
    }

    echo '</select>';
}

function permissoes_postadas()
{
    return array_values(array_unique(array_filter(array_map('intval', $_POST['permissoes'] ?? []))));
}

function guardar_permissoes_utilizador($conn, $utilizadorId, array $permissoesSelecionadas, array $permissoesDisponiveis)
{
    $stmt = mysqli_prepare($conn, 'DELETE FROM utilizador_permissoes WHERE utilizador_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $utilizadorId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (empty($permissoesDisponiveis)) {
        return;
    }

    $stmt = mysqli_prepare($conn, 'INSERT IGNORE INTO utilizador_permissoes (utilizador_id, permissao_id, efeito) VALUES (?, ?, ?)');
    foreach ($permissoesDisponiveis as $permissao) {
        $permissaoId = (int) $permissao['id'];
        $efeito = in_array($permissaoId, $permissoesSelecionadas, true) ? 'permitir' : 'negar';
        mysqli_stmt_bind_param($stmt, 'iis', $utilizadorId, $permissaoId, $efeito);
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);
}

function permissoes_ids_por_codigo(array $permissoes)
{
    $ids = [];
    foreach ($permissoes as $permissao) {
        $ids[$permissao['codigo']] = (int) $permissao['id'];
    }
    return $ids;
}

function tem_permissao_id(array $permissoesSelecionadas, array $idsPorCodigo, $codigo)
{
    return isset($idsPorCodigo[$codigo]) && in_array($idsPorCodigo[$codigo], $permissoesSelecionadas, true);
}

function render_permissoes_checkboxes(array $permissoes, array $selecionadas = [])
{
    foreach ($permissoes as $permissao) {
        $id = (int) $permissao['id'];
        $checked = isset($selecionadas[$id]) ? 'checked' : '';
        echo '<div class="col-md-6 mb-2">';
        echo '<label class="form-check">';
        echo '<input class="form-check-input" type="checkbox" name="permissoes[]" value="' . $id . '" ' . $checked . '>';
        echo '<span class="form-check-label">' . htmlspecialchars(ac_permission_label($permissao['codigo'], $permissao['nome']), ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</label>';
        echo '</div>';
    }
}
