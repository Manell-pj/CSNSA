<?php
$nome = $turno['nome'] ?? '';
$codigo = $turno['codigo'] ?? '';
$horaEntrada = isset($turno['hora_entrada']) ? substr((string) $turno['hora_entrada'], 0, 5) : '';
$horaSaida = isset($turno['hora_saida']) ? substr((string) $turno['hora_saida'], 0, 5) : '';
$toleranciaAtraso = isset($turno['tolerancia_entrada_min']) ? (int) $turno['tolerancia_entrada_min'] : 0;
$toleranciaSaida = isset($turno['tolerancia_saida_min']) ? (int) $turno['tolerancia_saida_min'] : 0;
$horasPrevistas = isset($turno['horas_previstas']) ? $turno['horas_previstas'] : 8.00;
$ativo = !isset($turno) || (int) ($turno['ativo'] ?? 1) === 1;
$turnoFormId = 'turno' . (int) ($turno['id'] ?? 0) . '_' . substr(md5((string) spl_object_id((object) $turno)), 0, 6);
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
        <label class="form-label">Hora de entrada *</label>
        <input type="time" name="hora_entrada" class="form-control" value="<?php echo e($horaEntrada); ?>" required>
        <div class="invalid-feedback">Indique a hora de entrada.</div>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Hora de saída *</label>
        <input type="time" name="hora_saida" class="form-control" value="<?php echo e($horaSaida); ?>" required>
        <div class="invalid-feedback">Indique a hora de saída.</div>
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
            <input class="form-check-input" type="checkbox" name="ativo" id="turnoAtivo<?php echo e($turnoFormId); ?>" <?php echo $ativo ? 'checked' : ''; ?>>
            <label class="form-check-label" for="turnoAtivo<?php echo e($turnoFormId); ?>">Ativo</label>
        </div>
    </div>
</div>
