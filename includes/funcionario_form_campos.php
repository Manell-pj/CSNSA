<?php
$formId = 'funcionarioWizard' . (int) ($funcionarioForm['id'] ?? 0);
$entidadePadrao = 'Centro Social Nossa Senhora Auxiliador';
$estadoCivilOpcoes = function_exists('estado_civil_opcoes') ? estado_civil_opcoes() : [
    'Solteiro',
    'Casado',
    'Divorciado',
    'Separado',
    'Viúvo',
];
$funcionarioForm = array_merge([
    'entidade' => $entidadePadrao,
    'data_ficha' => date('Y-m-d'),
    'numero_mecanografico' => '',
    'nome' => '',
    'doc_identificacao' => '',
    'data_validade_doc' => '',
    'local_emissao' => '',
    'naturalidade' => '',
    'codigo_residencia' => '',
    'genero' => '',
    'estado_civil' => '',
    'data_nascimento' => '',
    'tipo_contrato_id' => '',
    'tipo_contrato' => '',
    'tipo_contrato_codigo' => '',
    'tipo_contrato_tipo_rendimento' => '',
    'tipo_contrato_taxa_irs' => '',
    'morada' => '',
    'localidade' => '',
    'codigo_pais' => '',
    'codigo_postal' => '',
    'telefone' => '',
    'telemovel' => '',
    'data_admissao' => '',
    'codigo_admissao' => '',
    'data_cessacao' => '',
    'codigo_demissao' => '',
    'nif' => '',
    'irs_estado_civil' => '',
    'conjugue' => 0,
    'nif_conjugue' => '',
    'residencia_irs' => '',
    'beneficio_fiscal' => '',
    'numero_filhos' => '',
    'dependentes_deducao' => '',
    'deficientes_dependentes' => '',
    'titularidade_dependentes' => '',
    'irct' => '',
    'cct' => '',
    'categoria_profissional' => '',
    'nivel_profissional' => '',
    'defice_percentagem_paga' => '',
    'moeda' => 'EUR',
    'seccao' => '',
    'local_pagamento' => '',
    'codigo_subsidio_natal' => '',
    'codigo_subsidio_ferias' => '',
    'tipo_horario' => '',
    'tipo_horario_codigo' => '',
    'tipo_horario_unidade' => '',
    'tipo_horario_tratamento' => '',
    'tipo_horario_desc_semanal' => '',
    'carga_horaria_semanal' => '35.00',
    'horas_mes' => '',
    'salario_mensal' => '',
    'seguranca_social_codigo' => '',
    'seguranca_social_numero_beneficiario' => '',
    'relatorio_unico_estabelecimento' => '',
    'relatorio_unico_habilitacoes' => '',
    'relatorio_unico_profissao' => '',
    'relatorio_unico_situacao' => '',
    'relatorio_unico_nivel' => '',
    'relatorio_unico_nacionalidade' => '',
    'relatorio_unico_regime' => '',
    'carta_conducao_numero' => '',
    'carta_conducao_categoria_1' => '',
    'carta_conducao_categoria_2' => '',
    'carta_conducao_data_inicio' => '',
    'carta_conducao_data_validade' => '',
    'cursos' => '',
    'linguas' => '',
    'observacoes' => '',
    'seguro' => '',
    'sindicato' => '',
    'sindicato_numero' => '',
    'banco' => '',
    'iban' => '',
    'conta_empresa' => '',
    'dados_fixos_codigo' => '',
    'dados_fixos_designacao' => '',
    'dados_fixos_quantidade' => '',
    'dados_fixos_valor' => '',
    'email' => '',
    'funcao' => '',
    'equipa_id' => '',
    'diuturnidade_data_base' => '',
    'diuturnidade_ciclo_anos' => '',
    'diuturnidade_ativa' => 1,
    'pin_ponto' => '',
    'codigo_cartao' => '',
    'codigo_biometrico' => '',
    'estado' => 'ativo',
], $funcionarioForm ?? []);

$estadoCivilLegado = [
    'Solteiro/a' => 'Solteiro',
    'Casado/a' => 'Casado',
    'Divorciado/a' => 'Divorciado',
    'Separado/a' => 'Separado',
    'Viúvo/a' => 'Viúvo',
    'Viuvo/a' => 'Viúvo',
];

foreach (['estado_civil', 'irs_estado_civil'] as $estadoCivilCampo) {
    if (isset($estadoCivilLegado[$funcionarioForm[$estadoCivilCampo] ?? ''])) {
        $funcionarioForm[$estadoCivilCampo] = $estadoCivilLegado[$funcionarioForm[$estadoCivilCampo]];
    }

    if (($funcionarioForm[$estadoCivilCampo] ?? '') !== '' && !in_array($funcionarioForm[$estadoCivilCampo], $estadoCivilOpcoes, true)) {
        $estadoCivilOpcoes[] = $funcionarioForm[$estadoCivilCampo];
    }
}
?>

<div class="funcionario-wizard" id="<?php echo e($formId); ?>">
    <div class="wizard-progress mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small wizard-step-label">Página 1 de 10</span>
            <span class="text-muted small">* obrigatório</span>
        </div>
        <div class="progress" style="height: 6px;">
            <div class="progress-bar wizard-progress-bar" role="progressbar" style="width: 10%;"></div>
        </div>
    </div>

    <div class="wizard-page active" data-title="Dados gerais">
        <h6 class="fw-bold mb-3">Dados gerais</h6>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Entidade *</label>
                <input type="text" name="entidade" class="form-control" value="<?php echo e($funcionarioForm['entidade'] ?: $entidadePadrao); ?>" required>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Número mec *</label>
                <input type="text" name="numero_mecanografico" class="form-control js-numero-mecanografico" value="<?php echo e($funcionarioForm['numero_mecanografico']); ?>" required>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Data *</label>
                <input type="date" name="data_ficha" class="form-control" value="<?php echo e($funcionarioForm['data_ficha']); ?>" required>
            </div>
            <div class="col-md-12 mb-3">
                <label class="form-label">Nome completo *</label>
                <input type="text" name="nome" class="form-control" value="<?php echo e($funcionarioForm['nome']); ?>" required>
            </div>
        </div>
    </div>

    <div class="wizard-page" data-title="Identificação">
        <h6 class="fw-bold mb-3">Dados de Identificação</h6>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Doc de identificação *</label>
                <input type="text" name="doc_identificacao" class="form-control" value="<?php echo e($funcionarioForm['doc_identificacao']); ?>" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Data de validade *</label>
                <input type="date" name="data_validade_doc" class="form-control js-date-not-past" value="<?php echo e($funcionarioForm['data_validade_doc']); ?>" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Local emissão</label>
                <input type="text" name="local_emissao" class="form-control" value="<?php echo e($funcionarioForm['local_emissao']); ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Naturalidade *</label>
                <input type="text" name="naturalidade" class="form-control" value="<?php echo e($funcionarioForm['naturalidade']); ?>" required>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Cód Residência</label>
                <input type="text" name="codigo_residencia" class="form-control" value="<?php echo e($funcionarioForm['codigo_residencia']); ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Género *</label>
                <select name="genero" class="form-select" required>
                    <option value="">Selecionar</option>
                    <?php foreach (['Feminino', 'Masculino', 'Outro'] as $genero): ?>
                        <option value="<?php echo e($genero); ?>" <?php echo $funcionarioForm['genero'] === $genero ? 'selected' : ''; ?>><?php echo e($genero); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Estado Civil *</label>
                <select name="estado_civil" class="form-select js-estado-civil" required>
                    <option value="">Selecionar</option>
                    <?php foreach ($estadoCivilOpcoes as $estadoCivilOpcao): ?>
                        <option value="<?php echo e($estadoCivilOpcao); ?>" <?php echo $funcionarioForm['estado_civil'] === $estadoCivilOpcao ? 'selected' : ''; ?>>
                            <?php echo e($estadoCivilOpcao); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Data de Nascimento *</label>
                <input type="date" name="data_nascimento" class="form-control js-date-before-today" value="<?php echo e($funcionarioForm['data_nascimento']); ?>" max="<?php echo date('Y-m-d', strtotime('-1 day')); ?>" required>
            </div>
        </div>
    </div>

    <div class="wizard-page" data-title="Contrato e morada">
        <h6 class="fw-bold mb-3">Tipo de Contrato</h6>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Tipo de contrato</label>
                <select name="tipo_contrato_id" class="form-select js-tipo-contrato">
                    <option value="">Selecionar tipo de contrato</option>
                    <?php foreach (($tiposContrato ?? []) as $tipoContratoOpcao): ?>
                        <option
                            value="<?php echo (int) $tipoContratoOpcao['id']; ?>"
                            data-codigo="<?php echo e($tipoContratoOpcao['codigo']); ?>"
                            data-rendimento="<?php echo e($tipoContratoOpcao['tipo_rendimento']); ?>"
                            data-taxa="<?php echo e($tipoContratoOpcao['taxa_irs']); ?>"
                            <?php echo (int) ($funcionarioForm['tipo_contrato_id'] ?? 0) === (int) $tipoContratoOpcao['id'] ? 'selected' : ''; ?>>
                            <?php echo e($tipoContratoOpcao['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="tipo_contrato" value="<?php echo e($funcionarioForm['tipo_contrato']); ?>">
            </div>
            <div class="col-md-6 mb-3 d-flex align-items-end">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#novoTipoContrato<?php echo e($formId); ?>">
                    <i class="fa fa-plus"></i> Criar novo tipo
                </button>
            </div>
            <div class="col-md-12">
                <div class="collapse mb-3" id="novoTipoContrato<?php echo e($formId); ?>">
                    <div class="border rounded p-3">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Código</label>
                                <input type="text" name="novo_tipo_contrato_codigo" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Designação *</label>
                                <input type="text" name="novo_tipo_contrato_nome" class="form-control">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Tipo Rendimento</label>
                                <input type="text" name="novo_tipo_contrato_rendimento" class="form-control">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Taxa de I.R.S.</label>
                                <input type="text" name="novo_tipo_contrato_taxa_irs" class="form-control js-decimal-only" inputmode="decimal" pattern="\d+([,.]\d+)?">
                            </div>
                            <div class="col-md-12">
                                <button type="submit" name="acao" value="criar_tipo_contrato" class="btn btn-secondary btn-sm" formnovalidate>Guardar tipo de contrato</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Código</label>
                <input type="text" name="tipo_contrato_codigo" class="form-control js-contrato-codigo" value="<?php echo e($funcionarioForm['tipo_contrato_codigo']); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Tipo Rendimento</label>
                <input type="text" name="tipo_contrato_tipo_rendimento" class="form-control js-contrato-rendimento" value="<?php echo e($funcionarioForm['tipo_contrato_tipo_rendimento']); ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Taxa de I.R.S.</label>
                <input type="text" name="tipo_contrato_taxa_irs" class="form-control js-contrato-taxa js-decimal-only" value="<?php echo e($funcionarioForm['tipo_contrato_taxa_irs']); ?>" inputmode="decimal" pattern="\d+([,.]\d+)?">
            </div>
        </div>
        <h6 class="fw-bold mb-3 mt-2">Morada</h6>
        <div class="row">
            <div class="col-md-8 mb-3">
                <label class="form-label">Morada *</label>
                <input type="text" name="morada" class="form-control" value="<?php echo e($funcionarioForm['morada']); ?>" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Localidade *</label>
                <input type="text" name="localidade" class="form-control" value="<?php echo e($funcionarioForm['localidade']); ?>" required>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Código País</label>
                <input type="text" name="codigo_pais" class="form-control" value="<?php echo e($funcionarioForm['codigo_pais']); ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Código Postal *</label>
                <input type="text" name="codigo_postal" class="form-control js-codigo-postal" value="<?php echo e($funcionarioForm['codigo_postal']); ?>" inputmode="numeric" maxlength="8" pattern="\d{4}-\d{3}" placeholder="0000-000" required>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Telefone</label>
                <input type="text" name="telefone" class="form-control js-digits-only" value="<?php echo e($funcionarioForm['telefone']); ?>" inputmode="numeric" maxlength="9" pattern="\d{0,9}">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Telemóvel</label>
                <input type="text" name="telemovel" class="form-control js-digits-only" value="<?php echo e($funcionarioForm['telemovel']); ?>" inputmode="numeric" maxlength="9" pattern="\d{0,9}">
            </div>
        </div>
    </div>

    <div class="wizard-page" data-title="Situação e I.R.S.">
        <h6 class="fw-bold mb-3">Situação</h6>
        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label">Admissão *</label>
                <input type="date" name="data_admissao" class="form-control" value="<?php echo e($funcionarioForm['data_admissao']); ?>" required>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Código admissão *</label>
                <input type="text" name="codigo_admissao" class="form-control" value="<?php echo e($funcionarioForm['codigo_admissao']); ?>" required>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Demissão</label>
                <input type="date" name="data_cessacao" class="form-control" value="<?php echo e($funcionarioForm['data_cessacao']); ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Código demissão</label>
                <input type="text" name="codigo_demissao" class="form-control" value="<?php echo e($funcionarioForm['codigo_demissao']); ?>">
            </div>
        </div>
        <h6 class="fw-bold mb-3">I.R.S.</h6>
        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label">NIF *</label>
                <input type="text" name="nif" class="form-control" value="<?php echo e($funcionarioForm['nif']); ?>" required>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Estado Civil *</label>
                <select name="irs_estado_civil" class="form-select js-irs-estado-civil" required>
                    <option value="">Selecionar</option>
                    <?php foreach ($estadoCivilOpcoes as $estadoCivilOpcao): ?>
                        <option value="<?php echo e($estadoCivilOpcao); ?>" <?php echo $funcionarioForm['irs_estado_civil'] === $estadoCivilOpcao ? 'selected' : ''; ?>>
                            <?php echo e($estadoCivilOpcao); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 mb-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input js-conjugue-toggle" type="checkbox" name="conjugue" id="conjugue<?php echo e($formId); ?>" <?php echo (int) $funcionarioForm['conjugue'] === 1 ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="conjugue<?php echo e($formId); ?>">Cônjuge</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">NIF Cônjuge</label>
                <input type="text" name="nif_conjugue" class="form-control js-nif-conjugue" value="<?php echo e($funcionarioForm['nif_conjugue']); ?>" <?php echo (int) $funcionarioForm['conjugue'] === 1 ? '' : 'disabled'; ?>>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Residência I.R.S</label>
                <input type="text" name="residencia_irs" class="form-control" value="<?php echo e($funcionarioForm['residencia_irs']); ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">B.Fiscal *</label>
                <input type="text" name="beneficio_fiscal" class="form-control" value="<?php echo e($funcionarioForm['beneficio_fiscal']); ?>" required>
            </div>
            <div class="col-md-2 mb-3">
                <label class="form-label">N.Filhos *</label>
                <input type="number" min="0" name="numero_filhos" class="form-control" value="<?php echo e($funcionarioForm['numero_filhos']); ?>" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">N.Dependentes com dedução *</label>
                <input type="number" min="0" name="dependentes_deducao" class="form-control" value="<?php echo e($funcionarioForm['dependentes_deducao']); ?>" required>
            </div>
        </div>
    </div>

    <div class="wizard-page" data-title="Profissionais">
        <div class="row">
            <div class="col-md-6">
                <h6 class="fw-bold mb-3">Deficientes</h6>
                <div class="mb-3">
                    <label class="form-label">N.Dependentes</label>
                    <input type="number" min="0" name="deficientes_dependentes" class="form-control" value="<?php echo e($funcionarioForm['deficientes_dependentes']); ?>">
                </div>
            </div>
            <div class="col-md-6">
                <h6 class="fw-bold mb-3">Titularidade de Rendimentos</h6>
                <div class="mb-3">
                    <label class="form-label">N.Dependentes *</label>
                    <input type="number" min="0" name="titularidade_dependentes" class="form-control" value="<?php echo e($funcionarioForm['titularidade_dependentes']); ?>" required>
                </div>
            </div>
        </div>
        <h6 class="fw-bold mb-3">Profissionais</h6>
        <div class="row">
            <div class="col-md-3 mb-3"><label class="form-label">I.R.C.T</label><input type="text" name="irct" class="form-control" value="<?php echo e($funcionarioForm['irct']); ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">CCT</label><input type="text" name="cct" class="form-control" value="<?php echo e($funcionarioForm['cct']); ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Categoria *</label><input type="text" name="categoria_profissional" class="form-control" value="<?php echo e($funcionarioForm['categoria_profissional']); ?>" required></div>
            <div class="col-md-3 mb-3"><label class="form-label">Nível *</label><input type="text" name="nivel_profissional" class="form-control" value="<?php echo e($funcionarioForm['nivel_profissional']); ?>" required></div>
            <div class="col-md-3 mb-3"><label class="form-label">Defice% Paga *</label><input type="number" step="0.01" name="defice_percentagem_paga" class="form-control" value="<?php echo e($funcionarioForm['defice_percentagem_paga']); ?>" required></div>
            <div class="col-md-3 mb-3"><label class="form-label">Moeda *</label><input type="text" name="moeda" class="form-control" value="<?php echo e($funcionarioForm['moeda']); ?>" required></div>
            <div class="col-md-3 mb-3"><label class="form-label">Secção</label><input type="text" name="seccao" class="form-control" value="<?php echo e($funcionarioForm['seccao']); ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Loc.Pagamento</label><input type="text" name="local_pagamento" class="form-control" value="<?php echo e($funcionarioForm['local_pagamento']); ?>"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Cód. para sub. Natal</label><input type="text" name="codigo_subsidio_natal" class="form-control" value="<?php echo e($funcionarioForm['codigo_subsidio_natal']); ?>"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Cód. para sub. Férias</label><input type="text" name="codigo_subsidio_ferias" class="form-control" value="<?php echo e($funcionarioForm['codigo_subsidio_ferias']); ?>"></div>
        </div>
    </div>

    <div class="wizard-page" data-title="Horário">
        <h6 class="fw-bold mb-3">Tipo Horário</h6>
        <div class="row">
            <div class="col-md-3 mb-3"><label class="form-label">Cod. *</label><input type="text" name="tipo_horario_codigo" class="form-control" value="<?php echo e($funcionarioForm['tipo_horario_codigo']); ?>" required></div>
            <div class="col-md-3 mb-3"><label class="form-label">Unidade *</label><input type="text" name="tipo_horario_unidade" class="form-control" value="<?php echo e($funcionarioForm['tipo_horario_unidade']); ?>" required></div>
            <div class="col-md-3 mb-3"><label class="form-label">Tratamento *</label><input type="text" name="tipo_horario_tratamento" class="form-control" value="<?php echo e($funcionarioForm['tipo_horario_tratamento']); ?>" required></div>
            <div class="col-md-3 mb-3"><label class="form-label">Desc. Semanal</label><input type="text" name="tipo_horario_desc_semanal" class="form-control" value="<?php echo e($funcionarioForm['tipo_horario_desc_semanal']); ?>"></div>
            <input type="hidden" name="tipo_horario" value="<?php echo e($funcionarioForm['tipo_horario']); ?>">
        </div>
        <h6 class="fw-bold mb-3">Horas</h6>
        <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label">Semana *</label><input type="number" step="0.01" min="0.01" name="carga_horaria_semanal" class="form-control" value="<?php echo e($funcionarioForm['carga_horaria_semanal']); ?>" required></div>
            <div class="col-md-4 mb-3"><label class="form-label">Mês *</label><input type="number" step="0.01" min="0.01" name="horas_mes" class="form-control" value="<?php echo e($funcionarioForm['horas_mes']); ?>" required></div>
            <div class="col-md-4 mb-3"><label class="form-label">Salário Mensal</label><input type="number" step="0.01" min="0" name="salario_mensal" class="form-control" value="<?php echo e($funcionarioForm['salario_mensal']); ?>"></div>
        </div>
        <h6 class="fw-bold mb-3">Segurança social</h6>
        <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label">Cod *</label><input type="text" name="seguranca_social_codigo" class="form-control" value="<?php echo e($funcionarioForm['seguranca_social_codigo']); ?>" required></div>
            <div class="col-md-8 mb-3"><label class="form-label">N. Beneficiário</label><input type="text" name="seguranca_social_numero_beneficiario" class="form-control js-digits-only" value="<?php echo e($funcionarioForm['seguranca_social_numero_beneficiario']); ?>" inputmode="numeric" maxlength="11" pattern="\d{11}"></div>
        </div>
    </div>

    <div class="wizard-page" data-title="Relatório Único">
        <h6 class="fw-bold mb-3">Relatório Único</h6>
        <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label">Estabelecimento</label><input type="text" name="relatorio_unico_estabelecimento" class="form-control" value="<?php echo e($funcionarioForm['relatorio_unico_estabelecimento']); ?>"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Habilitações</label><input type="text" name="relatorio_unico_habilitacoes" class="form-control" value="<?php echo e($funcionarioForm['relatorio_unico_habilitacoes']); ?>"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Profissão</label><input type="text" name="relatorio_unico_profissao" class="form-control" value="<?php echo e($funcionarioForm['relatorio_unico_profissao']); ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Situação *</label><input type="text" name="relatorio_unico_situacao" class="form-control" value="<?php echo e($funcionarioForm['relatorio_unico_situacao']); ?>" required></div>
            <div class="col-md-3 mb-3"><label class="form-label">Nível</label><input type="text" name="relatorio_unico_nivel" class="form-control" value="<?php echo e($funcionarioForm['relatorio_unico_nivel']); ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Nacionalidade</label><input type="text" name="relatorio_unico_nacionalidade" class="form-control" value="<?php echo e($funcionarioForm['relatorio_unico_nacionalidade']); ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Regime</label><input type="text" name="relatorio_unico_regime" class="form-control" value="<?php echo e($funcionarioForm['relatorio_unico_regime']); ?>"></div>
        </div>
        <h6 class="fw-bold mb-3">Carta de Condução</h6>
        <div class="row">
            <div class="col-md-3 mb-3"><label class="form-label">Número</label><input type="text" name="carta_conducao_numero" class="form-control" value="<?php echo e($funcionarioForm['carta_conducao_numero']); ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Categoria</label><input type="text" name="carta_conducao_categoria_1" class="form-control" value="<?php echo e($funcionarioForm['carta_conducao_categoria_1']); ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Categoria</label><input type="text" name="carta_conducao_categoria_2" class="form-control" value="<?php echo e($funcionarioForm['carta_conducao_categoria_2']); ?>"></div>
            <div class="col-md-2 mb-3"><label class="form-label">Data de início</label><input type="date" name="carta_conducao_data_inicio" class="form-control" value="<?php echo e($funcionarioForm['carta_conducao_data_inicio']); ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Data de Validade</label><input type="date" name="carta_conducao_data_validade" class="form-control js-date-not-past" value="<?php echo e($funcionarioForm['carta_conducao_data_validade']); ?>" min="<?php echo date('Y-m-d'); ?>"></div>
        </div>
    </div>

    <div class="wizard-page" data-title="Cursos">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Cursos</label>
                <textarea name="cursos" class="form-control" rows="7"><?php echo e($funcionarioForm['cursos']); ?></textarea>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Línguas</label>
                <textarea name="linguas" class="form-control" rows="7"><?php echo e($funcionarioForm['linguas']); ?></textarea>
            </div>
            <div class="col-md-12 mb-3">
                <label class="form-label">Observações</label>
                <textarea name="observacoes" class="form-control" rows="4"><?php echo e($funcionarioForm['observacoes']); ?></textarea>
            </div>
        </div>
    </div>

    <div class="wizard-page" data-title="Instituições">
        <h6 class="fw-bold mb-3">Instituições</h6>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Seguro *</label><input type="text" name="seguro" class="form-control" value="<?php echo e($funcionarioForm['seguro']); ?>" required></div>
            <div class="col-md-3 mb-3"><label class="form-label">Sindicato</label><input type="text" name="sindicato" class="form-control" value="<?php echo e($funcionarioForm['sindicato']); ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Nº</label><input type="text" name="sindicato_numero" class="form-control" value="<?php echo e($funcionarioForm['sindicato_numero']); ?>"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Banco *</label><input type="text" name="banco" class="form-control" value="<?php echo e($funcionarioForm['banco']); ?>" required></div>
            <div class="col-md-4 mb-3"><label class="form-label">IBAN *</label><input type="text" name="iban" class="form-control" value="<?php echo e($funcionarioForm['iban']); ?>" required></div>
            <div class="col-md-4 mb-3"><label class="form-label">Conta Empresa *</label><input type="text" name="conta_empresa" class="form-control" value="<?php echo e($funcionarioForm['conta_empresa']); ?>" required></div>
        </div>
        <h6 class="fw-bold mb-3">Dados Fixos</h6>
        <div class="row">
            <div class="col-md-2 mb-3"><label class="form-label">Cód.</label><input type="text" name="dados_fixos_codigo" class="form-control" value="<?php echo e($funcionarioForm['dados_fixos_codigo']); ?>"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Designação</label><input type="text" name="dados_fixos_designacao" class="form-control" value="<?php echo e($funcionarioForm['dados_fixos_designacao']); ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Quant.</label><input type="text" name="dados_fixos_quantidade" class="form-control" value="<?php echo e($funcionarioForm['dados_fixos_quantidade']); ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Valor diário/Mensal</label><input type="number" step="0.01" name="dados_fixos_valor" class="form-control" value="<?php echo e($funcionarioForm['dados_fixos_valor']); ?>"></div>
        </div>
    </div>

    <div class="wizard-page" data-title="Sistema">
        <h6 class="fw-bold mb-3">Campos internos</h6>
        <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label">Função</label><input type="text" name="funcao" class="form-control" value="<?php echo e($funcionarioForm['funcao']); ?>"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?php echo e($funcionarioForm['email']); ?>"></div>
            <?php if ($temEquipas): ?>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Equipa</label>
                    <select name="equipa_id" class="form-select">
                        <option value="">Sem equipa</option>
                        <?php foreach ($equipas as $equipa): ?>
                            <option value="<?php echo (int) $equipa['id']; ?>" <?php echo (int) ($funcionarioForm['equipa_id'] ?? 0) === (int) $equipa['id'] ? 'selected' : ''; ?>>
                                <?php echo e($equipa['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-md-3 mb-3"><label class="form-label">Estado</label><select name="estado" class="form-select"><option value="ativo" <?php echo $funcionarioForm['estado'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option><option value="suspenso" <?php echo $funcionarioForm['estado'] === 'suspenso' ? 'selected' : ''; ?>>Suspenso</option><option value="inativo" <?php echo $funcionarioForm['estado'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option></select></div>
            <div class="col-md-3 mb-3"><label class="form-label">PIN ponto</label><input type="text" name="pin_ponto" class="form-control js-pin-ponto" value="<?php echo e($funcionarioForm['pin_ponto']); ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Código cartão</label><input type="text" name="codigo_cartao" class="form-control" value="<?php echo e($funcionarioForm['codigo_cartao']); ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Código biométrico</label><input type="text" name="codigo_biometrico" class="form-control" value="<?php echo e($funcionarioForm['codigo_biometrico']); ?>"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Data-base diuturnidade</label><input type="date" name="diuturnidade_data_base" class="form-control" value="<?php echo e($funcionarioForm['diuturnidade_data_base']); ?>"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Anos por ciclo</label><input type="number" min="1" max="80" name="diuturnidade_ciclo_anos" class="form-control" value="<?php echo e($funcionarioForm['diuturnidade_ciclo_anos']); ?>"></div>
            <div class="col-md-4 mb-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="diuturnidade_ativa" id="diuturnidadeAtiva<?php echo e($formId); ?>" <?php echo (int) ($funcionarioForm['diuturnidade_ativa'] ?? 1) === 1 ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="diuturnidadeAtiva<?php echo e($formId); ?>">Notificar diuturnidades</label>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
        <button type="button" class="btn btn-outline-secondary wizard-prev">Anterior</button>
        <div class="text-muted small wizard-current-title">Dados gerais</div>
        <button type="button" class="btn btn-primary wizard-next">Seguinte</button>
    </div>
</div>
