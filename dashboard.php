<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';
require_once __DIR__ . '/includes/notificacoes.php';
require_once __DIR__ . '/funcoes/dashboard_funcoes.php';

$utilizadorSessao = require_login($conn);
ac_require_permission($conn, $utilizadorSessao, 'ponto.consultar');

$estadoFuncionarios = fe_carregar_funcionarios_estado($conn);
$funcionarios = $estadoFuncionarios['funcionarios'];
$totais = $estadoFuncionarios['totais'];
$missingTables = $estadoFuncionarios['missing_tables'];
$notificacoesReady = nt_schema_ready($conn);
$notificacoesDashboard = [];
$podeVerIdades = false;
if ($notificacoesReady && ac_can($conn, (int) $utilizadorSessao['id'], 'notificacoes.ver')) {
    nt_generate_notifications($conn);
    $notificacoesDashboard = nt_list_notifications($conn, (int) $utilizadorSessao['id'], 6, false, false);
    $podeVerIdades = ac_can_any($conn, (int) $utilizadorSessao['id'], ['funcionarios.dados_sensiveis', 'funcionarios.ver_idade']);
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
                        <h3 class="fw-bold mb-3">Presenças de Funcionários</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="dashboard.php">
                                    <i class="icon-home"></i>
                                </a>
                            </li>
                            <li class="separator">
                                <i class="icon-arrow-right"></i>
                            </li>
                            <li class="nav-item">
                                <a href="dashboard.php">Estado atual</a>
                            </li>
                        </ul>
                    </div>

                    <?php if (!empty($missingTables)): ?>
                        <div class="alert alert-warning" role="alert">
                            Faltam tabelas de base: <strong><?php echo e(implode(', ', $missingTables)); ?></strong>.
                            Execute o schema <code>database/schema_completo.sql</code>.
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-primary bubble-shadow-small">
                                                <i class="fas fa-users"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Funcionários ativos</p>
                                                <h4 class="card-title"><?php echo (int) $totais['ativos']; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-success bubble-shadow-small">
                                                <i class="fas fa-user-check"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">A trabalhar</p>
                                                <h4 class="card-title"><?php echo (int) $totais['a_trabalhar']; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-warning bubble-shadow-small">
                                                <i class="fas fa-coffee"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Em pausa</p>
                                                <h4 class="card-title"><?php echo (int) $totais['em_pausa']; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-secondary bubble-shadow-small">
                                                <i class="fas fa-user-clock"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Ausentes</p>
                                                <h4 class="card-title"><?php echo (int) $totais['nao_trabalhar']; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($notificacoesReady): ?>
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex align-items-center">
                                    <h4 class="card-title">Aniversários e diuturnidades</h4>
                                    <a class="btn btn-outline-primary btn-sm ms-auto" href="notificacoes.php">
                                        Ver todas
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($notificacoesDashboard)): ?>
                                    <p class="text-muted mb-0">Sem notificações ativas para os próximos períodos.</p>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($notificacoesDashboard as $item): ?>
                                            <?php
                                            $badge = $item['tipo'] === 'aniversario' ? 'primary' : 'warning';
                                            $icon = $item['tipo'] === 'aniversario' ? 'fa-birthday-cake' : 'fa-award';
                                            $age = $item['tipo'] === 'aniversario' && $podeVerIdades ? dashboard_idade_evento($item['data_nascimento'], $item['data_evento']) : null;
                                            ?>
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div class="border rounded p-3 h-100">
                                                    <div class="d-flex align-items-start">
                                                        <span class="avatar-title rounded-circle bg-<?php echo e($badge); ?> me-3">
                                                            <i class="fas <?php echo e($icon); ?>"></i>
                                                        </span>
                                                        <div>
                                                            <div class="fw-bold"><?php echo e($item['funcionario_nome']); ?></div>
                                                            <div class="small text-muted">
                                                                <?php echo e(date('d/m/Y', strtotime($item['data_evento']))); ?>
                                                                <?php if ($age !== null): ?>
                                                                    &middot; <?php echo (int) $age; ?> anos
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="small mt-1"><?php echo e($item['mensagem']); ?></div>
                                                            <?php if ($item['lida_at'] === null): ?>
                                                                <span class="badge badge-primary mt-2">Não lida</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">Estado dos funcionários hoje</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-funcionarios-estado" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>N.º mec.</th>
                                            <th>Funcionário</th>
                                            <th>Equipa</th>
                                            <th>Último movimento</th>
                                            <th>Hora</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($funcionarios as $funcionario): ?>
                                            <tr>
                                                <td><?php echo e($funcionario['numero_mecanografico'] ?: '-'); ?></td>
                                                <td>
                                                    <div class="fw-bold"><?php echo e($funcionario['nome']); ?></div>
                                                    <small class="text-muted"><?php echo e($funcionario['funcao'] ?: 'Sem função definida'); ?></small>
                                                </td>
                                                <td><?php echo e($funcionario['equipa_nome'] ?: '-'); ?></td>
                                                <td><?php echo e(fe_movimento_label($funcionario['ultimo_tipo'])); ?></td>
                                                <td>
                                                    <?php echo $funcionario['ultimo_data_hora'] ? e(date('H:i', strtotime($funcionario['ultimo_data_hora']))) : '-'; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo e(fe_estado_badge($funcionario['estado_trabalho'])); ?>">
                                                        <?php echo e(fe_estado_label($funcionario['estado_trabalho'])); ?>
                                                    </span>
                                                </td>
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
            $('#tabela-funcionarios-estado').DataTable({
                pageLength: 25,
                language: {
                    search: 'Pesquisar:',
                    lengthMenu: 'Mostrar _MENU_ registos',
                    info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
                    infoEmpty: 'Sem registos',
                    zeroRecords: 'Nenhum funcionário encontrado',
                    paginate: {
                        first: 'Primeiro',
                        last: 'Último',
                        next: 'Seguinte',
                        previous: 'Anterior'
                    }
                }
            });
        });
    </script>
</body>

</html>

