<?php
require_once __DIR__ . '/notificacoes.php';

$nomeUtilizadorTopo = $utilizadorSessao['nome'] ?? ($_SESSION['utilizador_nome'] ?? 'Utilizador');
$emailUtilizadorTopo = $utilizadorSessao['email'] ?? '';
$fotoUtilizadorTopo = trim((string) ($utilizadorSessao['foto'] ?? ''));
if ($fotoUtilizadorTopo !== '' && preg_match('#^(https?:)?//#i', $fotoUtilizadorTopo)) {
    $fotoUtilizadorTopo = '';
}
$notificacoesTopoTotal = 0;
if (isset($conn) && isset($utilizadorSessao) && nt_schema_ready($conn) && ac_can($conn, (int) $utilizadorSessao['id'], 'notificacoes.ver')) {
    $notificacoesTopoTotal = nt_count_unread($conn, (int) $utilizadorSessao['id']);
}
?>
<!-- Navbar Header -->
<nav class="navbar navbar-header navbar-header-transparent navbar-expand-lg border-bottom">
    <div class="container-fluid">
        <div class="navbar-form nav-search p-0 d-none d-lg-flex">
            <div class="fw-bold text-muted">Centro Social Nossa Senhora Auxiliadora</div>
        </div>

        <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">
            <li class="nav-item">
                <a class="nav-link" href="notificacoes.php" title="Notificações">
                    <i class="fas fa-bell"></i>
                    <?php if ($notificacoesTopoTotal > 0): ?>
                        <span class="notification"><?php echo (int) $notificacoesTopoTotal; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item topbar-user dropdown hidden-caret">
                <a class="dropdown-toggle profile-pic" data-bs-toggle="dropdown" href="#" aria-expanded="false">
                    <div class="avatar-sm">
                        <?php if ($fotoUtilizadorTopo !== ''): ?>
                            <img src="<?php echo e($fotoUtilizadorTopo); ?>" alt="<?php echo e($nomeUtilizadorTopo); ?>" class="avatar-img rounded-circle border border-white">
                        <?php else: ?>
                            <span class="avatar-title rounded-circle border border-white bg-primary">
                                <?php echo e(strtoupper(substr($nomeUtilizadorTopo, 0, 1))); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <span class="profile-username">
                        <span class="op-7">Olá,</span>
                        <span class="fw-bold"><?php echo e($nomeUtilizadorTopo); ?></span>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-user animated fadeIn">
                    <div class="dropdown-user-scroll scrollbar-outer">
                        <li>
                            <div class="user-box">
                                <div class="avatar-lg">
                                    <?php if ($fotoUtilizadorTopo !== ''): ?>
                                        <img src="<?php echo e($fotoUtilizadorTopo); ?>" alt="<?php echo e($nomeUtilizadorTopo); ?>" class="avatar-img rounded">
                                    <?php else: ?>
                                        <span class="avatar-title rounded bg-primary">
                                            <?php echo e(strtoupper(substr($nomeUtilizadorTopo, 0, 1))); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="u-text">
                                    <h4><?php echo e($nomeUtilizadorTopo); ?></h4>
                                    <?php if ($emailUtilizadorTopo !== ''): ?>
                                        <p class="text-muted"><?php echo e($emailUtilizadorTopo); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <li>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="utilizadores.php">Utilizadores</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="logout.php">Sair</a>
                        </li>
                    </div>
                </ul>
            </li>
        </ul>
    </div>
</nav>
<!-- End Navbar -->

