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
