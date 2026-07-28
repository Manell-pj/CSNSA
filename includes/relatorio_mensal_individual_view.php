<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0"><?php echo e($funcionario['nome']); ?></h4>
        <div class="text-muted">
            Equipa: <?php echo e($funcionario['equipa']); ?> ·
            Função: <?php echo e($funcionario['funcao']); ?> ·
            Carga diária: <?php echo e(rm_formatar_minutos($funcionario['carga_diaria_minutos'])); ?> ·
            Carga semanal: <?php echo e(rm_formatar_minutos($funcionario['carga_semanal_minutos'])); ?>
        </div>
    </div>
    <div class="card-body">
        <?php $t = $funcionario['totais']; ?>
        <div class="row mb-3">
            <div class="col-md-3"><strong>Dias previstos:</strong> <?php echo (int) $t['dias_previstos']; ?></div>
            <div class="col-md-3"><strong>Dias trabalhados:</strong> <?php echo (int) $t['dias_trabalhados']; ?></div>
            <div class="col-md-3"><strong>Faltas:</strong> <?php echo (int) $t['faltas']; ?></div>
            <div class="col-md-3"><strong>Banco horas:</strong> <?php echo e(rm_formatar_minutos($t['banco_horas_minutos'])); ?></div>
        </div>

        <div class="table-responsive">
            <table class="display table table-striped table-hover report-table report-datatable">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Entradas/Saídas</th>
                        <th>Previstas</th>
                        <th>Trabalhadas</th>
                        <th>Pausas</th>
                        <th>Atrasos</th>
                        <th>Falta</th>
                        <th>Férias</th>
                        <th>Baixa</th>
                        <th>Folga</th>
                        <th>Folga trab.</th>
                        <th>Horas em folga</th>
                        <th>Extra</th>
                        <?php foreach ($relatorio['regras_extra'] as $regra): ?>
                            <th>Extra <?php echo (int) $regra['porcentagem']; ?>%</th>
                        <?php endforeach; ?>
                        <th>Banco horas</th>
                        <th>Correções</th>
                        <th>Observações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($funcionario['dias'] as $dia): ?>
                        <tr>
                            <td><?php echo e(rm_formatar_data($dia['data'])); ?></td>
                            <td><?php echo e($dia['tipo_dia']); ?></td>
                            <td><?php echo e($dia['entradas_saidas'] ?: '-'); ?></td>
                            <td><?php echo e(rm_formatar_minutos($dia['minutos_previstos'])); ?></td>
                            <td><?php echo e(rm_formatar_minutos($dia['minutos_trabalhados'])); ?></td>
                            <td><?php echo e(rm_formatar_minutos($dia['minutos_pausas'])); ?></td>
                            <td><?php echo e(rm_formatar_minutos($dia['minutos_atraso'])); ?></td>
                            <td><?php echo (int) $dia['falta']; ?></td>
                            <td><?php echo (int) $dia['ferias']; ?></td>
                            <td><?php echo (int) $dia['baixa']; ?></td>
                            <td><?php echo (int) $dia['folga']; ?></td>
                            <td><?php echo (int) $dia['folga_trabalhada']; ?></td>
                            <td><?php echo e(rm_formatar_minutos($dia['minutos_folga_trabalhada'])); ?></td>
                            <td><?php echo e(rm_formatar_minutos($dia['minutos_extra'])); ?></td>
                            <?php foreach ($relatorio['regras_extra'] as $regra): ?>
                                <?php $percentagem = (string) (int) $regra['porcentagem']; ?>
                                <td><?php echo e(rm_formatar_minutos($dia['extra_percentagens'][$percentagem] ?? 0)); ?></td>
                            <?php endforeach; ?>
                            <td><?php echo e(rm_formatar_minutos($dia['banco_horas_minutos'])); ?></td>
                            <td><?php echo (int) $dia['correcoes_manuais']; ?></td>
                            <td><?php echo e($dia['observacoes'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <th></th>
                        <th></th>
                        <th><?php echo e(rm_formatar_minutos($t['minutos_previstos'])); ?></th>
                        <th><?php echo e(rm_formatar_minutos($t['minutos_trabalhados'])); ?></th>
                        <th><?php echo e(rm_formatar_minutos($t['minutos_pausas'])); ?></th>
                        <th><?php echo e(rm_formatar_minutos($t['minutos_atraso'])); ?></th>
                        <th><?php echo (int) $t['faltas']; ?></th>
                        <th><?php echo (int) $t['ferias']; ?></th>
                        <th><?php echo (int) $t['baixas']; ?></th>
                        <th><?php echo (int) $t['folgas']; ?></th>
                        <th><?php echo (int) $t['folgas_trabalhadas']; ?></th>
                        <th><?php echo e(rm_formatar_minutos($t['minutos_folga_trabalhada'])); ?></th>
                        <th><?php echo e(rm_formatar_minutos($t['minutos_extra'])); ?></th>
                        <?php foreach ($relatorio['regras_extra'] as $regra): ?>
                            <?php $percentagem = (string) (int) $regra['porcentagem']; ?>
                            <th><?php echo e(rm_formatar_minutos($t['extra_percentagens'][$percentagem] ?? 0)); ?></th>
                        <?php endforeach; ?>
                        <th><?php echo e(rm_formatar_minutos($t['banco_horas_minutos'])); ?></th>
                        <th><?php echo (int) $t['correcoes_manuais']; ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
