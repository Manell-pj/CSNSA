<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/funcoes/escala_mensal_funcoes.php';

$utilizadorSessao = require_login($conn);
ac_require_permission($conn, $utilizadorSessao, 'escalas.gerir');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$contexto = escala_mensal_contexto_request();
$ano = $contexto['ano'];
$mes = $contexto['mes'];
$equipaId = $contexto['equipa_id'];
$diasNoMes = $contexto['dias_no_mes'];
$baseParams = escala_mensal_base_params($contexto);
$tiposDia = escala_mensal_tipos_dia();
$missingTables = escala_mensal_tabelas_em_falta($conn);

escala_mensal_processar_post($conn, $contexto, $missingTables);

$dadosEscala = escala_mensal_carregar_dados($conn, $contexto, $missingTables);
$equipas = $dadosEscala['equipas'];
$turnos = $dadosEscala['turnos'];
$funcionarios = $dadosEscala['funcionarios'];
$escalaGuardada = $dadosEscala['escala_guardada'];
$setores = $dadosEscala['setores'] ?? [];
$turnosById = [];
foreach ($turnos as $t) {
    $turnosById[(int)$t['id']] = $t;
}
$codigosTipoDia = [
    'folga' => 'F',
    'ferias' => 'FE',
    'falta' => 'A',
    'baixa' => 'B',
    'licenca_amamentacao' => 'L',
];

function obterCodigoVisualTurno(array $turno): string
{
    $entrada = substr((string) ($turno['hora_entrada'] ?? ''), 0, 5);
    $saida = substr((string) ($turno['hora_saida'] ?? ''), 0, 5);
    $codigoOriginal = strtoupper(trim((string) ($turno['codigo'] ?? '')));
    $nomeOriginal = strtoupper(trim((string) ($turno['nome'] ?? '')));
    $chaveHorario = str_replace(':', '', $entrada) . '_' . str_replace(':', '', $saida);
    $chaveOriginal = preg_replace('/[^A-Z0-9_]/', '_', $codigoOriginal ?: $nomeOriginal);

    $mapa = [
        '0000_0800' => 'N',
        '0800_1600' => 'M',
        '1200_2000' => 'T',
        '1600_0000' => 'N2',
        '0800_1400' => 'M1',
        '0830_1630' => 'M2',
        '0900_1730' => 'A1',
        'T_0000_0800' => 'N',
        'T_0800_1600' => 'M',
        'T_1200_2000' => 'T',
        'T_1600_0000' => 'N2',
        'T_0800_1400' => 'M1',
        'T_0830_1630' => 'M2',
        'T_0900_1230_1400_1730' => 'A1',
    ];

    if (isset($mapa[$chaveOriginal])) {
        return $mapa[$chaveOriginal];
    }

    if (isset($mapa[$chaveHorario])) {
        return $mapa[$chaveHorario];
    }

    if ($codigoOriginal !== '') {
        return substr($codigoOriginal, 0, 3);
    }

    return substr($nomeOriginal ?: 'T', 0, 3);
}

$equipaSelecionadaNome = 'Todas as equipas';
foreach ($equipas as $equipa) {
    if ((int) $equipa['id'] === (int) $equipaId) {
        $equipaSelecionadaNome = $equipa['nome'];
        break;
    }
}
$setorSelecionadoNome = 'Todos os setores';
foreach ($setores as $setor) {
    if ((int) $setor['id'] === (int) ($contexto['setor_id'] ?? 0)) {
        $setorSelecionadoNome = $setor['nome'];
        break;
    }
}
$mesAnoLabel = month_name($mes) . ' ' . (int) $ano;
$totalColunasEscala = $diasNoMes + 3;
$hojeAno = (int) date('Y');
$hojeMes = (int) date('n');
$hojeDia = (int) date('j');
$alertType = $_GET['type'] ?? '';
$alertMessage = $_GET['message'] ?? '';
$headExtraStyle = '
    .escala-print-area,
    .escala-print-logo,
    .print-footer {
        display: none;
    }
';
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

            <div class="container-fluid">
                <div class="page-inner">
                    <div class="page-header">
                        <h3 class="fw-bold mb-3">Escala Mensal</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="principal.php">
                                    <i class="icon-home"></i>
                                </a>
                            </li>
                            <li class="separator">
                                <i class="icon-arrow-right"></i>
                            </li>
                            <li class="nav-item">
                                <a href="escala_mensal.php">Escala Mensal</a>
                            </li>
                        </ul>
                    </div>

                    <?php if ($alertMessage !== ''): ?>
                        <div class="alert alert-<?php echo e($alertType ?: 'info'); ?> alert-dismissible fade show" role="alert">
                            <?php echo e($alertMessage); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($missingTables)): ?>
                        <div class="alert alert-warning" role="alert">
                            Faltam tabelas de base: <strong><?php echo e(implode(', ', $missingTables)); ?></strong>.
                            Execute o schema <code>database/schema_completo.sql</code> antes de usar esta página.
                        </div>
                    <?php endif; ?>

                    <div class="print-footer">Página <span class="page-number"></span></div>

                    <section id="areaImpressaoEscala" class="escala-print-area" aria-label="Impressão da escala mensal">
                        <header class="report-header mb-4 escala-print-header">
                            <img src="assets/img/csnsa/logo-nsa.png" alt="Centro Social Nossa Senhora Auxiliadora" class="escala-print-header-logo">
                            <div>
                                <h3 class="fw-bold mb-1">Centro Social Nossa Senhora Auxiliadora</h3>
                                <h4 class="mb-1">Escala mensal</h4>
                                <div class="text-muted">
                                    Período: <?php echo e($mesAnoLabel); ?> ·
                                    Setor: <?php echo e($setorSelecionadoNome); ?> ·
                                    Equipa: <?php echo e($equipaSelecionadaNome); ?> ·
                                    <?php echo (int) $diasNoMes; ?> dias
                                </div>
                            </div>
                        </header>

                        <?php if (!empty($funcionarios)): ?>
                            <table class="escala-print-table" style="--dias-mes: <?php echo (int) $diasNoMes; ?>;">
                                <colgroup>
                                    <col class="print-col-funcionario">
                                    <col class="print-col-categoria">
                                    <?php for ($dia = 1; $dia <= $diasNoMes; $dia++): ?>
                                        <col class="print-col-dia">
                                    <?php endfor; ?>
                                </colgroup>
                                <thead>
                                    <tr class="print-weekdays-row">
                                        <th class="print-funcionario-header" rowspan="2">Funcionário</th>
                                        <th class="print-categoria-header" rowspan="2">Equipa</th>
                                        <?php for ($dia = 1; $dia <= $diasNoMes; $dia++): ?>
                                            <?php
                                            $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                                            $diaSemana = weekday_short($data);
                                            $classesDiaPrint = [];
                                            $ehFimSemanaPrint = in_array(date('N', strtotime($data)), [6, 7], true);
                                            if ($ehFimSemanaPrint) {
                                                $classesDiaPrint[] = 'print-fim-semana';
                                            } else {
                                                $classesDiaPrint[] = 'print-dia-util';
                                            }
                                            $styleDiaPrint = $ehFimSemanaPrint ? 'background: #ffffff !important; background-color: #ffffff !important; border-color: #b8b8b8 !important; color: #9a9a9a !important; font-weight: 400 !important;' : '';
                                            ?>
                                            <th class="print-dia-header <?php echo e(implode(' ', $classesDiaPrint)); ?>" style="<?php echo e($styleDiaPrint); ?>">
                                                <?php echo e(substr($diaSemana, 0, 1)); ?>
                                            </th>
                                        <?php endfor; ?>
                                    </tr>
                                    <tr class="print-days-row">
                                        <?php for ($dia = 1; $dia <= $diasNoMes; $dia++): ?>
                                            <?php
                                            $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                                            $classesDiaPrint = [];
                                            $ehFimSemanaPrint = in_array(date('N', strtotime($data)), [6, 7], true);
                                            if ($ehFimSemanaPrint) {
                                                $classesDiaPrint[] = 'print-fim-semana';
                                            } else {
                                                $classesDiaPrint[] = 'print-dia-util';
                                            }
                                            $styleDiaPrint = $ehFimSemanaPrint ? 'background: #ffffff !important; background-color: #ffffff !important; border-color: #b8b8b8 !important; color: #9a9a9a !important; font-weight: 400 !important;' : '';
                                            ?>
                                            <th class="print-dia-numero <?php echo e(implode(' ', $classesDiaPrint)); ?>" style="<?php echo e($styleDiaPrint); ?>">
                                                <?php echo (int) $dia; ?>
                                            </th>
                                        <?php endfor; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($funcionarios as $funcionario): ?>
                                        <tr>
                                            <th class="print-funcionario"><?php echo e($funcionario['nome']); ?></th>
                                            <td class="print-categoria"><?php echo e($funcionario['equipa_nome'] ?: '-'); ?></td>
                                            <?php for ($dia = 1; $dia <= $diasNoMes; $dia++): ?>
                                                <?php
                                                $registo = $escalaGuardada[(int) $funcionario['id']][$dia] ?? [];
                                                $tipoDia = $registo['tipo_dia'] ?? 'turno';
                                                $turnoSelecionado = (int) ($registo['turno_id'] ?? 0);
                                                $folgaTrabalhada = (int) ($registo['folga_trabalhada'] ?? 0) === 1;
                                                $mostraTurnoNoDia = $tipoDia === 'turno' || $tipoDia === 'substituicao' || $folgaTrabalhada;
                                                $turnoInfo = $mostraTurnoNoDia ? ($turnosById[$turnoSelecionado] ?? null) : null;
                                                $codigoTurno = $turnoInfo ? obterCodigoVisualTurno($turnoInfo) : '';
                                                $codigoDia = $codigoTurno ?: ($tipoDia === 'turno' ? '-' : ($codigosTipoDia[$tipoDia] ?? '-'));
                                                $classesCelulaPrint = ['print-tipo-' . $tipoDia];
                                                $dataCelula = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                                                $ehFimSemanaPrint = in_array(date('N', strtotime($dataCelula)), [6, 7], true);
                                                if ($ehFimSemanaPrint) {
                                                    $classesCelulaPrint[] = 'print-fim-semana';
                                                } else {
                                                    $classesCelulaPrint[] = 'print-dia-util';
                                                }
                                                if ($folgaTrabalhada) {
                                                    $classesCelulaPrint[] = 'print-folga-trabalhada';
                                                }
                                                $styleCelulaPrint = $ehFimSemanaPrint ? 'background: #ffffff !important; background-color: #ffffff !important; border-color: #b8b8b8 !important; color: #9a9a9a !important; font-weight: 400 !important;' : '';
                                                ?>
                                                <td class="print-dia-cell <?php echo e(implode(' ', $classesCelulaPrint)); ?>" style="<?php echo e($styleCelulaPrint); ?>">
                                                    <?php echo e($codigoDia); ?><?php echo $tipoDia === 'substituicao' ? 'S' : ''; ?><?php echo $folgaTrabalhada ? '+' : ''; ?>
                                                </td>
                                            <?php endfor; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <section class="escala-print-legendas" aria-label="Legendas da escala">
                                <div class="escala-print-legenda-bloco">
                                    <h5>Horários</h5>
                                    <table>
                                        <tbody>
                                            <?php foreach ($turnos as $t): ?>
                                                <tr>
                                                    <th><?php echo e(obterCodigoVisualTurno($t)); ?></th>
                                                    <td><?php echo e(substr($t['hora_entrada'], 0, 5) . ' às ' . substr($t['hora_saida'], 0, 5)); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="escala-print-legenda-bloco">
                                    <h5>Estados</h5>
                                    <table>
                                        <tbody>
                                            <tr><th>F</th><td>Folga</td></tr>
                                            <tr><th>FE</th><td>Férias</td></tr>
                                            <tr><th>A</th><td>Falta</td></tr>
                                            <tr><th>B</th><td>Baixa</td></tr>
                                            <tr><th>S</th><td>Substituição</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        <?php endif; ?>
                    </section>

                    <div class="escala-screen-area">
                    <section class="escala-folha-header mb-3">
                        <img src="assets/img/csnsa/logo-nsa.png" alt="Centro Social Nossa Senhora Auxiliadora" class="escala-print-logo">
                        <div>
                            <div class="escala-instituicao">Centro Social Nossa Senhora Auxiliadora</div>
                            <h2>Escala Mensal</h2>
                            <div class="escala-periodo"><?php echo e($mesAnoLabel); ?></div>
                        </div>
                        <div class="escala-contexto">
                            <span>Setor: <strong><?php echo e($setorSelecionadoNome); ?></strong></span>
                            <span>Equipa: <strong><?php echo e($equipaSelecionadaNome); ?></strong></span>
                            <span><?php echo (int) $diasNoMes; ?> dias</span>
                        </div>
                    </section>

                    <div class="card mb-3 escala-filtros-card">
                        <div class="card-body">
                            <form method="get" id="filtrosEscala" class="row g-3 align-items-end">
                                <div class="col-md-2">
                                    <label class="form-label">Mês</label>
                                    <select name="mes" class="form-select">
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $i === $mes ? 'selected' : ''; ?>>
                                                <?php echo e(month_name($i)); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Ano</label>
                                    <input type="number" name="ano" class="form-control" min="2000" max="2100" value="<?php echo (int) $ano; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Equipa</label>
                                    <select name="equipa_id" class="form-select">
                                        <option value="0">Todas as equipas</option>
                                        <?php foreach ($equipas as $equipa): ?>
                                            <option value="<?php echo (int) $equipa['id']; ?>" <?php echo (int) $equipa['id'] === $equipaId ? 'selected' : ''; ?>>
                                                <?php echo e($equipa['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="acao" value="guardar">
                        <input type="hidden" name="mes" value="<?php echo (int) $mes; ?>">
                        <input type="hidden" name="ano" value="<?php echo (int) $ano; ?>">
                        <input type="hidden" name="equipa_id" value="<?php echo (int) $equipaId; ?>">
                        <input type="hidden" name="setor_id" value="<?php echo (int) ($contexto['setor_id'] ?? 0); ?>">

                        <section class="escala-paper">
                            <div class="escala-paper-actions">
                                <strong>Horários - Serviços Comuns - <?php echo e($mesAnoLabel); ?></strong>
                                <span class="escala-selection-count text-muted">0 selecionados</span>
                                <button type="button" class="btn btn-light btn-sm escala-print-btn" onclick="imprimirEscalaMensal()" title="Imprimir escala">
                                    <i class="fa fa-print"></i>
                                    Imprimir
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" id="btnOpenBulkAssign" <?php echo !empty($missingTables) ? 'disabled' : ''; ?> title="Atribuir a selecionados">
                                    <i class="fa fa-tasks"></i>
                                    Atribuir selecionados
                                </button>
                                <button type="submit" class="btn btn-success btn-sm" <?php echo !empty($missingTables) ? 'disabled' : ''; ?>>
                                    <i class="fa fa-save"></i>
                                    Guardar escala
                                </button>
                            </div>
                            <div class="escala-paper-body">
                                <?php if (empty($funcionarios)): ?>
                                    <div class="alert alert-info mb-0">
                                        Nenhum funcionário encontrado para os filtros selecionados.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive escala-wrapper">
                                        <table class="table table-bordered table-sm align-middle escala-table" style="--dias-mes: <?php echo (int) $diasNoMes; ?>;">
                                            <colgroup>
                                                <col class="escala-col-select">
                                                <col class="escala-col-funcionario">
                                                <col class="escala-col-categoria">
                                                <?php for ($dia = 1; $dia <= $diasNoMes; $dia++): ?>
                                                    <col class="escala-col-dia">
                                                <?php endfor; ?>
                                            </colgroup>
                                            <thead>
                                                <tr>
                                                    <th class="escala-select-col"><input type="checkbox" id="select_all_rows"></th>
                                                    <th class="escala-sticky-col escala-funcionario-col">Funcionário</th>
                                                    <th class="escala-categoria-col">Categoria</th>
                                                    <?php for ($dia = 1; $dia <= $diasNoMes; $dia++): ?>
                                                        <?php
                                                        $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                                                        $diaSemana = weekday_short($data);
                                                        $classesDiaHeader = [];
                                                        if (in_array(date('N', strtotime($data)), [6, 7], true)) {
                                                            $classesDiaHeader[] = 'escala-fim-semana';
                                                        }
                                                        if ((int) $ano === $hojeAno && (int) $mes === $hojeMes && (int) $dia === $hojeDia) {
                                                            $classesDiaHeader[] = 'escala-hoje';
                                                        }
                                                        ?>
                                                        <th class="text-center escala-dia-header <?php echo e(implode(' ', $classesDiaHeader)); ?>" title="<?php echo e($diaSemana . ' ' . $dia); ?>">
                                                            <span><?php echo e(substr($diaSemana, 0, 1)); ?></span>
                                                            <strong><?php echo $dia; ?></strong>
                                                        </th>
                                                    <?php endfor; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $grupoAtual = null; ?>
                                                <?php foreach ($funcionarios as $funcionario): ?>
                                                    <?php
                                                    $grupoLinha = ($funcionario['equipa_nome'] ?? '') ?: 'Sem equipa';
                                                    if ($grupoLinha !== $grupoAtual):
                                                        $grupoAtual = $grupoLinha;
                                                    ?>
                                                        <tr class="escala-grupo-row">
                                                            <td colspan="<?php echo (int) $totalColunasEscala; ?>">
                                                                <?php echo e($grupoAtual); ?>
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                    <tr>
                                                        <td class="escala-select-col"><input type="checkbox" class="select_row" name="selected_funcionarios[]" value="<?php echo (int)$funcionario['id']; ?>"></td>
                                                        <th class="escala-sticky-col escala-funcionario-col escala-funcionario">
                                                            <div class="fw-bold"><?php echo e($funcionario['nome']); ?></div>
                                                            <small class="text-muted">
                                                                <?php echo e($funcionario['numero_mecanografico'] ?: 'Sem número'); ?>
                                                                <?php if (!empty($funcionario['equipa_nome'])): ?>
                                                                    · <?php echo e($funcionario['equipa_nome']); ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        </th>
                                                        <td class="escala-categoria-col"><?php echo e($funcionario['funcao'] ?: '-'); ?></td>
                                                        <?php for ($dia = 1; $dia <= $diasNoMes; $dia++): ?>
                                                            <?php
                                                            $registo = $escalaGuardada[(int) $funcionario['id']][$dia] ?? [];
                                                            $tipoDia = $registo['tipo_dia'] ?? 'turno';
                                                            $turnoSelecionado = (int) ($registo['turno_id'] ?? 0);
                                                            $substituiSelecionado = (int) ($registo['substitui_funcionario_id'] ?? 0);
                                                            $folgaTrabalhada = (int) ($registo['folga_trabalhada'] ?? 0) === 1;
                                                            $observacoes = $registo['observacoes'] ?? '';
                                                            $mostraTurnoNoDia = $tipoDia === 'turno' || $tipoDia === 'substituicao' || $folgaTrabalhada;
                                                            $turnoInfo = $mostraTurnoNoDia ? ($turnosById[$turnoSelecionado] ?? null) : null;
                                                            $codigoTurno = $turnoInfo ? obterCodigoVisualTurno($turnoInfo) : '';
                                                            $codigoDia = $codigoTurno ?: ($tipoDia === 'turno' ? '-' : ($codigosTipoDia[$tipoDia] ?? '-'));
                                                            $labelDia = $turnoInfo
                                                                ? ($turnoInfo['nome'] . ' (' . substr($turnoInfo['hora_entrada'], 0, 5) . '-' . substr($turnoInfo['hora_saida'], 0, 5) . ')')
                                                                : tipo_label($tipoDia);
                                                            $cellClasses = ['tipo-' . $tipoDia];
                                                            if ((int) $ano === $hojeAno && (int) $mes === $hojeMes && (int) $dia === $hojeDia) {
                                                                $cellClasses[] = 'escala-hoje';
                                                            }

                                                            if ($folgaTrabalhada) {
                                                                $cellClasses[] = 'escala-folga-trabalhada';
                                                            }

                                                            if ($tipoDia === 'substituicao') {
                                                                $cellClasses[] = 'escala-substituicao';
                                                            }

                                                            if ($observacoes !== '') {
                                                                $cellClasses[] = 'tem-observacoes';
                                                            }
                                                            ?>
                                                            <td class="escala-cell <?php echo e(implode(' ', $cellClasses)); ?>" data-dia="<?php echo $dia; ?>" data-funcionario="<?php echo e($funcionario['nome']); ?>">
                                                                <button type="button" class="escala-cell-trigger" title="<?php echo e($labelDia); ?>" aria-label="<?php echo e($funcionario['nome'] . ', dia ' . $dia . ': ' . $labelDia); ?>">
                                                                    <span class="escala-cell-code"><?php echo e($codigoDia); ?></span>
                                                                    <?php if ($tipoDia === 'substituicao'): ?><span class="escala-cell-flag">S</span><?php endif; ?>
                                                                    <?php if ($folgaTrabalhada): ?><span class="escala-cell-flag">+</span><?php endif; ?>
                                                                </button>
                                                                <div class="escala-editor">
                                                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                                                        <strong>Dia <?php echo $dia; ?></strong>
                                                                        <button type="button" class="btn btn-link btn-sm p-0 escala-editor-close">Fechar</button>
                                                                    </div>
                                                                    <select name="escala[<?php echo (int) $funcionario['id']; ?>][<?php echo $dia; ?>][tipo_dia]" class="form-select form-select-sm escala-tipo">
                                                                        <?php foreach ($tiposDia as $tipo): ?>
                                                                            <option value="<?php echo e($tipo); ?>" <?php echo $tipo === $tipoDia ? 'selected' : ''; ?>>
                                                                                <?php echo e(tipo_label($tipo)); ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>

                                                                    <select name="escala[<?php echo (int) $funcionario['id']; ?>][<?php echo $dia; ?>][turno_id]" class="form-select form-select-sm mt-2 escala-turno">
                                                                        <option value="">Sem turno</option>
                                                                        <?php foreach ($turnos as $turno): ?>
                                                                            <option value="<?php echo (int) $turno['id']; ?>" data-code="<?php echo e(obterCodigoVisualTurno($turno)); ?>" data-label="<?php echo e($turno['nome'] . ' (' . substr($turno['hora_entrada'], 0, 5) . '-' . substr($turno['hora_saida'], 0, 5) . ')'); ?>" <?php echo (int) $turno['id'] === $turnoSelecionado ? 'selected' : ''; ?>>
                                                                                <?php echo e(obterCodigoVisualTurno($turno)); ?> · <?php echo e(substr($turno['hora_entrada'], 0, 5) . '-' . substr($turno['hora_saida'], 0, 5)); ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>

                                                                    <select name="escala[<?php echo (int) $funcionario['id']; ?>][<?php echo $dia; ?>][substitui_funcionario_id]" class="form-select form-select-sm mt-2 escala-substitui">
                                                                        <option value="">Substitui...</option>
                                                                        <?php foreach ($funcionarios as $opcaoFuncionario): ?>
                                                                            <?php if ((int) $opcaoFuncionario['id'] === (int) $funcionario['id']) {
                                                                                continue;
                                                                            } ?>
                                                                            <option value="<?php echo (int) $opcaoFuncionario['id']; ?>" <?php echo (int) $opcaoFuncionario['id'] === $substituiSelecionado ? 'selected' : ''; ?>>
                                                                                <?php echo e($opcaoFuncionario['nome']); ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>

                                                                    <div class="form-check mt-2 escala-folga-check">
                                                                        <input class="form-check-input escala-folga-trabalhada-check" type="checkbox" name="escala[<?php echo (int) $funcionario['id']; ?>][<?php echo $dia; ?>][folga_trabalhada]" id="folga<?php echo (int) $funcionario['id']; ?>_<?php echo $dia; ?>" <?php echo $folgaTrabalhada ? 'checked' : ''; ?>>
                                                                        <label class="form-check-label" for="folga<?php echo (int) $funcionario['id']; ?>_<?php echo $dia; ?>">Folga trabalhada</label>
                                                                    </div>

                                                                    <input type="text" name="escala[<?php echo (int) $funcionario['id']; ?>][<?php echo $dia; ?>][observacoes]" class="form-control form-control-sm mt-2 escala-observacoes" placeholder="Observações" value="<?php echo e($observacoes); ?>">
                                                                </div>
                                                            </td>
                                                        <?php endfor; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <section class="escala-legenda-folha" aria-label="Legenda de turnos e estados">
                                        <div class="escala-legenda-bloco">
                                            <h5>Horários</h5>
                                            <table class="escala-legenda-table">
                                                <tbody>
                                                    <?php foreach ($turnos as $t): ?>
                                                        <tr>
                                                            <th><?php echo e(obterCodigoVisualTurno($t)); ?></th>
                                                            <td><?php echo e(substr($t['hora_entrada'], 0, 5) . ' às ' . substr($t['hora_saida'], 0, 5)); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="escala-legenda-bloco">
                                            <h5>Estados</h5>
                                            <table class="escala-legenda-table">
                                                <tbody>
                                                    <tr><th>F</th><td>Folga</td></tr>
                                                    <tr><th>FE</th><td>Férias</td></tr>
                                                    <tr><th>A</th><td>Falta</td></tr>
                                                    <tr><th>B</th><td>Baixa</td></tr>
                                                    <tr><th>S</th><td>Substituição</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </section>
                                <?php endif; ?>
                            </div>
                        </section>
                    </form>
                    </div>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <!-- Bulk assign modal -->
    <div class="modal fade" id="modalBulkAssign" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" class="modal-content">
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="acao" value="bulk_assign">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Atribuição em massa</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Esta ação irá aplicar um turno/estado aos funcionários selecionados.</p>
                    <div class="mb-3">
                        <label class="form-label">Turno</label>
                        <select name="turno_id" class="form-select">
                            <option value="">Sem turno</option>
                            <?php foreach ($turnos as $t): ?>
                                <option value="<?php echo (int)$t['id']; ?>"><?php echo e($t['codigo'] ?: $t['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo</label>
                        <select name="tipo_dia" class="form-select">
                            <?php foreach ($tiposDia as $tipo): ?>
                                <option value="<?php echo e($tipo); ?>"><?php echo e(tipo_label($tipo)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Dia início</label>
                            <input type="number" name="dia_inicio" class="form-control" min="1" max="<?php echo $diasNoMes; ?>" value="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dia fim</label>
                            <input type="number" name="dia_fim" class="form-control" min="1" max="<?php echo $diasNoMes; ?>" value="<?php echo $diasNoMes; ?>">
                        </div>
                    </div>
                    <input type="hidden" name="funcionario_ids" id="bulk_funcionario_ids" value="">
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Aplicar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    <style>
        .escala-filtros-card {
            border: 1px solid #b9b9b9;
            border-radius: 0;
            box-shadow: none;
            margin-bottom: 10px;
        }

        .escala-filtros-card .card-body {
            padding: 8px 10px;
        }

        .escala-filtros-card .form-label {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .escala-filtros-card .form-control,
        .escala-filtros-card .form-select {
            border-radius: 0;
            font-size: 12px;
            min-height: 30px;
            padding: 3px 6px;
        }

        .escala-paper {
            background: #fff;
            border: 1px solid #555;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            padding: 0;
        }

        .escala-folha-header {
            background: #fff;
            border: 1px solid #555;
            border-bottom: 0;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            margin-bottom: 0 !important;
            padding: 8px 10px;
            text-align: center;
        }

        .escala-instituicao {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .escala-folha-header h2 {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.2;
            margin: 3px 0;
            text-transform: uppercase;
        }

        .escala-periodo {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .escala-contexto {
            display: block;
            font-size: 10px;
            margin-top: 4px;
        }

        .escala-contexto span + span::before {
            content: " | ";
            font-weight: 400;
        }

        .report-header {
            align-items: flex-start;
            display: flex;
            gap: 16px;
            justify-content: space-between;
        }

        .print-footer,
        .escala-print-logo {
            display: none;
        }

        .escala-print-area {
            display: none;
        }

        .escala-paper-actions {
            align-items: center;
            border-bottom: 1px solid #555;
            display: flex;
            gap: 6px;
            padding: 6px 8px;
        }

        .escala-paper-actions strong {
            font-size: 12px;
            margin-right: auto;
            text-transform: uppercase;
        }

        .escala-paper-actions .btn {
            border-radius: 0;
            font-size: 12px;
            padding: 3px 8px;
        }

        .escala-selection-count {
            font-size: 11px;
            white-space: nowrap;
        }

        .escala-paper-body {
            padding: 0;
        }

        .escala-wrapper {
            overflow-x: auto;
            overflow-y: visible;
            width: 100%;
        }

        .escala-table {
            border-collapse: collapse;
            border-spacing: 0;
            font-size: 11px;
            margin: 0;
            table-layout: fixed;
            width: max-content;
        }

        .escala-col-select {
            width: 32px;
        }

        .escala-col-funcionario {
            width: 190px;
        }

        .escala-col-categoria,
        .escala-categoria-col {
            display: none;
        }

        .escala-col-dia {
            width: 32px;
        }

        .escala-table th,
        .escala-table td {
            border: 1px solid #555 !important;
            box-shadow: none;
            padding: 0;
            vertical-align: middle;
            white-space: nowrap;
        }

        .escala-table thead th {
            background: #d8d8d8;
            color: #111;
            font-weight: 700;
            height: 38px;
            text-align: center;
        }

        .escala-select-col {
            background: #fff;
            text-align: center;
            width: 32px;
        }

        .escala-funcionario-col {
            background: #fff;
            min-width: 190px;
            padding: 2px 5px !important;
            text-align: left;
            width: 190px;
        }

        .escala-dia-header {
            font-size: 10px;
            line-height: 1;
            width: 32px;
        }

        .escala-dia-header span,
        .escala-dia-header strong {
            display: block;
        }

        .escala-dia-header strong {
            font-size: 11px;
            margin-top: 4px;
        }

        .escala-fim-semana {
            background: #ececec !important;
        }

        .escala-hoje {
            background: #fff4bc !important;
        }

        .escala-grupo-row td {
            background: #cfcfcf;
            color: #111;
            font-size: 11px;
            font-weight: 700;
            height: 22px;
            padding: 2px 6px !important;
            text-align: left;
            text-transform: uppercase;
        }

        .escala-funcionario {
            height: 31px;
        }

        .escala-funcionario .fw-bold {
            color: #111;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.1;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .escala-funcionario small {
            color: #444 !important;
            display: block;
            font-size: 9px;
            line-height: 1.05;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .escala-cell {
            background: #fff;
            cursor: pointer;
            height: 31px;
            text-align: center;
            width: 32px;
        }

        .escala-cell:hover {
            outline: 2px solid #111;
            outline-offset: -2px;
        }

        .escala-cell-trigger {
            appearance: none;
            background: transparent;
            border: 0;
            color: inherit;
            cursor: pointer;
            display: block;
            font: inherit;
            height: 31px;
            line-height: 31px;
            margin: 0;
            overflow: hidden;
            padding: 0;
            text-align: center;
            width: 32px;
        }

        .escala-cell-code {
            display: inline;
            font-size: 12px;
            font-weight: 700;
        }

        .escala-cell-flag {
            font-size: 8px;
            font-weight: 700;
            margin-left: 1px;
            vertical-align: super;
        }

        .tipo-folga,
        .tipo-ferias,
        .tipo-falta,
        .tipo-baixa,
        .tipo-substituicao,
        .tipo-licenca_amamentacao {
            background: #f5f5f5;
        }

        .tem-observacoes .escala-cell-trigger::before {
            content: "*";
            font-size: 8px;
            margin-right: 1px;
            vertical-align: super;
        }

        .escala-editor {
            background: #fff;
            border: 1px solid #555;
            box-shadow: none;
            display: none;
            min-width: min(260px, calc(100vw - 24px));
            padding: 10px;
            text-align: left;
            white-space: normal;
        }

        .escala-editor-floating {
            display: block !important;
            max-width: min(320px, calc(100vw - 24px));
            position: fixed;
            z-index: 3000;
        }

        .escala-editor .form-select,
        .escala-editor .form-control {
            border-radius: 0;
            font-size: 12px;
        }

        .escala-folga-check {
            font-size: 12px;
            min-height: auto;
        }

        .escala-legenda-folha {
            display: grid;
            gap: 22px;
            grid-template-columns: repeat(auto-fit, minmax(210px, max-content));
            padding: 14px 8px 10px;
        }

        .escala-legenda-bloco h5 {
            color: #111;
            font-size: 11px;
            font-weight: 700;
            margin: 0 0 4px;
            text-transform: uppercase;
        }

        .escala-legenda-table {
            border-collapse: collapse;
            font-size: 11px;
            table-layout: fixed;
            width: 210px;
        }

        .escala-legenda-table th,
        .escala-legenda-table td {
            border: 1px solid #555;
            color: #111;
            height: 22px;
            line-height: 1.1;
            padding: 2px 6px;
            white-space: nowrap;
        }

        .escala-legenda-table th {
            font-weight: 700;
            text-align: center;
            width: 48px;
        }

        .escala-legenda-table td {
            overflow: hidden;
            text-overflow: ellipsis;
            width: 162px;
        }

        @media (max-width: 768px) {
            .escala-paper-actions {
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .escala-paper-actions strong {
                width: 100%;
            }
        }

        @media print {
            @page {
                margin: 12mm;
                size: A4 landscape;
            }

            html,
            body {
                background: #fff !important;
                height: auto !important;
                margin: 0 !important;
                min-height: 0 !important;
                overflow: visible !important;
                padding: 0 !important;
                width: auto !important;
            }

            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .sidebar,
            .main-sidebar,
            .main-header,
            .page-header,
            .breadcrumbs,
            .no-print,
            .escala-screen-area,
            .escala-filtros-card,
            .escala-paper-actions,
            .footer,
            footer,
            nav,
            button,
            .btn,
            .alert,
            .modal,
            .modal-backdrop,
            .dropdown-menu,
            .tooltip {
                display: none !important;
            }

            .wrapper,
            .main-panel,
            .content,
            .container,
            .container-fluid,
            .page-inner {
                background: #fff !important;
                box-shadow: none !important;
                display: block !important;
                float: none !important;
                height: auto !important;
                margin: 0 !important;
                max-width: none !important;
                min-height: 0 !important;
                overflow: visible !important;
                padding: 0 !important;
                position: static !important;
                transform: none !important;
                width: 100% !important;
            }

            .card {
                border: 0 !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }

            #areaImpressaoEscala {
                background: #fff !important;
                box-shadow: none !important;
                box-sizing: border-box !important;
                color: #111 !important;
                display: block !important;
                font-family: Arial, Helvetica, sans-serif !important;
                margin: 0 !important;
                max-width: none !important;
                overflow: visible !important;
                padding: 0 !important;
                position: static !important;
                width: 100% !important;
            }

            .escala-print-header {
                align-items: flex-start !important;
                display: flex !important;
                gap: 5mm !important;
                margin-bottom: 8mm !important;
            }

            .escala-print-header-logo {
                display: block !important;
                flex: 0 0 auto !important;
                height: 18mm !important;
                object-fit: contain !important;
                width: 18mm !important;
            }

            .escala-print-header h3 {
                font-size: 13pt !important;
            }

            .escala-print-header h4 {
                font-size: 11pt !important;
            }

            .escala-print-header .text-muted {
                color: #666 !important;
                font-size: 8pt !important;
            }

            .escala-print-area a {
                color: inherit !important;
                text-decoration: none !important;
            }

            .escala-print-table {
                border-collapse: separate !important;
                border-spacing: 0 !important;
                border-left: 0.3mm solid #333 !important;
                border-top: 0.3mm solid #333 !important;
                font-size: 6.8pt !important;
                line-height: 1 !important;
                margin: 0 !important;
                table-layout: fixed !important;
                width: 100% !important;
            }

            .print-col-funcionario {
                width: 48mm !important;
            }

            .print-col-categoria {
                width: 22mm !important;
            }

            .print-col-dia {
                width: calc((100% - 70mm) / var(--dias-mes)) !important;
            }

            .escala-print-table thead {
                display: table-header-group !important;
            }

            .escala-print-table tr {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            .escala-print-table th,
            .escala-print-table td {
                border: 0 !important;
                border-bottom: 0.3mm solid #333 !important;
                border-right: 0.3mm solid #333 !important;
                box-sizing: border-box !important;
                height: 5.1mm !important;
                overflow: hidden !important;
                padding: 0.3mm 0.45mm !important;
                text-align: center !important;
                vertical-align: middle !important;
                white-space: nowrap !important;
            }

            .escala-print-table tr > th:first-child,
            .escala-print-table tr > td:first-child {
                border-left: 0 !important;
            }

            .escala-print-table thead tr:first-child > th {
                border-top: 0 !important;
            }

            .escala-print-table thead th {
                background: #d9d9d9 !important;
                font-size: 6pt !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
            }

            .print-funcionario-header,
            .print-categoria-header {
                height: 7.2mm !important;
                text-align: left !important;
            }

            .print-dia-header,
            .print-dia-numero {
                font-size: 5.8pt !important;
                height: 3.6mm !important;
                line-height: 1 !important;
                padding: 0.15mm !important;
            }

            .print-funcionario,
            .print-categoria {
                font-size: 6.5pt !important;
                font-weight: 600 !important;
                line-height: 1.05 !important;
                overflow-wrap: anywhere !important;
                text-align: left !important;
                white-space: normal !important;
            }

            .print-dia-cell {
                font-size: 7.2pt !important;
                font-weight: 700 !important;
            }

            .print-fim-semana {
                background: #eeeeee !important;
            }

            .print-tipo-folga,
            .print-tipo-ferias,
            .print-tipo-falta,
            .print-tipo-baixa,
            .print-tipo-substituicao,
            .print-tipo-licenca_amamentacao {
                background: #f2f2f2 !important;
            }

            .print-tipo-falta,
            .print-tipo-baixa {
                background: #dedede !important;
            }

            .print-dia-header.print-dia-util,
            .print-dia-numero.print-dia-util {
                background: #a8a8a8 !important;
            }

            .print-dia-header.print-fim-semana,
            .print-dia-numero.print-fim-semana,
            .print-dia-cell.print-fim-semana {
                background: #ffffff !important;
                background-color: #ffffff !important;
                border-color: #b8b8b8 !important;
                color: #9a9a9a !important;
                font-weight: 400 !important;
            }

            .escala-print-legendas {
                align-items: flex-start !important;
                break-inside: avoid !important;
                display: flex !important;
                gap: 7mm !important;
                margin-top: 2.2mm !important;
                page-break-inside: avoid !important;
            }

            .escala-print-legenda-bloco {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            .escala-print-legenda-bloco h5 {
                font-size: 6.6pt !important;
                font-weight: 700 !important;
                line-height: 1 !important;
                margin: 0 0 0.8mm !important;
                text-transform: uppercase !important;
            }

            .escala-print-legenda-bloco table {
                border-collapse: separate !important;
                border-left: 0.3mm solid #333 !important;
                border-spacing: 0 !important;
                border-top: 0.3mm solid #333 !important;
                font-size: 6.2pt !important;
                table-layout: fixed !important;
                width: 52mm !important;
            }

            .escala-print-legenda-bloco th,
            .escala-print-legenda-bloco td {
                border: 0 !important;
                border-bottom: 0.3mm solid #333 !important;
                border-right: 0.3mm solid #333 !important;
                height: 4.1mm !important;
                line-height: 1 !important;
                padding: 0.35mm 0.8mm !important;
                white-space: nowrap !important;
            }

            .escala-print-legenda-bloco tr:first-child > th,
            .escala-print-legenda-bloco tr:first-child > td {
                border-top: 0 !important;
            }

            .escala-print-legenda-bloco tr > th:first-child,
            .escala-print-legenda-bloco tr > td:first-child {
                border-left: 0 !important;
            }

            .escala-print-legenda-bloco th {
                background: #e6e6e6 !important;
                text-align: center !important;
                width: 13mm !important;
            }

            .escala-print-legenda-bloco td {
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                width: 39mm !important;
            }

            .print-footer {
                bottom: 0;
                color: #666;
                display: block !important;
                font-size: 10px;
                position: fixed;
                right: 0;
            }

            .page-number:after {
                content: counter(page);
            }
        }

    </style>
    <?php include 'includes/scripts.php'; ?>
    <script>
        function imprimirEscalaMensal() {
            var tituloOriginal = document.title;
            var escapeEvent = $.Event('keydown');
            escapeEvent.key = 'Escape';
            $(document).trigger(escapeEvent);
            $('.modal.show').each(function () {
                if (window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(this).hide();
                } else {
                    $(this).modal('hide');
                }
            });
            $('.dropdown-menu.show, .tooltip.show').removeClass('show');
            document.activeElement && document.activeElement.blur();
            document.title = 'Escala mensal - <?php echo e($mesAnoLabel); ?>';
            window.setTimeout(function () {
                window.print();
                window.setTimeout(function () {
                    document.title = tituloOriginal;
                }, 500);
            }, 50);
        }

        $(document).ready(function () {
            var tipoCodigos = {
                folga: 'F',
                ferias: 'FE',
                falta: 'A',
                baixa: 'B',
                licenca_amamentacao: 'L',
                substituicao: 'S'
            };
            var tipoClasses = 'tipo-turno tipo-folga tipo-ferias tipo-falta tipo-baixa tipo-substituicao tipo-licenca_amamentacao';
            var $activeCell = null;
            var $activeEditor = null;

            function editorDaCelula($cell) {
                if ($activeCell && $activeCell[0] === $cell[0] && $activeEditor) {
                    return $activeEditor;
                }
                return $cell.find('.escala-editor').first();
            }

            function atualizarCelula($cell) {
                if (!$cell || !$cell.length) {
                    return;
                }
                var $editor = editorDaCelula($cell);
                var tipo = $editor.find('.escala-tipo').val();
                var $turno = $editor.find('.escala-turno');
                var $turnoSelecionado = $turno.find('option:selected');
                var turnoId = $turno.val();
                var folgaTrabalhada = $editor.find('.escala-folga-trabalhada-check').is(':checked');
                var mostrarSubstitui = tipo === 'substituicao';
                var mostrarTurno = tipo === 'turno' || tipo === 'substituicao' || folgaTrabalhada;
                var codigo = (mostrarTurno && turnoId) ? ($turnoSelecionado.data('code') || $turnoSelecionado.text().trim()) : (tipoCodigos[tipo] || '-');
                var label = (mostrarTurno && turnoId) ? ($turnoSelecionado.data('label') || $turnoSelecionado.text().trim()) : $editor.find('.escala-tipo option:selected').text().trim();
                var temObservacoes = $.trim($editor.find('.escala-observacoes').val()) !== '';

                $cell.removeClass(tipoClasses);
                $cell.addClass('tipo-' + tipo);
                $cell.toggleClass('escala-substituicao', mostrarSubstitui);
                $cell.toggleClass('escala-folga-trabalhada', folgaTrabalhada);
                $cell.toggleClass('tem-observacoes', temObservacoes);
                $editor.find('.escala-substitui').toggle(mostrarSubstitui);
                $editor.find('.escala-turno').toggle(mostrarTurno);
                $cell.find('.escala-cell-code').text(codigo);
                $cell.find('.escala-cell-trigger').attr('title', label).attr('aria-label', ($cell.data('funcionario') || 'Funcionário') + ', dia ' + ($cell.data('dia') || '') + ': ' + label);
                $cell.find('.escala-cell-flag').remove();
                if (mostrarSubstitui) {
                    $cell.find('.escala-cell-trigger').append('<span class="escala-cell-flag">S</span>');
                }
                if (folgaTrabalhada) {
                    $cell.find('.escala-cell-trigger').append('<span class="escala-cell-flag">+</span>');
                }
            }

            function fecharEditor() {
                if ($activeEditor && $activeCell) {
                    $activeEditor.hide().removeClass('escala-editor-floating').appendTo($activeCell);
                    $activeCell.removeClass('editor-open');
                }
                $activeEditor = null;
                $activeCell = null;
            }

            function posicionarEditor() {
                if (!$activeEditor || !$activeCell) {
                    return;
                }

                var rect = $activeCell[0].getBoundingClientRect();
                var maxWidth = Math.max(1, window.innerWidth - 24);
                $activeEditor.css({ maxWidth: Math.min(320, maxWidth) + 'px' });

                var editorWidth = $activeEditor.outerWidth();
                var editorHeight = $activeEditor.outerHeight();
                var left = rect.left;
                var top = rect.bottom + 8;

                if (left + editorWidth > window.innerWidth - 12) {
                    left = window.innerWidth - editorWidth - 12;
                }
                if (left < 12) {
                    left = 12;
                }
                if (top + editorHeight > window.innerHeight - 12) {
                    top = rect.top - editorHeight - 8;
                }
                if (top < 12) {
                    top = 12;
                }

                $activeEditor.css({
                    left: left + 'px',
                    top: top + 'px'
                });
            }

            function abrirEditor($cell) {
                if ($activeCell && $activeCell[0] === $cell[0]) {
                    fecharEditor();
                    return;
                }

                fecharEditor();
                $activeCell = $cell;
                $activeEditor = $cell.find('.escala-editor').first();
                $activeCell.addClass('editor-open');
                $activeEditor.appendTo(document.body).addClass('escala-editor-floating').show();
                posicionarEditor();
                $activeEditor.find('select, input, textarea').first().trigger('focus');
            }

            $('.escala-cell').each(function () {
                atualizarCelula($(this));
            });

            $('.escala-tipo, .escala-turno, .escala-folga-trabalhada-check, .escala-observacoes').on('change input', function () {
                var $cell = $(this).closest('.escala-cell');
                if (!$cell.length && $activeCell) {
                    $cell = $activeCell;
                }
                atualizarCelula($cell);
                posicionarEditor();
            });

            $('.escala-cell-trigger').on('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                abrirEditor($(this).closest('.escala-cell'));
            });

            $('.escala-cell').on('click', function (event) {
                if ($(event.target).closest('.escala-editor, .escala-cell-trigger').length) {
                    return;
                }
                abrirEditor($(this));
            });

            $(document).on('click', '.escala-editor-floating, .escala-editor-floating select, .escala-editor-floating input, .escala-editor-floating textarea, .escala-editor-floating label', function (event) {
                event.stopPropagation();
            });

            $('.escala-editor-close').on('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                fecharEditor();
            });

            $(document).on('click', function () {
                fecharEditor();
            });

            $(document).on('keydown', function (event) {
                if (event.key === 'Escape') {
                    fecharEditor();
                }
            });

            $(window).on('resize scroll', posicionarEditor);
            $('.escala-wrapper').on('scroll', posicionarEditor);
            $('form').on('submit', function () {
                fecharEditor();
            });

            // filtros reativos: atualizar URL sem botão
            $('#filtrosEscala').find('select, input').on('change', function () {
                var params = new URLSearchParams(window.location.search);
                $('#filtrosEscala').find('select, input[name="ano"]').each(function () {
                    var name = $(this).attr('name');
                    var val = $(this).val();
                    if (name) {
                        params.set(name, val);
                    }
                });
                var newUrl = window.location.pathname + '?' + params.toString();
                window.location.href = newUrl;
            });

            // selecionar todas as linhas
            function atualizarSelecao() {
                var total = $('.select_row').length;
                var selecionados = $('.select_row:checked').length;
                $('.select_row').each(function () {
                    $(this).closest('tr').toggleClass('row-selected', $(this).is(':checked'));
                });
                $('.escala-selection-count').text(selecionados + (selecionados === 1 ? ' selecionado' : ' selecionados'));
                $('#select_all_rows').prop('checked', total > 0 && selecionados === total);
                $('#select_all_rows').prop('indeterminate', selecionados > 0 && selecionados < total);
            }

            $('#select_all_rows').on('change', function () {
                var checked = $(this).is(':checked');
                $('.select_row').prop('checked', checked);
                atualizarSelecao();
            });

            $('.select_row').on('change', atualizarSelecao);
            atualizarSelecao();

            $('#btnOpenBulkAssign').on('click', function () {
                var ids = [];
                $('.select_row:checked').each(function () { ids.push($(this).val()); });
                if (ids.length === 0) {
                    alert('Selecione pelo menos um funcionário antes de aplicar.');
                    return;
                }
                $('#bulk_funcionario_ids').val(ids.join(','));
                if (window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalBulkAssign')).show();
                } else {
                    $('#modalBulkAssign').modal('show');
                }
            });
        });
    </script>
</body>

</html>
