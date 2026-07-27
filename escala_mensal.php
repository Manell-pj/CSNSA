<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/funcoes/escala_mensal_funcoes.php';

$utilizadorSessao = require_login($conn);
ac_require_permission($conn, $utilizadorSessao, 'escalas.gerir');

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
$minutosPorTurno = [];
foreach ($turnos as $t) {
    $minutos = escala_mensal_minutos_entre_horas($t['hora_entrada'], $t['hora_saida']);
    $minutosPorTurno[(int)$t['id']] = $minutos;
}
$turnoPaleta = ['turno-azul', 'turno-verde', 'turno-laranja', 'turno-roxo', 'turno-ciano', 'turno-rosa'];
$classePorTurno = [];
foreach ($turnos as $index => $t) {
    $classePorTurno[(int) $t['id']] = $turnoPaleta[$index % count($turnoPaleta)];
}
$codigosTipoDia = [
    'folga' => 'F',
    'ferias' => 'Fe',
    'falta' => 'A',
    'baixa' => 'B',
    'licenca_amamentacao' => 'L',
];
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
            <div class="main-header">
                <?php include 'includes/header.php'; ?>
            </div>

            <div class="container">
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

                    <div class="card mb-4">
                        <div class="card-header">
                            <h4 class="card-title mb-0">Filtros</h4>
                        </div>
                        <div class="card-body">
                            <form method="get" id="filtrosEscala" class="row g-3 align-items-end">
                                <div class="col-md-2">
                                    <label class="form-label">Mes</label>
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
                                <div class="col-md-2">
                                    <label class="form-label">Setor</label>
                                    <select name="setor_id" class="form-select">
                                        <option value="0">Todos os setores</option>
                                        <?php foreach ($setores as $setor): ?>
                                            <option value="<?php echo (int)$setor['id']; ?>" <?php echo (int)($setor['id'] ?? 0) === ($contexto['setor_id'] ?? 0) ? 'selected' : ''; ?>><?php echo e($setor['nome']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100 d-none" id="btnFiltrar">
                                        <i class="fa fa-filter"></i>
                                        Filtrar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <form method="post">
                        <input type="hidden" name="acao" value="guardar">
                        <input type="hidden" name="mes" value="<?php echo (int) $mes; ?>">
                        <input type="hidden" name="ano" value="<?php echo (int) $ano; ?>">
                        <input type="hidden" name="equipa_id" value="<?php echo (int) $equipaId; ?>">
                        <input type="hidden" name="setor_id" value="<?php echo (int) ($contexto['setor_id'] ?? 0); ?>">

                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <h4 class="card-title mb-0"><?php echo e(month_name($mes)); ?> <?php echo (int) $ano; ?></h4>
                                    <div class="ms-auto d-flex align-items-center flex-wrap gap-2 escala-toolbar">
                                        <div class="escala-legenda d-flex align-items-center flex-wrap gap-1">
                                            <?php foreach ($turnos as $t): ?>
                                                <span class="escala-legend-item <?php echo e($classePorTurno[(int) $t['id']] ?? 'turno-azul'); ?>">
                                                    <?php echo e($t['codigo'] ?: $t['nome']); ?>
                                                    <small><?php echo e(substr($t['hora_entrada'], 0, 5) . '-' . substr($t['hora_saida'], 0, 5)); ?></small>
                                                </span>
                                            <?php endforeach; ?>
                                            <span class="escala-legend-item tipo-folga">F <small>Folga</small></span>
                                            <span class="escala-legend-item tipo-ferias">Fe <small>Férias</small></span>
                                            <span class="escala-legend-item tipo-ausencia">A <small>Falta</small></span>
                                            <span class="escala-legend-item tipo-baixa">B <small>Baixa</small></span>
                                            <span class="escala-legend-item tipo-substituicao">S <small>Subst.</small></span>
                                        </div>
                                        <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#modalBulkAssign" <?php echo !empty($missingTables) ? 'disabled' : ''; ?> title="Atribuir a selecionados">
                                            <i class="fa fa-tasks"></i>
                                            Atribuir selecionados
                                        </button>
                                        <button type="submit" class="btn btn-success" <?php echo !empty($missingTables) ? 'disabled' : ''; ?>>
                                            <i class="fa fa-save"></i>
                                            Guardar escala
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($funcionarios)): ?>
                                    <div class="alert alert-info mb-0">
                                        Nenhum funcionário encontrado para os filtros selecionados.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive escala-wrapper">
                                        <table class="table table-bordered table-sm align-middle escala-table">
                                            <thead>
                                                <tr>
                                                    <th class="escala-select-col"><input type="checkbox" id="select_all_rows"></th>
                                                    <th class="escala-sticky-col">Funcionário</th>
                                                    <?php for ($dia = 1; $dia <= $diasNoMes; $dia++): ?>
                                                        <?php $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia); ?>
                                                        <th class="text-center escala-dia-header <?php echo in_array(date('N', strtotime($data)), [6, 7], true) ? 'escala-fim-semana' : ''; ?>">
                                                            <div><?php echo $dia; ?></div>
                                                            <small><?php echo e(weekday_short($data)); ?></small>
                                                        </th>
                                                    <?php endfor; ?>
                                                    <th>Total horas</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($funcionarios as $funcionario): ?>
                                                    <tr>
                                                        <td class="escala-select-col"><input type="checkbox" class="select_row" name="selected_funcionarios[]" value="<?php echo (int)$funcionario['id']; ?>"></td>
                                                        <th class="escala-sticky-col escala-funcionario">
                                                            <div class="fw-bold"><?php echo e($funcionario['nome']); ?></div>
                                                            <small class="text-muted">
                                                                <?php echo e($funcionario['numero_mecanografico'] ?: 'Sem número'); ?>
                                                                <?php if ($funcionario['equipa_nome']): ?>
                                                                    · <?php echo e($funcionario['equipa_nome']); ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        </th>
                                                        <?php $totalMinutes = 0; ?>
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
                                                            $codigoTurno = $turnoInfo ? ($turnoInfo['codigo'] ?: $turnoInfo['nome']) : '';
                                                            $codigoDia = $codigoTurno ?: ($codigosTipoDia[$tipoDia] ?? '-');
                                                            $labelDia = $turnoInfo
                                                                ? ($turnoInfo['nome'] . ' (' . substr($turnoInfo['hora_entrada'], 0, 5) . '-' . substr($turnoInfo['hora_saida'], 0, 5) . ')')
                                                                : tipo_label($tipoDia);
                                                            $cellClasses = ['tipo-' . $tipoDia];

                                                            if ($folgaTrabalhada) {
                                                                $cellClasses[] = 'escala-folga-trabalhada';
                                                            }

                                                            if ($tipoDia === 'substituicao') {
                                                                $cellClasses[] = 'escala-substituicao';
                                                            }

                                                            if ($turnoSelecionado > 0 && isset($classePorTurno[$turnoSelecionado])) {
                                                                $cellClasses[] = $classePorTurno[$turnoSelecionado];
                                                            }

                                                            if ($observacoes !== '') {
                                                                $cellClasses[] = 'tem-observacoes';
                                                            }
                                                            ?>
                                                            <td class="escala-cell <?php echo e(implode(' ', $cellClasses)); ?>">
                                                                <button type="button" class="escala-chip" title="<?php echo e($labelDia); ?>">
                                                                    <span class="escala-chip-code"><?php echo e($codigoDia); ?></span>
                                                                    <?php if ($tipoDia === 'substituicao'): ?><span class="escala-chip-flag">S</span><?php endif; ?>
                                                                    <?php if ($folgaTrabalhada): ?><span class="escala-chip-flag">+</span><?php endif; ?>
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
                                                                            <option value="<?php echo (int) $turno['id']; ?>" data-code="<?php echo e($turno['codigo'] ?: $turno['nome']); ?>" data-class="<?php echo e($classePorTurno[(int) $turno['id']] ?? 'turno-azul'); ?>" data-label="<?php echo e($turno['nome'] . ' (' . substr($turno['hora_entrada'], 0, 5) . '-' . substr($turno['hora_saida'], 0, 5) . ')'); ?>" <?php echo (int) $turno['id'] === $turnoSelecionado ? 'selected' : ''; ?>>
                                                                                <?php echo e($turno['codigo'] ?: $turno['nome']); ?> · <?php echo e(substr($turno['hora_entrada'], 0, 5) . '-' . substr($turno['hora_saida'], 0, 5)); ?>
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
                                                            <?php
                                                            if ($turnoSelecionado > 0 && isset($minutosPorTurno[$turnoSelecionado])) {
                                                                $totalMinutes += (int)$minutosPorTurno[$turnoSelecionado];
                                                            }
                                                            ?>
                                                        <?php endfor; ?>
                                                        <td class="text-end fw-bold"><?php echo floor($totalMinutes / 60) . 'h ' . sprintf('%02d', $totalMinutes % 60) . 'm'; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <?php include 'includes/scripts.php'; ?>
    <!-- Bulk assign modal -->
    <div class="modal fade" id="modalBulkAssign" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" class="modal-content">
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
        .escala-wrapper {
            border: 1px solid #e7ebf3;
            border-radius: 8px;
            max-height: 72vh;
            overflow: auto;
        }

        .escala-table {
            border-collapse: separate;
            border-spacing: 0;
            min-width: 1320px;
            table-layout: fixed;
        }

        .escala-table th,
        .escala-table td {
            border-color: #e3e7ef !important;
            padding: 4px;
            vertical-align: middle;
        }

        .escala-select-col {
            background: #f8fafc;
            position: sticky;
            left: 0;
            text-align: center;
            width: 38px;
            z-index: 6;
        }

        .escala-table thead .escala-select-col {
            top: 0;
            z-index: 8;
        }

        .escala-sticky-col {
            background: #fff;
            left: 38px;
            min-width: 240px;
            position: sticky;
            width: 240px;
            z-index: 5;
        }

        .escala-table thead .escala-sticky-col {
            background: #f8fafc;
            top: 0;
            z-index: 7;
        }

        .escala-dia-header {
            background: #f8fafc;
            color: #1f2937;
            min-width: 38px;
            position: sticky;
            top: 0;
            width: 38px;
            z-index: 4;
        }

        .escala-dia-header div {
            font-weight: 700;
            line-height: 1;
        }

        .escala-dia-header small {
            color: #6b7280;
            display: block;
            font-size: 0.66rem;
            margin-top: 3px;
        }

        .escala-fim-semana {
            background: #eef2f7 !important;
        }

        .escala-cell {
            background: #fff;
            height: 38px;
            min-width: 38px;
            position: relative;
            text-align: center;
            width: 38px;
        }

        .escala-chip {
            align-items: center;
            background: #f3f4f6;
            border: 1px solid #d8dee9;
            border-radius: 6px;
            color: #1f2937;
            cursor: pointer;
            display: flex;
            font-weight: 700;
            height: 30px;
            justify-content: center;
            line-height: 1;
            margin: 0 auto;
            padding: 0;
            position: relative;
            width: 30px;
        }

        .escala-chip-code {
            font-size: 0.78rem;
        }

        .escala-chip-flag {
            align-items: center;
            background: #fff;
            border: 1px solid currentColor;
            border-radius: 50%;
            bottom: -5px;
            display: flex;
            font-size: 0.56rem;
            height: 13px;
            justify-content: center;
            position: absolute;
            right: -5px;
            width: 13px;
        }

        .escala-chip-flag + .escala-chip-flag {
            right: 9px;
        }

        .turno-azul .escala-chip,
        .turno-azul.escala-legend-item {
            background: #dbeafe;
            border-color: #93c5fd;
            color: #1d4ed8;
        }

        .turno-verde .escala-chip,
        .turno-verde.escala-legend-item {
            background: #dcfce7;
            border-color: #86efac;
            color: #166534;
        }

        .turno-laranja .escala-chip,
        .turno-laranja.escala-legend-item {
            background: #ffedd5;
            border-color: #fdba74;
            color: #9a3412;
        }

        .turno-roxo .escala-chip,
        .turno-roxo.escala-legend-item {
            background: #ede9fe;
            border-color: #c4b5fd;
            color: #5b21b6;
        }

        .turno-ciano .escala-chip,
        .turno-ciano.escala-legend-item {
            background: #cffafe;
            border-color: #67e8f9;
            color: #0e7490;
        }

        .turno-rosa .escala-chip,
        .turno-rosa.escala-legend-item {
            background: #fce7f3;
            border-color: #f9a8d4;
            color: #9d174d;
        }

        .tipo-folga .escala-chip,
        .tipo-folga.escala-legend-item {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #475569;
        }

        .tipo-ferias .escala-chip,
        .tipo-ferias.escala-legend-item {
            background: #dcfce7;
            border-color: #86efac;
            color: #15803d;
        }

        .tipo-ausencia .escala-chip,
        .tipo-ausencia.escala-legend-item,
        .tipo-falta .escala-chip {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #b91c1c;
        }

        .tipo-baixa .escala-chip,
        .tipo-baixa.escala-legend-item {
            background: #e0f2fe;
            border-color: #7dd3fc;
            color: #075985;
        }

        .tipo-substituicao .escala-chip,
        .tipo-substituicao.escala-legend-item {
            box-shadow: inset 0 -3px 0 rgba(14, 116, 144, 0.34);
        }

        .escala-folga-trabalhada .escala-chip {
            outline: 2px solid #facc15;
            outline-offset: 1px;
        }

        .tem-observacoes .escala-chip:before {
            background: #111827;
            border-radius: 50%;
            content: "";
            height: 5px;
            left: 4px;
            position: absolute;
            top: 4px;
            width: 5px;
        }

        .escala-funcionario {
            white-space: normal;
        }

        .escala-editor {
            background: #fff;
            border: 1px solid #cfd7e6;
            border-radius: 8px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.18);
            display: none;
            left: 4px;
            min-width: 230px;
            padding: 10px;
            position: absolute;
            text-align: left;
            top: 38px;
            z-index: 20;
        }

        .escala-cell.editor-open .escala-editor {
            display: block;
        }

        .escala-cell:nth-last-child(-n+4) .escala-editor {
            left: auto;
            right: 4px;
        }

        .escala-folga-check {
            font-size: 0.72rem;
            min-height: auto;
        }

        .escala-legend-item {
            border: 1px solid #d8dee9;
            border-radius: 6px;
            display: inline-flex;
            font-size: 0.75rem;
            font-weight: 700;
            gap: 5px;
            line-height: 1;
            padding: 6px 8px;
        }

        .escala-legend-item small {
            font-weight: 500;
            opacity: 0.82;
        }

        .escala-toolbar .btn {
            white-space: nowrap;
        }

        .escala-table th:last-child,
        .escala-table td:last-child {
            background: #fff;
            min-width: 92px;
            position: sticky;
            right: 0;
            z-index: 4;
        }

        .escala-table thead th:last-child {
            background: #f8fafc;
            top: 0;
            z-index: 6;
        }
    </style>
    <script>
        $(document).ready(function () {
            var tipoCodigos = {
                folga: 'F',
                ferias: 'Fe',
                falta: 'A',
                baixa: 'B',
                licenca_amamentacao: 'L',
                substituicao: 'S'
            };
            var tipoClasses = 'tipo-turno tipo-folga tipo-ferias tipo-falta tipo-baixa tipo-substituicao tipo-licenca_amamentacao';
            var turnoClasses = 'turno-azul turno-verde turno-laranja turno-roxo turno-ciano turno-rosa';

            function atualizarCelula($cell) {
                var tipo = $cell.find('.escala-tipo').val();
                var $turno = $cell.find('.escala-turno');
                var $turnoSelecionado = $turno.find('option:selected');
                var turnoId = $turno.val();
                var folgaTrabalhada = $cell.find('.escala-folga-trabalhada-check').is(':checked');
                var mostrarSubstitui = tipo === 'substituicao';
                var mostrarTurno = tipo === 'turno' || tipo === 'substituicao' || folgaTrabalhada;
                var codigo = (mostrarTurno && turnoId) ? ($turnoSelecionado.data('code') || $turnoSelecionado.text().trim()) : (tipoCodigos[tipo] || '-');
                var label = (mostrarTurno && turnoId) ? ($turnoSelecionado.data('label') || $turnoSelecionado.text().trim()) : $cell.find('.escala-tipo option:selected').text().trim();
                var turnoClass = (mostrarTurno && turnoId) ? ($turnoSelecionado.data('class') || '') : '';
                var temObservacoes = $.trim($cell.find('.escala-observacoes').val()) !== '';

                $cell.removeClass(tipoClasses + ' ' + turnoClasses);
                $cell.addClass('tipo-' + tipo);
                if (turnoClass) {
                    $cell.addClass(turnoClass);
                }
                $cell.toggleClass('escala-substituicao', mostrarSubstitui);
                $cell.toggleClass('escala-folga-trabalhada', folgaTrabalhada);
                $cell.toggleClass('tem-observacoes', temObservacoes);
                $cell.find('.escala-substitui').toggle(mostrarSubstitui);
                $cell.find('.escala-turno').toggle(mostrarTurno);
                $cell.find('.escala-chip-code').text(codigo);
                $cell.find('.escala-chip').attr('title', label);
                $cell.find('.escala-chip-flag').remove();
                if (mostrarSubstitui) {
                    $cell.find('.escala-chip').append('<span class="escala-chip-flag">S</span>');
                }
                if (folgaTrabalhada) {
                    $cell.find('.escala-chip').append('<span class="escala-chip-flag">+</span>');
                }
            }

            $('.escala-cell').each(function () {
                atualizarCelula($(this));
            });

            $('.escala-tipo, .escala-turno, .escala-folga-trabalhada-check, .escala-observacoes').on('change input', function () {
                atualizarCelula($(this).closest('.escala-cell'));
            });

            $('.escala-chip').on('click', function (event) {
                event.stopPropagation();
                var $cell = $(this).closest('.escala-cell');
                $('.escala-cell.editor-open').not($cell).removeClass('editor-open');
                $cell.toggleClass('editor-open');
            });

            $('.escala-editor, .escala-editor select, .escala-editor input, .escala-editor label').on('click', function (event) {
                event.stopPropagation();
            });

            $('.escala-editor-close').on('click', function () {
                $(this).closest('.escala-cell').removeClass('editor-open');
            });

            $(document).on('click', function () {
                $('.escala-cell.editor-open').removeClass('editor-open');
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
            $('#select_all_rows').on('change', function () {
                var checked = $(this).is(':checked');
                $('.select_row').prop('checked', checked);
            });

            // abrir modal bulk: preencher ids
            $('#modalBulkAssign').on('show.bs.modal', function () {
                var ids = [];
                $('.select_row:checked').each(function () { ids.push($(this).val()); });
                $('#bulk_funcionario_ids').val(ids.join(','));
                if (ids.length === 0) {
                    alert('Selecione pelo menos um funcionário antes de aplicar.');
                    $('#modalBulkAssign').modal('hide');
                }
            });
        });
    </script>
</body>

</html>

