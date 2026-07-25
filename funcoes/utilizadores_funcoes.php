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
