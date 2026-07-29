<?php
$exportar = $_GET['exportar'] ?? '';
if ($exportar === 'xlsx') {
    ob_start();
}

require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';
require_once __DIR__ . '/includes/relatorio_mensal.php';
require_once __DIR__ . '/funcoes/relatorios_horas_funcoes.php';

$utilizadorSessao = require_login($conn);
ac_require_permission($conn, $utilizadorSessao, 'relatorios.consultar');

$anoAtual = (int) date('Y');
$mesAtual = (int) date('n');
$ano = (int) ($_GET['ano'] ?? $anoAtual);
$mes = (int) ($_GET['mes'] ?? $mesAtual);
$funcionarioId = (int) ($_GET['funcionario_id'] ?? 0);
$equipaId = (int) ($_GET['equipa_id'] ?? 0);
$isPrint = $exportar === 'pdf';

if ($ano < 2000 || $ano > 2100) {
    $ano = $anoAtual;
}

if ($mes < 1 || $mes > 12) {
    $mes = $mesAtual;
}

$todosFuncionarios = rm_carregar_funcionarios_relatorio($conn, 0, $equipaId);
$funcionarioIds = array_map('intval', array_keys($todosFuncionarios));

if (!$isPrint && $exportar !== 'xlsx') {
    if ($funcionarioId <= 0 || !in_array($funcionarioId, $funcionarioIds, true)) {
        $funcionarioId = $funcionarioIds[0] ?? 0;
    }
}

$relatorioFuncionarioId = (($isPrint || $exportar === '') && $funcionarioId > 0) ? $funcionarioId : 0;
$relatorioEquipaId = $relatorioFuncionarioId > 0 ? 0 : $equipaId;
$relatorio = rm_carregar_relatorio_mensal($conn, $ano, $mes, $relatorioFuncionarioId, $relatorioEquipaId, $utilizadorSessao['nome'] ?? 'Utilizador');

if ($exportar === 'xlsx') {
    ac_require_permission($conn, $utilizadorSessao, 'relatorios.exportar');
    rm_exportar_xlsx($relatorio, sprintf('relatorio_mensal_%04d_%02d.xlsx', $ano, $mes));
}

if ($isPrint) {
    ac_require_permission($conn, $utilizadorSessao, 'relatorios.exportar');
}

$equipas = [];
if (rm_table_exists($conn, 'equipas')) {
    $res = mysqli_query($conn, 'SELECT id, nome FROM equipas WHERE ativo = 1 ORDER BY nome ASC');
    while ($row = mysqli_fetch_assoc($res)) {
        $equipas[] = $row;
    }
}

$totais = $relatorio['totais_equipa'];
?>
<!DOCTYPE html>
<html lang="pt">
<?php include 'includes/head.php'; ?>

<body class="<?php echo $isPrint ? 'print-report' : ''; ?>">
    <div class="wrapper">
        <?php if (!$isPrint): ?>
            <?php include 'includes/sidebar.php'; ?>
        <?php endif; ?>

        <div class="main-panel">
            <?php if (!$isPrint): ?>
                <div class="main-header">
                    <?php include 'includes/header.php'; ?>
                </div>
            <?php endif; ?>

            <div class="container">
                <div class="page-inner">
                    <?php if ($isPrint): ?>
                        <div class="print-footer">Página <span class="page-number"></span></div>
                    <?php endif; ?>
                    <div class="report-header mb-4">
                        <div>
                            <h3 class="fw-bold mb-1"><?php echo e($relatorio['meta']['instituicao']); ?></h3>
                            <h4 class="mb-1">Relatório mensal de assiduidade</h4>
                            <div class="text-muted">
                                Período: <?php echo e($relatorio['meta']['periodo']); ?> ·
                                Gerado em <?php echo e(rm_formatar_data($relatorio['meta']['gerado_em']) . ' ' . date('H:i', strtotime($relatorio['meta']['gerado_em']))); ?> ·
                                Utilizador: <?php echo e($relatorio['meta']['gerado_por']); ?>
                            </div>
                        </div>
                        <?php if (!$isPrint): ?>
                            <div class="ms-auto d-flex gap-2 no-print">
                                <a class="btn btn-secondary" href="relatorios_horas.php?<?php echo http_build_query(['ano' => $ano, 'mes' => $mes, 'equipa_id' => $equipaId, 'exportar' => 'pdf']); ?>" target="_blank">
                                    <i class="fa fa-print"></i> Imprimir geral
                                </a>
                                <a class="btn btn-primary" href="relatorios_horas.php?<?php echo http_build_query(['ano' => $ano, 'mes' => $mes, 'equipa_id' => $equipaId, 'exportar' => 'xlsx']); ?>">
                                    <i class="fa fa-download"></i> Excel .xlsx
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!$isPrint): ?>
                        <div class="card mb-4 no-print">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Filtros</h4>
                            </div>
                            <div class="card-body">
                                <form method="get" class="row g-3 align-items-end">
                                    <div class="col-md-2">
                                        <label class="form-label">Mês</label>
                                        <select name="mes" class="form-select">
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo $i === $mes ? 'selected' : ''; ?>><?php echo e(sprintf('%02d', $i)); ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Ano</label>
                                        <input type="number" name="ano" class="form-control" min="2000" max="2100" value="<?php echo (int) $ano; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Equipa</label>
                                        <select name="equipa_id" class="form-select">
                                            <option value="0">Todas</option>
                                            <?php foreach ($equipas as $equipa): ?>
                                                <option value="<?php echo (int) $equipa['id']; ?>" <?php echo (int) $equipa['id'] === $equipaId ? 'selected' : ''; ?>><?php echo e($equipa['nome']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Funcionario</label>
                                        <select name="funcionario_id" class="form-select js-submit-on-change">
                                            <?php if (empty($todosFuncionarios)): ?>
                                                <option value="0">Sem funcionarios</option>
                                            <?php endif; ?>
                                            <?php foreach ($todosFuncionarios as $funcionarioOpcao): ?>
                                                <option value="<?php echo (int) $funcionarioOpcao['id']; ?>" <?php echo (int) $funcionarioOpcao['id'] === $funcionarioId ? 'selected' : ''; ?>>
                                                    <?php echo e($funcionarioOpcao['nome']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary w-100"><i class="fa fa-filter"></i> Ver</button>
                                        <?php if ($funcionarioId > 0): ?>
                                            <a class="btn btn-secondary" href="relatorios_horas.php?<?php echo http_build_query(['ano' => $ano, 'mes' => $mes, 'funcionario_id' => $funcionarioId, 'exportar' => 'pdf']); ?>" target="_blank" title="Imprimir funcionario">
                                                <i class="fa fa-print"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                    <?php endif; ?>

                    <div class="row">
                        <div class="col-sm-6 col-lg-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <p class="card-category">Horas previstas</p>
                                    <h4 class="card-title"><?php echo e(rm_formatar_minutos($totais['minutos_previstos'])); ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <p class="card-category">Horas trabalhadas</p>
                                    <h4 class="card-title"><?php echo e(rm_formatar_minutos($totais['minutos_trabalhados'])); ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <p class="card-category">Horas extra</p>
                                    <h4 class="card-title"><?php echo e(rm_formatar_minutos($totais['minutos_extra'])); ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <p class="card-category">Incidências pendentes</p>
                                    <h4 class="card-title"><?php echo (int) $totais['incidencias_pendentes']; ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($relatorioFuncionarioId > 0): ?>
                        <?php $funcionario = reset($relatorio['funcionarios']); ?>
                        <?php if (!$funcionario): ?>
                            <div class="alert alert-info">Funcionário não encontrado para os filtros selecionados.</div>
                        <?php else: ?>
                            <?php include __DIR__ . '/includes/relatorio_mensal_individual_view.php'; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php include __DIR__ . '/includes/relatorio_mensal_equipa_view.php'; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$isPrint): ?>
                <?php include 'includes/footer.php'; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$isPrint): ?>
        <?php include 'includes/scripts.php'; ?>
    <?php endif; ?>
    <style>
        .report-header {
            align-items: flex-start;
            display: flex;
            gap: 16px;
            justify-content: space-between;
        }

        .report-table {
            font-size: 12px;
        }

        .report-table th,
        .report-table td {
            white-space: nowrap;
        }

        @media print {
            @page {
                margin: 12mm;
                size: A4 landscape;
            }

            .no-print,
            .sidebar,
            .main-header,
            footer {
                display: none !important;
            }

            .main-panel,
            .container,
            .page-inner {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }

            .card {
                border: 0 !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }

            .report-table {
                font-size: 10px;
            }

            .page-break {
                page-break-before: always;
            }

            .print-footer {
                bottom: 0;
                color: #666;
                font-size: 10px;
                position: fixed;
                right: 0;
            }

            .page-number:after {
                content: counter(page);
            }
        }
    </style>
    <?php if ($isPrint): ?>
        <script>window.addEventListener('load', function () { window.print(); });</script>
    <?php else: ?>
        <script>
            $(document).ready(function () {
                $('.js-submit-on-change').on('change', function () {
                    this.form.submit();
                });

                $('.report-datatable').DataTable({
                    pageLength: 31,
                    order: [[0, 'asc']],
                    language: {
                        search: 'Pesquisar:',
                        lengthMenu: 'Mostrar _MENU_ registos',
                        info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
                        infoEmpty: 'Sem registos',
                        zeroRecords: 'Nenhum registo encontrado',
                        paginate: { first: 'Primeiro', last: 'Último', next: 'Seguinte', previous: 'Anterior' }
                    }
                });
            });
        </script>
    <?php endif; ?>
</body>
</html>
