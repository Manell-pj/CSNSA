<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';

$utilizadorSessao = require_login($conn);
ac_require_permission($conn, $utilizadorSessao, 'logs.consultar');

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$logs = [];
if (ac_table_exists($conn, 'logs_sistema')) {
    $sql = "SELECT l.*, u.nome AS utilizador_nome
            FROM logs_sistema l
            LEFT JOIN utilizadores u ON u.id = l.utilizador_id
            ORDER BY l.created_at DESC
            LIMIT 300";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $logs[] = $row;
    }
    mysqli_stmt_close($stmt);
}
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
                    <h3 class="fw-bold mb-3">Logs</h3>
                </div>
                <div class="card">
                    <div class="card-header"><h4 class="card-title">Auditoria</h4></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tabela-logs" class="display table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Utilizador</th>
                                        <th>Ação</th>
                                        <th>Módulo</th>
                                        <th>Descrição</th>
                                        <th>IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo e(date('d/m/Y H:i:s', strtotime($log['created_at']))); ?></td>
                                            <td><?php echo e($log['utilizador_nome'] ?: '-'); ?></td>
                                            <td><?php echo e($log['acao']); ?></td>
                                            <td><?php echo e($log['modulo'] ?: '-'); ?></td>
                                            <td><?php echo e($log['descricao'] ?: '-'); ?></td>
                                            <td><?php echo e($log['ip'] ?: '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>
<?php include 'includes/scripts.php'; ?>
<script>
$(document).ready(function () {
    $('#tabela-logs').DataTable({ pageLength: 25, order: [[0, 'desc']] });
});
</script>
</body>
</html>
