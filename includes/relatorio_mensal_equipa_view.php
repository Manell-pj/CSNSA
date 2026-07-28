<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">Totais por funcionário</h4>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="display table table-striped table-hover report-table report-datatable">
                <thead>
                    <tr>
                        <th>Funcionário</th>
                        <th>Equipa</th>
                        <th>Função</th>
                        <th>Carga diária</th>
                        <th>Carga semanal</th>
                        <th>Dias previstos</th>
                        <th>Dias trabalhados</th>
                        <th>Horas previstas</th>
                        <th>Horas trabalhadas</th>
                        <th>Horas extra</th>
                        <?php foreach ($relatorio['regras_extra'] as $regra): ?>
                            <th>Extra <?php echo (int) $regra['porcentagem']; ?>%</th>
                        <?php endforeach; ?>
                        <th>Faltas</th>
                        <th>Ausências</th>
                        <th>Folgas trab.</th>
                        <th>Horas em folga</th>
                        <th>Banco horas</th>
                        <th>Correções</th>
                        <th>Incidências pendentes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($relatorio['funcionarios'] as $funcionario): ?>
                        <?php $t = $funcionario['totais']; ?>
                        <tr>
                            <td><?php echo e($funcionario['nome']); ?></td>
                            <td><?php echo e($funcionario['equipa']); ?></td>
                            <td><?php echo e($funcionario['funcao']); ?></td>
                            <td><?php echo e(rm_formatar_minutos($funcionario['carga_diaria_minutos'])); ?></td>
                            <td><?php echo e(rm_formatar_minutos($funcionario['carga_semanal_minutos'])); ?></td>
                            <td><?php echo (int) $t['dias_previstos']; ?></td>
                            <td><?php echo (int) $t['dias_trabalhados']; ?></td>
                            <td><?php echo e(rm_formatar_minutos($t['minutos_previstos'])); ?></td>
                            <td><?php echo e(rm_formatar_minutos($t['minutos_trabalhados'])); ?></td>
                            <td><?php echo e(rm_formatar_minutos($t['minutos_extra'])); ?></td>
                            <?php foreach ($relatorio['regras_extra'] as $regra): ?>
                                <?php $percentagem = (string) (int) $regra['porcentagem']; ?>
                                <td><?php echo e(rm_formatar_minutos($t['extra_percentagens'][$percentagem] ?? 0)); ?></td>
                            <?php endforeach; ?>
                            <td><?php echo (int) $t['faltas']; ?></td>
                            <td><?php echo (int) ($t['ferias'] + $t['baixas']); ?></td>
                            <td><?php echo (int) $t['folgas_trabalhadas']; ?></td>
                            <td><?php echo e(rm_formatar_minutos($t['minutos_folga_trabalhada'])); ?></td>
                            <td><?php echo e(rm_formatar_minutos($t['banco_horas_minutos'])); ?></td>
                            <td><?php echo (int) $t['correcoes_manuais']; ?></td>
                            <td><?php echo (int) $t['incidencias_pendentes']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php $t = $relatorio['totais_equipa']; ?>
                    <tr>
                        <th>Total da equipa</th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th><?php echo (int) $t['dias_previstos']; ?></th>
                        <th><?php echo (int) $t['dias_trabalhados']; ?></th>
                        <th><?php echo e(rm_formatar_minutos($t['minutos_previstos'])); ?></th>
                        <th><?php echo e(rm_formatar_minutos($t['minutos_trabalhados'])); ?></th>
                        <th><?php echo e(rm_formatar_minutos($t['minutos_extra'])); ?></th>
                        <?php foreach ($relatorio['regras_extra'] as $regra): ?>
                            <?php $percentagem = (string) (int) $regra['porcentagem']; ?>
                            <th><?php echo e(rm_formatar_minutos($t['extra_percentagens'][$percentagem] ?? 0)); ?></th>
                        <?php endforeach; ?>
                        <th><?php echo (int) $t['faltas']; ?></th>
                        <th><?php echo (int) ($t['ferias'] + $t['baixas']); ?></th>
                        <th><?php echo (int) $t['folgas_trabalhadas']; ?></th>
                        <th><?php echo e(rm_formatar_minutos($t['minutos_folga_trabalhada'])); ?></th>
                        <th><?php echo e(rm_formatar_minutos($t['banco_horas_minutos'])); ?></th>
                        <th><?php echo (int) $t['correcoes_manuais']; ?></th>
                        <th><?php echo (int) $t['incidencias_pendentes']; ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php foreach ($relatorio['funcionarios'] as $funcionario): ?>
    <div class="page-break">
        <?php include __DIR__ . '/relatorio_mensal_individual_view.php'; ?>
    </div>
<?php endforeach; ?>
