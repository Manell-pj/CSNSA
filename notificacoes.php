<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notificacoes.php';

$utilizadorSessao = require_login($conn);
ac_require_permission($conn, $utilizadorSessao, 'notificacoes.ver');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function notificacoes_redirect($type, $message)
{
    header('Location: notificacoes.php?' . http_build_query([
        'type' => $type,
        'message' => $message,
    ]));
    exit;
}

function nt_format_date_pt($date)
{
    return $date ? date('d/m/Y', strtotime($date)) : '-';
}

function nt_age_at_event($birthDate, $eventDate)
{
    if (!$birthDate || !$eventDate) {
        return null;
    }

    return (new DateTimeImmutable($birthDate))->diff(new DateTimeImmutable($eventDate))->y;
}

$schemaReady = nt_schema_ready($conn);
if ($schemaReady) {
    nt_generate_notifications($conn);
}

$canView = $schemaReady && ac_can($conn, (int) $utilizadorSessao['id'], 'notificacoes.ver');
$canManage = $schemaReady && ac_can($conn, (int) $utilizadorSessao['id'], 'notificacoes.gerir');
$canViewAge = $schemaReady && ac_can_any($conn, (int) $utilizadorSessao['id'], ['funcionarios.dados_sensiveis', 'funcionarios.ver_idade']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$schemaReady) {
        notificacoes_redirect('danger', 'Execute a migração de notificações antes de usar esta página.');
    }

    if (!$canView) {
        notificacoes_redirect('danger', 'Não tem permissão para gerir notificações.');
    }

    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        notificacoes_redirect('danger', 'Token CSRF inválido.');
    }

    $acao = $_POST['acao'] ?? '';
    $notificacaoId = (int) ($_POST['notificacao_id'] ?? 0);

    if ($acao === 'marcar_lida' && $notificacaoId > 0) {
        nt_mark_read($conn, $notificacaoId, (int) $utilizadorSessao['id'], true);
        notificacoes_redirect('success', 'Notificação marcada como lida.');
    }

    if ($acao === 'marcar_nao_lida' && $notificacaoId > 0) {
        nt_mark_read($conn, $notificacaoId, (int) $utilizadorSessao['id'], false);
        notificacoes_redirect('success', 'Notificação marcada como não lida.');
    }

    if ($acao === 'confirmar_diuturnidade' && $notificacaoId > 0) {
        ac_require_permission($conn, $utilizadorSessao, 'notificacoes.gerir');

        $observacoes = trim($_POST['observacoes'] ?? '');
        $ok = nt_confirm_diuturnidade($conn, $notificacaoId, (int) $utilizadorSessao['id'], $observacoes !== '' ? $observacoes : null);
        notificacoes_redirect($ok ? 'success' : 'danger', $ok ? 'Diuturnidade confirmada e registada no histórico.' : 'Não foi possível confirmar a diuturnidade.');
    }

    if ($acao === 'guardar_config') {
        ac_require_permission($conn, $utilizadorSessao, 'notificacoes.gerir');

        nt_set_config_int($conn, 'aniversarios_dias_aviso', $_POST['aniversarios_dias_aviso'] ?? 30);
        nt_set_config_int($conn, 'diuturnidades_dias_aviso', $_POST['diuturnidades_dias_aviso'] ?? 60);
        nt_set_config_int($conn, 'diuturnidades_ciclo_anos', $_POST['diuturnidades_ciclo_anos'] ?? 5);
        nt_generate_notifications($conn);
        notificacoes_redirect('success', 'Configuração guardada.');
    }
}

$showInativos = ($_GET['show_inativos'] ?? '') === '1';
$onlyUnread = ($_GET['estado'] ?? '') === 'nao_lidas';
$notificacoes = $canView ? nt_list_notifications($conn, (int) $utilizadorSessao['id'], 200, $showInativos, $onlyUnread) : [];
$unreadCount = $canView ? nt_count_unread($conn, (int) $utilizadorSessao['id']) : 0;
$alertType = $_GET['type'] ?? '';
$alertMessage = $_GET['message'] ?? '';

$config = [
    'aniversarios_dias_aviso' => $schemaReady ? nt_config_int($conn, 'aniversarios_dias_aviso', 30) : 30,
    'diuturnidades_dias_aviso' => $schemaReady ? nt_config_int($conn, 'diuturnidades_dias_aviso', 60) : 60,
    'diuturnidades_ciclo_anos' => $schemaReady ? nt_config_int($conn, 'diuturnidades_ciclo_anos', 5) : 5,
];

$historico = [];
if ($schemaReady && $canView) {
    $sql = "SELECT da.*, f.nome AS funcionario_nome, u.nome AS confirmado_por_nome
            FROM diuturnidades_atribuicoes da
            INNER JOIN funcionarios f ON f.id = da.funcionario_id
            LEFT JOIN utilizadores u ON u.id = da.confirmado_por
            ORDER BY da.confirmado_at DESC
            LIMIT 50";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $historico[] = $row;
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
            <div class="main-header">
                <?php include 'includes/header.php'; ?>
            </div>

            <div class="container">
                <div class="page-inner">
                    <div class="page-header">
                        <h3 class="fw-bold mb-3">Notificações</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home"><a href="principal.php"><i class="icon-home"></i></a></li>
                            <li class="separator"><i class="icon-arrow-right"></i></li>
                            <li class="nav-item"><a href="notificacoes.php">Aniversários e diuturnidades</a></li>
                        </ul>
                    </div>

                    <?php if ($alertMessage !== ''): ?>
                        <div class="alert alert-<?php echo e($alertType ?: 'info'); ?> alert-dismissible fade show" role="alert">
                            <?php echo e($alertMessage); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!$schemaReady): ?>
                        <div class="alert alert-warning">
                            Execute a migração <code>database/2026_07_16_notificacoes_aniversarios_diuturnidades.sql</code> para ativar notificações.
                        </div>
                    <?php elseif (!$canView): ?>
                        <div class="alert alert-danger">Não tem permissão para ver notificações.</div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <h4 class="card-title">Lista de notificações</h4>
                                                <p class="card-category"><?php echo (int) $unreadCount; ?> por ler</p>
                                            </div>
                                            <div class="ms-auto d-flex gap-2">
                                                <a class="btn btn-outline-secondary btn-sm" href="notificacoes.php?show_inativos=<?php echo $showInativos ? '0' : '1'; ?>&estado=<?php echo $onlyUnread ? 'nao_lidas' : 'todas'; ?>">
                                                    <?php echo $showInativos ? 'Ocultar inativos' : 'Mostrar inativos'; ?>
                                                </a>
                                                <a class="btn btn-outline-primary btn-sm" href="notificacoes.php?show_inativos=<?php echo $showInativos ? '1' : '0'; ?>&estado=<?php echo $onlyUnread ? 'todas' : 'nao_lidas'; ?>">
                                                    <?php echo $onlyUnread ? 'Mostrar todas' : 'Só não lidas'; ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($notificacoes)): ?>
                                            <p class="text-muted mb-0">Sem notificações para os filtros atuais.</p>
                                        <?php endif; ?>

                                        <?php foreach ($notificacoes as $item): ?>
                                            <?php
                                            $isUnread = $item['lida_at'] === null;
                                            $badge = $item['tipo'] === 'aniversario' ? 'primary' : 'warning';
                                            $icon = $item['tipo'] === 'aniversario' ? 'fa-birthday-cake' : 'fa-award';
                                            $age = $item['tipo'] === 'aniversario' && $canViewAge ? nt_age_at_event($item['data_nascimento'], $item['data_evento']) : null;
                                            ?>
                                            <div class="d-flex align-items-start border-bottom py-3 <?php echo $isUnread ? 'bg-light' : ''; ?>">
                                                <div class="avatar-sm me-3">
                                                    <span class="avatar-title rounded-circle bg-<?php echo e($badge); ?>">
                                                        <i class="fas <?php echo e($icon); ?>"></i>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                                        <strong><?php echo e($item['titulo']); ?></strong>
                                                        <?php if ($isUnread): ?><span class="badge badge-primary">Não lida</span><?php endif; ?>
                                                        <?php if ($item['funcionario_estado'] === 'inativo'): ?><span class="badge badge-secondary">Inativo</span><?php endif; ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        Evento: <?php echo e(nt_format_date_pt($item['data_evento'])); ?>
                                                        &middot; Criada em <?php echo e(date('d/m/Y H:i', strtotime($item['created_at']))); ?>
                                                        <?php if ($age !== null): ?>&middot; <?php echo (int) $age; ?> anos<?php endif; ?>
                                                    </div>
                                                    <p class="mb-2 mt-1"><?php echo e($item['mensagem']); ?></p>
                                                    <div class="d-flex gap-2 flex-wrap">
                                                        <form method="post">
                                                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="notificacao_id" value="<?php echo (int) $item['id']; ?>">
                                                            <input type="hidden" name="acao" value="<?php echo $isUnread ? 'marcar_lida' : 'marcar_nao_lida'; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                                <?php echo $isUnread ? 'Marcar lida' : 'Marcar não lida'; ?>
                                                            </button>
                                                        </form>

                                                        <?php if ($item['tipo'] === 'diuturnidade' && $canManage): ?>
                                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#confirmarDiuturnidade<?php echo (int) $item['id']; ?>">
                                                                Confirmar atribuição
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <?php if ($canManage): ?>
                                    <div class="card">
                                        <div class="card-header">
                                            <h4 class="card-title">Configuração</h4>
                                        </div>
                                        <div class="card-body">
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="acao" value="guardar_config">
                                                <div class="mb-3">
                                                    <label class="form-label">Dias de aviso para aniversários</label>
                                                    <input type="number" min="1" max="365" name="aniversarios_dias_aviso" class="form-control" value="<?php echo (int) $config['aniversarios_dias_aviso']; ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Dias de aviso para diuturnidades</label>
                                                    <input type="number" min="1" max="365" name="diuturnidades_dias_aviso" class="form-control" value="<?php echo (int) $config['diuturnidades_dias_aviso']; ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Anos por ciclo</label>
                                                    <input type="number" min="1" max="80" name="diuturnidades_ciclo_anos" class="form-control" value="<?php echo (int) $config['diuturnidades_ciclo_anos']; ?>">
                                                </div>
                                                <button type="submit" class="btn btn-primary w-100">Guardar</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="card">
                                    <div class="card-header">
                                        <h4 class="card-title">Histórico de diuturnidades</h4>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($historico)): ?>
                                            <p class="text-muted mb-0">Sem atribuições confirmadas.</p>
                                        <?php endif; ?>
                                        <?php foreach ($historico as $row): ?>
                                            <div class="border-bottom py-2">
                                                <div class="fw-bold"><?php echo e($row['funcionario_nome']); ?></div>
                                                <div class="small text-muted">
                                                    Ciclo <?php echo (int) $row['ciclo_numero']; ?> de <?php echo (int) $row['ciclo_anos']; ?> ano(s)
                                                    &middot; venc. <?php echo e(nt_format_date_pt($row['data_vencimento'])); ?>
                                                </div>
                                                <div class="small">Confirmado por <?php echo e($row['confirmado_por_nome'] ?: '-'); ?> em <?php echo e(date('d/m/Y H:i', strtotime($row['confirmado_at']))); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <?php foreach ($notificacoes as $item): ?>
        <?php if ($item['tipo'] !== 'diuturnidade' || !$canManage) { continue; } ?>
        <div class="modal fade" id="confirmarDiuturnidade<?php echo (int) $item['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="post" class="modal-content">
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="acao" value="confirmar_diuturnidade">
                    <input type="hidden" name="notificacao_id" value="<?php echo (int) $item['id']; ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Confirmar diuturnidade</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2"><strong><?php echo e($item['funcionario_nome']); ?></strong></p>
                        <p class="text-muted">A confirmação grava histórico. Não altera salário nem remuneração.</p>
                        <div class="mb-3">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-primary">Confirmar</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <?php include 'includes/scripts.php'; ?>
</body>

</html>

