<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/funcoes/permissoes_funcoes.php';

$utilizadorSessao = require_login($conn);
ac_require_permission($conn, $utilizadorSessao, 'permissoes.gerir');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

if (!ac_permissions_ready($conn)) {
    ac_abort_403('Execute a migração de controlo de acesso antes de gerir permissões.');
}

$permissoes = [];
$res = mysqli_query($conn, 'SELECT id, codigo, nome FROM permissoes ORDER BY codigo ASC');
while ($row = mysqli_fetch_assoc($res)) {
    $permissoes[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ac_require_permission($conn, $utilizadorSessao, 'permissoes.gerir');

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        ac_abort_403('Token CSRF inválido.');
    }

    $acao = $_POST['acao'] ?? '';

    if ($acao === 'guardar_utilizador') {
        $utilizadorId = (int) ($_POST['utilizador_id'] ?? 0);
        $permissoesSelecionadas = array_values(array_unique(array_filter(array_map('intval', $_POST['permissoes'] ?? []))));

        if ($utilizadorId <= 0) {
            permissoes_redirect('danger', 'Utilizador inválido.');
        }

        $idsPorCodigo = [];
        foreach ($permissoes as $permissao) {
            $idsPorCodigo[$permissao['codigo']] = (int) $permissao['id'];
        }
        $mantemGestao = isset($idsPorCodigo['utilizadores.gerir'], $idsPorCodigo['permissoes.gerir'])
            && in_array($idsPorCodigo['utilizadores.gerir'], $permissoesSelecionadas, true)
            && in_array($idsPorCodigo['permissoes.gerir'], $permissoesSelecionadas, true);

        if (!$mantemGestao && ac_active_admin_count($conn, $utilizadorId) === 0) {
            permissoes_redirect('danger', 'Não é possível deixar o sistema sem utilizador ativo com permissões de gestão.');
        }

        if ($utilizadorId === (int) $utilizadorSessao['id']) {
            $stmt = mysqli_prepare($conn, 'SELECT id FROM permissoes WHERE codigo = "permissoes.gerir" LIMIT 1');
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $permGerir = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if ($permGerir && !in_array((int) $permGerir['id'], $permissoesSelecionadas, true) && ($_POST['confirmar_auto_revogacao'] ?? '') !== '1') {
                permissoes_redirect('danger', 'Confirme explicitamente a remoção da sua permissão de gerir permissões.');
            }
        }

        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, 'DELETE FROM utilizador_permissoes WHERE utilizador_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $utilizadorId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, 'INSERT IGNORE INTO utilizador_permissoes (utilizador_id, permissao_id, efeito) VALUES (?, ?, ?)');
            foreach ($permissoes as $permissao) {
                $permissaoId = (int) $permissao['id'];
                $efeito = in_array($permissaoId, $permissoesSelecionadas, true) ? 'permitir' : 'negar';
                mysqli_stmt_bind_param($stmt, 'iis', $utilizadorId, $permissaoId, $efeito);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);

            ac_log($conn, (int) $utilizadorSessao['id'], 'guardar_permissoes_utilizador', 'permissoes', 'utilizador_permissoes', $utilizadorId, 'Permissões do utilizador atualizadas.', null, ['permissoes' => $permissoesSelecionadas]);
            mysqli_commit($conn);
            permissoes_redirect('success', 'Permissões do utilizador guardadas.');
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            permissoes_redirect('danger', 'Não foi possível guardar permissões.');
        }
    }
}

$utilizadores = [];
$res = mysqli_query($conn, "SELECT id, nome, email FROM utilizadores WHERE estado = 'ativo' ORDER BY nome ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $utilizadores[] = $row;
}

$selectedUserId = (int) ($_GET['utilizador_id'] ?? ($utilizadores[0]['id'] ?? 0));
$userOverrides = [];
if ($selectedUserId > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT permissao_id, efeito FROM utilizador_permissoes WHERE utilizador_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $selectedUserId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $userOverrides[(int) $row['permissao_id']] = $row['efeito'];
    }
    mysqli_stmt_close($stmt);

    if (count($userOverrides) < count($permissoes) && ac_table_exists($conn, 'papel_permissoes')) {
        $stmt = mysqli_prepare($conn, 'SELECT pp.permissao_id
            FROM utilizador_papeis up
            INNER JOIN papel_permissoes pp ON pp.papel_id = up.papel_id
            WHERE up.utilizador_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $selectedUserId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $pid = (int) $row['permissao_id'];
            if (!isset($userOverrides[$pid])) {
                $userOverrides[$pid] = 'permitir';
            }
        }
        mysqli_stmt_close($stmt);
    }
}

$alertType = $_GET['type'] ?? '';
$alertMessage = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt">
<?php include 'includes/head.php'; ?>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-panel">
        <div class="main-header"><?php include 'includes/header.php'; ?></div>
        <div class="container">
            <div class="page-inner">
                <div class="page-header">
                    <h3 class="fw-bold mb-3">Permissões</h3>
                </div>
                <?php if ($alertMessage !== ''): ?>
                    <div class="alert alert-<?php echo e($alertType ?: 'info'); ?>"><?php echo e($alertMessage); ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header"><h4 class="card-title">Permissões por utilizador</h4></div>
                    <div class="card-body">
                        <form method="get" class="mb-4">
                            <label class="form-label">Utilizador</label>
                            <select name="utilizador_id" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($utilizadores as $u): ?>
                                    <option value="<?php echo (int) $u['id']; ?>" <?php echo (int) $u['id'] === $selectedUserId ? 'selected' : ''; ?>><?php echo e($u['nome']); ?> - <?php echo e($u['email']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="acao" value="guardar_utilizador">
                            <input type="hidden" name="utilizador_id" value="<?php echo (int) $selectedUserId; ?>">
                            <div class="row">
                                <?php foreach ($permissoes as $permissao): ?>
                                    <div class="col-md-4 mb-2">
                                        <label class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permissoes[]" value="<?php echo (int) $permissao['id']; ?>" <?php echo ($userOverrides[(int) $permissao['id']] ?? '') === 'permitir' ? 'checked' : ''; ?>>
                                            <span class="form-check-label"><?php echo e(ac_permission_label($permissao['codigo'], $permissao['nome'])); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($selectedUserId === (int) $utilizadorSessao['id']): ?>
                                <label class="form-check my-3">
                                    <input class="form-check-input" type="checkbox" name="confirmar_auto_revogacao" value="1">
                                    <span class="form-check-label">Confirmo alterações que possam retirar o meu acesso administrativo</span>
                                </label>
                            <?php endif; ?>
                            <button class="btn btn-primary" type="submit">Guardar permissões</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>
<?php include 'includes/scripts.php'; ?>
</body>
</html>
