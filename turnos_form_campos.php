<?php
$nome = $turno['nome'] ?? '';
$codigo = $turno['codigo'] ?? '';
$inicioPausa = $turno['inicio_pausa'] ?? '';
$fimPausa = $turno['fim_pausa'] ?? '';
$toleranciaAtraso = isset($turno['tolerancia_entrada_min']) ? (int) $turno['tolerancia_entrada_min'] : 0;
$toleranciaSaida = isset($turno['tolerancia_saida_min']) ? (int) $turno['tolerancia_saida_min'] : 0;
$horasPrevistas = isset($turno['horas_previstas']) ? $turno['horas_previstas'] : 8.00;
$turnoNoturno = isset($turno['turno_noturno']) && (int) $turno['turno_noturno'] === 1;
$ativo = !isset($turno) || (int) ($turno['ativo'] ?? 1) === 1;
$periodosData = $periodos ?? [];
if (empty($periodosData) && isset($turno['hora_entrada'], $turno['hora_saida'])) {
    $periodosData[] = [
        'inicio' => substr($turno['hora_entrada'], 0, 5),
        'fim' => substr($turno['hora_saida'], 0, 5),
    ];
}
?>
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Nome *</label>
        <input type="text" name="nome" class="form-control" value="<?php echo e($nome); ?>" required>
        <div class="invalid-feedback">Indique o nome do turno.</div>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Código</label>
        <input type="text" name="codigo" class="form-control" value="<?php echo e($codigo); ?>">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Início pausa</label>
        <input type="time" name="inicio_pausa" class="form-control" value="<?php echo e($inicioPausa); ?>">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Fim pausa</label>
        <input type="time" name="fim_pausa" class="form-control" value="<?php echo e($fimPausa); ?>">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Tolerância de atraso (min)</label>
        <input type="number" name="tolerancia_entrada_min" class="form-control" min="0" value="<?php echo e($toleranciaAtraso); ?>">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Tolerância de saída (min)</label>
        <input type="number" name="tolerancia_saida_min" class="form-control" min="0" value="<?php echo e($toleranciaSaida); ?>">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Horas previstas</label>
        <input type="number" step="0.25" name="horas_previstas" class="form-control" min="0" value="<?php echo e($horasPrevistas); ?>">
    </div>
    <div class="col-md-4 mb-3">
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="turno_noturno" id="criarTurnoNoturno" <?php echo $turnoNoturno ? 'checked' : ''; ?>>
            <label class="form-check-label" for="criarTurnoNoturno">Turno noturno</label>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="ativo" id="criarTurnoAtivo" <?php echo $ativo ? 'checked' : ''; ?>>
            <label class="form-check-label" for="criarTurnoAtivo">Ativo</label>
        </div>
    </div>
</div>
<div class="card mb-3">
    <div class="card-header">
        <h5 class="card-title mb-0">Períodos de turno <small class="text-muted">(um ou mais períodos no mesmo dia)</small></h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm turno-periodos-tabela">
                <thead>
                    <tr>
                        <th>Início *</th>
                        <th>Fim *</th>
                        <th style="width: 100px"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($periodosData)): ?>
                        <?php $periodosData[] = ['inicio' => '', 'fim' => '']; ?>
                    <?php endif; ?>
                    <?php foreach ($periodosData as $periodo): ?>
                        <tr>
                            <td>
                                <input type="time" name="periodo_inicio[]" class="form-control" value="<?php echo e($periodo['inicio']); ?>" required>
                            </td>
                            <td>
                                <input type="time" name="periodo_fim[]" class="form-control" value="<?php echo e($periodo['fim']); ?>" required>
                            </td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm remover-periodo">Remover</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-secondary btn-sm turno-periodos-adicionar">Adicionar período</button>
        <div class="invalid-feedback d-none periodos-feedback">Adicione pelo menos um período válido.</div>
    </div>
</div>
