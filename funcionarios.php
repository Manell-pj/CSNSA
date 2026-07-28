<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';
require_once __DIR__ . '/funcoes/funcionarios_funcoes.php';

$utilizadorSessao = require_login($conn);
ac_require_permission($conn, $utilizadorSessao, 'funcionarios.consultar');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$missingTables = [];
if (!fe_table_exists($conn, 'funcionarios')) {
    $missingTables[] = 'funcionarios';
}

if (empty($missingTables)) {
    ensure_funcionarios_extended_schema($conn);
}

$temEquipas = fe_table_exists($conn, 'equipas') && fe_column_exists($conn, 'funcionarios', 'equipa_id');
$temCamposNotificações = fe_table_exists($conn, 'funcionarios')
    && fe_column_exists($conn, 'funcionarios', 'data_nascimento')
    && fe_column_exists($conn, 'funcionarios', 'diuturnidade_data_base')
    && fe_column_exists($conn, 'funcionarios', 'diuturnidade_ciclo_anos')
    && fe_column_exists($conn, 'funcionarios', 'diuturnidade_ativa');
$podeVerDadosSensiveis = ac_can($conn, (int) $utilizadorSessao['id'], 'funcionarios.dados_sensiveis');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($missingTables)) {
        redirect_with_message('danger', 'Execute a migração antes de gerir funcionários.');
    }

    $acao = $_POST['acao'] ?? '';
    if ($acao === 'criar' || $acao === 'editar' || $acao === 'criar_tipo_contrato') {
        ac_require_permission($conn, $utilizadorSessao, 'funcionarios.editar');
    }
    if ($acao === 'desativar' || $acao === 'reativar') {
        ac_require_permission($conn, $utilizadorSessao, 'funcionarios.desativar');
    }

    // Verify CSRF token for all modifying actions
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        redirect_with_message('danger', 'Token CSRF inválido.');
    }

    if ($acao === 'criar_tipo_contrato') {
        $codigo = nullable_text($_POST['novo_tipo_contrato_codigo'] ?? '');
        $nome = get_post_value('novo_tipo_contrato_nome');
        $tipoRendimento = nullable_text($_POST['novo_tipo_contrato_rendimento'] ?? '');
        if (!is_decimal_value($_POST['novo_tipo_contrato_taxa_irs'] ?? '')) {
            redirect_with_message('danger', 'A taxa de I.R.S. so pode conter numeros.');
        }
        $taxaIrs = nullable_decimal($_POST['novo_tipo_contrato_taxa_irs'] ?? '');

        if ($nome === '') {
            redirect_with_message('danger', 'Indique a designação do novo tipo de contrato.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'INSERT INTO funcionario_tipos_contrato (codigo, nome, tipo_rendimento, taxa_irs) VALUES (?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sssd', $codigo, $nome, $tipoRendimento, $taxaIrs);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Tipo de contrato criado com sucesso.', [
                'modal' => 'adicionar_funcionario',
                'step' => 2,
            ]);
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível criar o tipo de contrato. Verifique se a designação já existe.');
        }
    }

    if ($acao === 'criar' || $acao === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $entidadePadrao = 'Centro Social Nossa Senhora Auxiliador';
        $entidade = get_post_value('entidade') ?: $entidadePadrao;
        $nome = get_post_value('nome');
        $numeroMecanograficoRaw = trim($_POST['numero_mecanografico'] ?? '');
        $numeroMecanografico = nullable_int($numeroMecanograficoRaw);
        $dataFicha = nullable_date($_POST['data_ficha'] ?? '');
        $email = nullable_text($_POST['email'] ?? '');
        $telefoneRaw = $_POST['telefone'] ?? '';
        $telemovelRaw = $_POST['telemovel'] ?? '';
        $telefone = nullable_text($telefoneRaw);
        $funcao = nullable_text($_POST['funcao'] ?? '');
        $dataNascimento = nullable_date($_POST['data_nascimento'] ?? '');
        $categoria = nullable_text($_POST['categoria_profissional'] ?? '');
        $equipaId = $temEquipas ? nullable_int($_POST['equipa_id'] ?? '') : null;
        $setorId = null;
        if ($equipaId !== null && fe_column_exists($conn, 'equipas', 'setor_id')) {
            $stmtSetor = mysqli_prepare($conn, 'SELECT setor_id FROM equipas WHERE id = ? LIMIT 1');
            mysqli_stmt_bind_param($stmtSetor, 'i', $equipaId);
            mysqli_stmt_execute($stmtSetor);
            $equipaSelecionada = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtSetor));
            mysqli_stmt_close($stmtSetor);
            $setorId = isset($equipaSelecionada['setor_id']) ? (int) $equipaSelecionada['setor_id'] : null;
        }
        $dataAdmissao = nullable_date($_POST['data_admissao'] ?? '');
        $diuturnidadeDataBase = nullable_date($_POST['diuturnidade_data_base'] ?? '');
        $diuturnidadeCicloAnos = nullable_int($_POST['diuturnidade_ciclo_anos'] ?? '');
        $diuturnidadeAtiva = isset($_POST['diuturnidade_ativa']) ? 1 : 0;
        $dataCessacao = nullable_date($_POST['data_cessacao'] ?? '');
        $tipoContratoId = nullable_int($_POST['tipo_contrato_id'] ?? '');
        $tipoContrato = nullable_text($_POST['tipo_contrato'] ?? '');
        $cargaHoraria = nullable_decimal($_POST['carga_horaria_semanal'] ?? '40') ?? 40.0;
        $horasMes = nullable_decimal($_POST['horas_mes'] ?? '');
        $salarioMensal = nullable_decimal($_POST['salario_mensal'] ?? '');
        $pinPonto = nullable_text($_POST['pin_ponto'] ?? '') ?: $numeroMecanografico;
        $codigoCartao = nullable_text($_POST['codigo_cartao'] ?? '');
        $codigoBiometrico = nullable_text($_POST['codigo_biometrico'] ?? '');
        $estado = get_post_value('estado') ?: 'ativo';
        $observacoes = nullable_text($_POST['observacoes'] ?? '');
        $conjugue = isset($_POST['conjugue']) ? 1 : 0;
        $estadoCivil = nullable_text(normalizar_estado_civil($_POST['estado_civil'] ?? ''));
        $irsEstadoCivil = nullable_text(normalizar_estado_civil($_POST['irs_estado_civil'] ?? '')) ?: $estadoCivil;
        $codigoPostal = normalizar_codigo_postal($_POST['codigo_postal'] ?? '');

        $tipoContratoSelecionado = null;
        if ($tipoContratoId !== null) {
            $stmt = mysqli_prepare($conn, 'SELECT id, codigo, nome, tipo_rendimento, taxa_irs FROM funcionario_tipos_contrato WHERE id = ? AND ativo = 1 LIMIT 1');
            mysqli_stmt_bind_param($stmt, 'i', $tipoContratoId);
            mysqli_stmt_execute($stmt);
            $tipoContratoSelecionado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
        }

        if ($tipoContratoSelecionado) {
            $tipoContrato = $tipoContratoSelecionado['nome'];
        }

        $funcionarioDados = [
            'setor_id' => $setorId,
            'equipa_id' => $equipaId,
            'entidade' => $entidade,
            'numero_mecanografico' => $numeroMecanografico,
            'data_ficha' => $dataFicha,
            'nome' => $nome,
            'email' => $email,
            'telefone' => $telefone,
            'funcao' => $funcao,
            'data_nascimento' => $dataNascimento,
            'doc_identificacao' => nullable_text($_POST['doc_identificacao'] ?? ''),
            'data_validade_doc' => nullable_date($_POST['data_validade_doc'] ?? ''),
            'local_emissao' => nullable_text($_POST['local_emissao'] ?? ''),
            'naturalidade' => nullable_text($_POST['naturalidade'] ?? ''),
            'codigo_residencia' => nullable_text($_POST['codigo_residencia'] ?? ''),
            'genero' => nullable_text($_POST['genero'] ?? ''),
            'estado_civil' => $estadoCivil,
            'tipo_contrato' => $tipoContrato,
            'tipo_contrato_id' => $tipoContratoId,
            'tipo_contrato_codigo' => $tipoContratoSelecionado['codigo'] ?? nullable_text($_POST['tipo_contrato_codigo'] ?? ''),
            'tipo_contrato_tipo_rendimento' => $tipoContratoSelecionado['tipo_rendimento'] ?? nullable_text($_POST['tipo_contrato_tipo_rendimento'] ?? ''),
            'tipo_contrato_taxa_irs' => $tipoContratoSelecionado && $tipoContratoSelecionado['taxa_irs'] !== null ? (float) $tipoContratoSelecionado['taxa_irs'] : nullable_decimal($_POST['tipo_contrato_taxa_irs'] ?? ''),
            'morada' => nullable_text($_POST['morada'] ?? ''),
            'localidade' => nullable_text($_POST['localidade'] ?? ''),
            'codigo_pais' => nullable_text($_POST['codigo_pais'] ?? ''),
            'codigo_postal' => $codigoPostal,
            'telemovel' => nullable_text($telemovelRaw),
            'data_admissao' => $dataAdmissao,
            'codigo_admissao' => nullable_text($_POST['codigo_admissao'] ?? ''),
            'data_cessacao' => $dataCessacao,
            'codigo_demissao' => nullable_text($_POST['codigo_demissao'] ?? ''),
            'nif' => nullable_text($_POST['nif'] ?? ''),
            'irs_estado_civil' => $irsEstadoCivil,
            'conjugue' => $conjugue,
            'nif_conjugue' => $conjugue ? nullable_text($_POST['nif_conjugue'] ?? '') : null,
            'residencia_irs' => nullable_text($_POST['residencia_irs'] ?? ''),
            'beneficio_fiscal' => nullable_text($_POST['beneficio_fiscal'] ?? ''),
            'numero_filhos' => nullable_int($_POST['numero_filhos'] ?? ''),
            'dependentes_deducao' => nullable_int($_POST['dependentes_deducao'] ?? ''),
            'deficientes_dependentes' => nullable_int($_POST['deficientes_dependentes'] ?? ''),
            'titularidade_dependentes' => nullable_int($_POST['titularidade_dependentes'] ?? ''),
            'categoria_profissional' => $categoria,
            'irct' => nullable_text($_POST['irct'] ?? ''),
            'cct' => nullable_text($_POST['cct'] ?? ''),
            'nivel_profissional' => nullable_text($_POST['nivel_profissional'] ?? ''),
            'defice_percentagem_paga' => nullable_decimal($_POST['defice_percentagem_paga'] ?? ''),
            'moeda' => nullable_text($_POST['moeda'] ?? ''),
            'seccao' => nullable_text($_POST['seccao'] ?? ''),
            'local_pagamento' => nullable_text($_POST['local_pagamento'] ?? ''),
            'codigo_subsidio_natal' => nullable_text($_POST['codigo_subsidio_natal'] ?? ''),
            'codigo_subsidio_ferias' => nullable_text($_POST['codigo_subsidio_ferias'] ?? ''),
            'tipo_horario' => nullable_text($_POST['tipo_horario'] ?? ''),
            'tipo_horario_codigo' => nullable_text($_POST['tipo_horario_codigo'] ?? ''),
            'tipo_horario_unidade' => nullable_text($_POST['tipo_horario_unidade'] ?? ''),
            'tipo_horario_tratamento' => nullable_text($_POST['tipo_horario_tratamento'] ?? ''),
            'tipo_horario_desc_semanal' => nullable_text($_POST['tipo_horario_desc_semanal'] ?? ''),
            'carga_horaria_semanal' => $cargaHoraria,
            'horas_mes' => $horasMes,
            'salario_mensal' => $salarioMensal,
            'seguranca_social_codigo' => nullable_text($_POST['seguranca_social_codigo'] ?? ''),
            'seguranca_social_numero_beneficiario' => nullable_text($_POST['seguranca_social_numero_beneficiario'] ?? ''),
            'relatorio_unico_estabelecimento' => nullable_text($_POST['relatorio_unico_estabelecimento'] ?? ''),
            'relatorio_unico_habilitacoes' => nullable_text($_POST['relatorio_unico_habilitacoes'] ?? ''),
            'relatorio_unico_profissao' => nullable_text($_POST['relatorio_unico_profissao'] ?? ''),
            'relatorio_unico_situacao' => nullable_text($_POST['relatorio_unico_situacao'] ?? ''),
            'relatorio_unico_nivel' => nullable_text($_POST['relatorio_unico_nivel'] ?? ''),
            'relatorio_unico_nacionalidade' => nullable_text($_POST['relatorio_unico_nacionalidade'] ?? ''),
            'relatorio_unico_regime' => nullable_text($_POST['relatorio_unico_regime'] ?? ''),
            'carta_conducao_numero' => nullable_text($_POST['carta_conducao_numero'] ?? ''),
            'carta_conducao_categoria_1' => nullable_text($_POST['carta_conducao_categoria_1'] ?? ''),
            'carta_conducao_categoria_2' => nullable_text($_POST['carta_conducao_categoria_2'] ?? ''),
            'carta_conducao_data_inicio' => nullable_date($_POST['carta_conducao_data_inicio'] ?? ''),
            'carta_conducao_data_validade' => nullable_date($_POST['carta_conducao_data_validade'] ?? ''),
            'cursos' => nullable_text($_POST['cursos'] ?? ''),
            'linguas' => nullable_text($_POST['linguas'] ?? ''),
            'observacoes' => $observacoes,
            'seguro' => nullable_text($_POST['seguro'] ?? ''),
            'sindicato' => nullable_text($_POST['sindicato'] ?? ''),
            'sindicato_numero' => nullable_text($_POST['sindicato_numero'] ?? ''),
            'banco' => nullable_text($_POST['banco'] ?? ''),
            'iban' => nullable_text($_POST['iban'] ?? ''),
            'conta_empresa' => nullable_text($_POST['conta_empresa'] ?? ''),
            'dados_fixos_codigo' => nullable_text($_POST['dados_fixos_codigo'] ?? ''),
            'dados_fixos_designacao' => nullable_text($_POST['dados_fixos_designacao'] ?? ''),
            'dados_fixos_quantidade' => nullable_text($_POST['dados_fixos_quantidade'] ?? ''),
            'dados_fixos_valor' => nullable_decimal($_POST['dados_fixos_valor'] ?? ''),
            'pin_ponto' => $pinPonto,
            'codigo_picagem' => $pinPonto,
            'codigo_cartao' => $codigoCartao,
            'codigo_biometrico' => $codigoBiometrico,
            'estado' => $estado,
        ];

        if ($nome === '') {
            redirect_with_message('danger', 'Preencha o nome do funcionário.');
        }

        if (!has_max_digits($numeroMecanograficoRaw, 10)) {
            redirect_with_message('danger', 'O numero mecanografico so pode conter algarismos.');
        }

        if ($numeroMecanografico !== null && $numeroMecanografico <= 0) {
            redirect_with_message('danger', 'O numero mecanografico deve ser superior a zero.');
        }

        $requiredLabels = [
            'entidade' => 'Entidade',
            'numero_mecanografico' => 'Número mec',
            'data_ficha' => 'Data',
            'doc_identificacao' => 'Doc de identificação',
            'data_validade_doc' => 'Data de validade',
            'naturalidade' => 'Naturalidade',
            'genero' => 'Género',
            'estado_civil' => 'Estado civil',
            'data_nascimento' => 'Data de nascimento',
            'morada' => 'Morada',
            'localidade' => 'Localidade',
            'codigo_postal' => 'Código postal',
            'data_admissao' => 'Admissão',
            'codigo_admissao' => 'Código de admissão',
            'nif' => 'NIF',
            'irs_estado_civil' => 'Estado civil de I.R.S',
            'beneficio_fiscal' => 'B. Fiscal',
            'numero_filhos' => 'N. Filhos',
            'dependentes_deducao' => 'N. Dependentes com dedução',
            'titularidade_dependentes' => 'N. Dependentes',
            'categoria_profissional' => 'Categoria',
            'nivel_profissional' => 'Nível profissional',
            'defice_percentagem_paga' => 'Defice% paga',
            'moeda' => 'Moeda',
            'tipo_horario_codigo' => 'Código do tipo horário',
            'tipo_horario_unidade' => 'Unidade',
            'tipo_horario_tratamento' => 'Tratamento',
            'carga_horaria_semanal' => 'Horas por semana',
            'horas_mes' => 'Horas por mês',
            'seguranca_social_codigo' => 'Código da Segurança Social',
            'relatorio_unico_situacao' => 'Situação do Relatório Único',
            'seguro' => 'Seguro',
            'banco' => 'Banco',
            'iban' => 'IBAN',
            'conta_empresa' => 'Conta empresa',
        ];

        foreach ($requiredLabels as $field => $label) {
            if (($funcionarioDados[$field] ?? null) === null || $funcionarioDados[$field] === '') {
                redirect_with_message('danger', 'Preencha o campo obrigatório: ' . $label . '.');
            }
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect_with_message('danger', 'Introduza um email válido.');
        }

        if ($codigoPostal !== null && !preg_match('/^\d{4}-\d{3}$/', $codigoPostal)) {
            redirect_with_message('danger', 'Introduza um código postal válido no formato 0000-000.');
        }

        $estadoCivilOpcoes = estado_civil_opcoes();
        if (!in_array($estadoCivil, $estadoCivilOpcoes, true) || !in_array($irsEstadoCivil, $estadoCivilOpcoes, true)) {
            redirect_with_message('danger', 'Selecione um estado civil valido.');
        }

        $hoje = date('Y-m-d');
        if ($funcionarioDados['data_validade_doc'] !== null && $funcionarioDados['data_validade_doc'] < $hoje) {
            redirect_with_message('danger', 'A data de validade nao pode ser anterior a data atual.');
        }

        if ($funcionarioDados['carta_conducao_data_validade'] !== null && $funcionarioDados['carta_conducao_data_validade'] < $hoje) {
            redirect_with_message('danger', 'A data de validade da carta de conducao nao pode ser anterior a data atual.');
        }

        if ($dataNascimento !== null && $dataNascimento >= $hoje) {
            redirect_with_message('danger', 'A data de nascimento tem de ser anterior a data atual.');
        }

        if (!has_max_digits($telefoneRaw, 9) || !has_max_digits($telemovelRaw, 9)) {
            redirect_with_message('danger', 'Telefone e telemovel so podem ter ate 9 digitos.');
        }

        if (!is_decimal_value($_POST['tipo_contrato_taxa_irs'] ?? '') || !is_decimal_value($_POST['novo_tipo_contrato_taxa_irs'] ?? '')) {
            redirect_with_message('danger', 'A taxa de I.R.S. so pode conter numeros.');
        }

        if (!has_exact_digits($_POST['seguranca_social_numero_beneficiario'] ?? '', 11)) {
            redirect_with_message('danger', 'O numero de beneficiario tem de ter exatamente 11 digitos.');
        }

        if ($cargaHoraria <= 0) {
            redirect_with_message('danger', 'A carga horaria semanal deve ser superior a zero.');
        }

        if (!in_array($estado, ['ativo', 'suspenso', 'inativo'], true)) {
            $estado = 'ativo';
        }

        try {
            if ($acao === 'criar') {
                $columns = array_keys($funcionarioDados);
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $quotedColumns = '`' . implode('`, `', $columns) . '`';
                $types = str_repeat('s', count($columns));
                $params = array_values($funcionarioDados);
                execute_prepared($conn, "INSERT INTO funcionarios ($quotedColumns) VALUES ($placeholders)", $types, $params);
                $novoFuncionarioId = mysqli_insert_id($conn);

                if ($temCamposNotificações) {
                    $stmt = mysqli_prepare($conn, 'UPDATE funcionarios SET data_nascimento = ?, diuturnidade_data_base = ?, diuturnidade_ciclo_anos = ?, diuturnidade_ativa = ? WHERE id = ?');
                    mysqli_stmt_bind_param($stmt, 'ssiii', $dataNascimento, $diuturnidadeDataBase, $diuturnidadeCicloAnos, $diuturnidadeAtiva, $novoFuncionarioId);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }

                redirect_with_message('success', 'Funcionário criado com sucesso.');
            }

            if ($id <= 0) {
                redirect_with_message('danger', 'Funcionário inválido.');
            }

            $assignments = implode(', ', array_map(static function ($column) {
                return "`$column` = ?";
            }, array_keys($funcionarioDados)));
            $params = array_values($funcionarioDados);
            $params[] = $id;
            execute_prepared($conn, "UPDATE funcionarios SET $assignments WHERE id = ?", str_repeat('s', count($funcionarioDados)) . 'i', $params);

            if ($temCamposNotificações && $podeVerDadosSensiveis) {
                $stmt = mysqli_prepare($conn, 'UPDATE funcionarios SET data_nascimento = ?, diuturnidade_data_base = ?, diuturnidade_ciclo_anos = ?, diuturnidade_ativa = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'ssiii', $dataNascimento, $diuturnidadeDataBase, $diuturnidadeCicloAnos, $diuturnidadeAtiva, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            } elseif ($temCamposNotificações) {
                $stmt = mysqli_prepare($conn, 'UPDATE funcionarios SET diuturnidade_data_base = ?, diuturnidade_ciclo_anos = ?, diuturnidade_ativa = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'siii', $diuturnidadeDataBase, $diuturnidadeCicloAnos, $diuturnidadeAtiva, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            redirect_with_message('success', 'Funcionário atualizado com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível guardar o funcionário. Verifique campos unicos como número mecanográfico, PIN, cartao ou código biométrico.');
        }
    }

    if ($acao === 'desativar') {
        $id = (int) ($_POST['id'] ?? 0);
        $motivo = nullable_text($_POST['motivo_desativacao'] ?? '');

        if ($id <= 0) {
            redirect_with_message('danger', 'Funcionário inválido.');
        }

        try {
            // Prefer to store deactivation date and motivo if columns exist
            $sql = 'SHOW COLUMNS FROM funcionarios LIKE "data_desativacao"';
            $res = mysqli_query($conn, $sql);
            $hasDataCol = mysqli_num_rows($res) > 0;

            $sql = 'SHOW COLUMNS FROM funcionarios LIKE "motivo_desativacao"';
            $res2 = mysqli_query($conn, $sql);
            $hasMotivoCol = mysqli_num_rows($res2) > 0;

            if ($hasDataCol && $hasMotivoCol) {
                $estadoInativo = 'inativo';
                $stmt = mysqli_prepare($conn, 'UPDATE funcionarios SET estado = ?, data_desativacao = NOW(), motivo_desativacao = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'ssi', $estadoInativo, $motivo, $id);
            } else {
                $estadoInativo = 'inativo';
                $stmt = mysqli_prepare($conn, 'UPDATE funcionarios SET estado = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'si', $estadoInativo, $id);
            }

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Funcionário desativado com sucesso. Os registos históricos foram preservados.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível desativar o funcionário.');
        }
    }

    if ($acao === 'reativar') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            redirect_with_message('danger', 'Funcionário inválido.');
        }

        try {
            // Clear deactivation metadata if present
            $sql = 'SHOW COLUMNS FROM funcionarios LIKE "data_desativacao"';
            $res = mysqli_query($conn, $sql);
            $hasDataCol = mysqli_num_rows($res) > 0;

            $sql = 'SHOW COLUMNS FROM funcionarios LIKE "motivo_desativacao"';
            $res2 = mysqli_query($conn, $sql);
            $hasMotivoCol = mysqli_num_rows($res2) > 0;

            if ($hasDataCol && $hasMotivoCol) {
                $estadoAtivo = 'ativo';
                $stmt = mysqli_prepare($conn, 'UPDATE funcionarios SET estado = ?, data_desativacao = NULL, motivo_desativacao = NULL WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'si', $estadoAtivo, $id);
            } else {
                $estadoAtivo = 'ativo';
                $stmt = mysqli_prepare($conn, 'UPDATE funcionarios SET estado = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'si', $estadoAtivo, $id);
            }

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Funcionário reativado com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível reativar o funcionário.');
        }
    }
}

$equipas = [];
if ($temEquipas) {
    $selectSetor = fe_column_exists($conn, 'equipas', 'setor_id') ? ', setor_id' : '';
    $stmt = mysqli_prepare($conn, "SELECT id, nome$selectSetor FROM equipas WHERE ativo = 1 ORDER BY nome ASC");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $equipas[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$tiposContrato = [];
if (empty($missingTables) && fe_table_exists($conn, 'funcionario_tipos_contrato')) {
    $stmt = mysqli_prepare($conn, 'SELECT id, codigo, nome, tipo_rendimento, taxa_irs FROM funcionario_tipos_contrato WHERE ativo = 1 ORDER BY nome ASC');
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $tiposContrato[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Lista com opção de mostrar inativos
$funcionarios = [];
$showInativos = ($_GET['show_inativos'] ?? '') === '1';
if (empty($missingTables)) {
    $selectEquipa = $temEquipas ? 'eq.nome AS equipa_nome' : 'NULL AS equipa_nome';
    $joinEquipa = $temEquipas ? 'LEFT JOIN equipas eq ON eq.id = f.equipa_id' : '';

    $where = $showInativos ? "" : "WHERE f.estado = 'ativo'";

    $sql = "SELECT f.*, $selectEquipa
        FROM funcionarios f
        $joinEquipa
        $where
        ORDER BY f.estado = 'ativo' DESC, f.nome ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $funcionarios[] = $row;
    }
    mysqli_stmt_close($stmt);
}

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
                        <h3 class="fw-bold mb-3">Funcionários</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="principal.php"><i class="icon-home"></i></a>
                            </li>
                            <li class="separator"><i class="icon-arrow-right"></i></li>
                            <li class="nav-item"><a href="funcionarios.php">Funcionários</a></li>
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
                            Execute o schema <code>database/schema_completo.sql</code> antes de gerir funcionários.
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <h4 class="card-title">Lista de funcionários</h4>
                                <div class="ms-auto d-flex align-items-center gap-2">
                                    <a class="btn btn-outline-secondary btn-sm" href="funcionarios.php?show_inativos=<?php echo $showInativos ? '0' : '1'; ?>">
                                        <?php echo $showInativos ? 'Ocultar inativos' : 'Mostrar inativos'; ?>
                                    </a>
                                    <button class="btn btn-primary btn-round" data-bs-toggle="modal" data-bs-target="#modalCriarFuncionario" <?php echo !empty($missingTables) ? 'disabled' : ''; ?>>
                                        <i class="fa fa-plus"></i>
                                        Adicionar funcionário
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-funcionarios" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>N.º mec.</th>
                                            <th>Nome</th>
                                            <th>Função</th>
                                            <th>Equipa</th>
                                            <th>Biometria</th>
                                            <th>Estado</th>
                                            <th style="width: 120px">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($funcionarios as $funcionario): ?>
                                            <tr>
                                                <td><?php echo e($funcionario['numero_mecanografico'] ?: '-'); ?></td>
                                                <td>
                                                    <div class="fw-bold"><?php echo e($funcionario['nome']); ?></div>
                                                    <small class="text-muted"><?php echo $podeVerDadosSensiveis ? e($funcionario['email'] ?: $funcionario['telefone'] ?: 'Sem contacto') : 'Contacto protegido'; ?></small>
                                                </td>
                                                <td><?php echo e($funcionario['funcao'] ?: '-'); ?></td>
                                                <td><?php echo e($funcionario['equipa_nome'] ?: '-'); ?></td>
                                                <td><?php echo e($funcionario['codigo_biometrico'] ?: '-'); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo e(funcionario_estado_badge($funcionario['estado'])); ?>">
                                                        <?php echo e(ucfirst($funcionario['estado'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="form-button-action">
                                                        <button type="button" class="btn btn-link btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#modalEditarFuncionario<?php echo (int) $funcionario['id']; ?>" title="Editar">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                        <?php if ($funcionario['estado'] !== 'ativo'): ?>
                                                            <form method="post" style="display:inline" onsubmit="return confirm('Reativar funcionário?');">
                                                                <input type="hidden" name="acao" value="reativar">
                                                                <input type="hidden" name="id" value="<?php echo (int) $funcionario['id']; ?>">
                                                                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                                                <button type="submit" class="btn btn-link btn-success btn-lg" title="Reativar">
                                                                    <i class="fa fa-undo"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
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

    <div class="modal fade" id="modalCriarFuncionario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <form method="post" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="acao" value="criar">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Adicionar funcionário</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <div class="modal-body">
                    <?php
                    $funcionarioForm = [
                        'id' => 0,
                        'nome' => '',
                        'numero_mecanografico' => '',
                        'email' => '',
                        'telefone' => '',
                        'funcao' => '',
                        'data_nascimento' => '',
                        'equipa_id' => '',
                        'data_admissao' => '',
                        'diuturnidade_data_base' => '',
                        'diuturnidade_ciclo_anos' => '',
                        'diuturnidade_ativa' => 1,
                        'data_cessacao' => '',
                        'tipo_contrato' => '',
                        'carga_horaria_semanal' => '40.00',
                        'pin_ponto' => '',
                        'codigo_cartao' => '',
                        'codigo_biometrico' => '',
                        'estado' => 'ativo',
                        'observacoes' => '',
                    ];
                    include __DIR__ . '/includes/funcionario_form_campos.php';
                    ?>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($funcionarios as $funcionario): ?>
        <div class="modal fade" id="modalEditarFuncionario<?php echo (int) $funcionario['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl" role="document">
                <form method="post" class="modal-content needs-validation" novalidate>
                    <input type="hidden" name="id" value="<?php echo (int) $funcionario['id']; ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Editar funcionário</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                    <div class="modal-body">
                        <?php
                        $funcionarioForm = $funcionario;
                        include __DIR__ . '/includes/funcionario_form_campos.php';
                        ?>
                        <?php if ($funcionario['estado'] === 'ativo'): ?>
                            <hr>
                            <div class="mb-0">
                                <p class="mb-2">Desativar este funcionario nao elimina dados historicos.</p>
                                <label class="form-label">Motivo da desativacao (opcional)</label>
                                <textarea name="motivo_desativacao" class="form-control" rows="3"></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer border-0">
                        <?php if ($funcionario['estado'] === 'ativo'): ?>
                            <button type="submit" name="acao" value="desativar" class="btn btn-warning me-auto" formnovalidate onclick="return confirm('Tem a certeza que pretende desativar este funcionario? Os registos historicos serao preservados.');">Desativar</button>
                        <?php endif; ?>
                        <button type="submit" name="acao" value="editar" class="btn btn-primary">Guardar alterações</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

    <?php endforeach; ?>

    <style>
        .funcionario-wizard .wizard-page {
            display: none;
            min-height: 360px;
        }

        .funcionario-wizard .wizard-page.active {
            display: block;
        }

        .funcionario-wizard h6 {
            border-bottom: 1px solid #eee;
            padding-bottom: .5rem;
        }
    </style>

    <?php include 'includes/scripts.php'; ?>
    <script>
        $(document).ready(function () {
            $('#tabela-funcionarios').DataTable({
                pageLength: 10,
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

            function refreshWizard($wizard) {
                var $pages = $wizard.find('.wizard-page');
                var index = Number($wizard.data('step') || 0);
                var total = $pages.length;
                var $current = $pages.eq(index);

                $pages.removeClass('active');
                $current.addClass('active');
                $wizard.find('.wizard-prev').prop('disabled', index === 0);
                $wizard.find('.wizard-next').text(index === total - 1 ? 'Concluir' : 'Seguinte');
                $wizard.find('.wizard-step-label').text('Página ' + (index + 1) + ' de ' + total);
                $wizard.find('.wizard-current-title').text($current.data('title') || '');
                $wizard.find('.wizard-progress-bar').css('width', (((index + 1) / total) * 100) + '%');
            }

            function validateWizardPage($wizard) {
                var valid = true;
                $wizard.find('.wizard-page.active').find('input, select, textarea').each(function () {
                    if (!this.checkValidity()) {
                        valid = false;
                        this.reportValidity();
                        return false;
                    }
                });

                return valid;
            }

            function getToday() {
                return new Date().toISOString().slice(0, 10);
            }

            function filterDigits($input, maxLength) {
                var digits = String($input.val() || '').replace(/\D/g, '');
                if (maxLength) {
                    digits = digits.slice(0, maxLength);
                }
                $input.val(digits);
            }

            function filterDecimal($input) {
                var value = String($input.val() || '').replace(/[^\d,.]/g, '');
                var parts = value.split(/[,.]/);
                if (parts.length > 1) {
                    value = parts.shift() + '.' + parts.join('');
                }
                $input.val(value);
            }

            function toggleNifConjugue($wizard) {
                var checked = $wizard.find('.js-conjugue-toggle').is(':checked');
                $wizard.find('.js-nif-conjugue').prop('disabled', !checked);
            }

            function fillContrato($select) {
                var $option = $select.find('option:selected');
                var $wizard = $select.closest('.funcionario-wizard');
                $wizard.find('input[name="tipo_contrato"]').val($option.val() ? $option.text().trim() : '');
                $wizard.find('.js-contrato-codigo').val($option.data('codigo') || '');
                $wizard.find('.js-contrato-rendimento').val($option.data('rendimento') || '');
                $wizard.find('.js-contrato-taxa').val($option.data('taxa') || '');
            }

            function fillPinPonto($wizard) {
                var $numero = $wizard.find('.js-numero-mecanografico');
                var $pin = $wizard.find('.js-pin-ponto');
                var previous = String($numero.data('previous') || '');
                var pin = String($pin.val() || '');

                if (pin === '' || pin === previous) {
                    $pin.val($numero.val());
                }

                $numero.data('previous', $numero.val());
            }

            function focusNextControl(current) {
                var $form = $(current).closest('form');
                var $controls = $form.find('input, select, textarea')
                    .filter(':visible:not(:disabled)')
                    .filter(function () {
                        return $(this).attr('type') !== 'hidden';
                    });
                var index = $controls.index(current);
                var next = $controls.get(index + 1);

                if (next) {
                    next.focus();
                } else {
                    $(current).closest('.funcionario-wizard').find('.wizard-next').trigger('click');
                }
            }

            var draftKey = 'funcionario_add_draft_v1';
            var pendingCreateKey = 'funcionario_add_pending_create_v1';

            function serializeDraft($form) {
                var data = {};
                $form.find('input[name], select[name], textarea[name]').each(function () {
                    var name = this.name;
                    if (['acao', 'csrf_token'].indexOf(name) !== -1 || name.indexOf('novo_tipo_contrato_') === 0) {
                        return;
                    }

                    if (this.type === 'checkbox') {
                        data[name] = this.checked ? '1' : '0';
                    } else {
                        data[name] = $(this).val();
                    }
                });
                data.__step = Number($form.find('.funcionario-wizard').data('step') || 0);
                return data;
            }

            function saveDraft($form) {
                try {
                    sessionStorage.setItem(draftKey, JSON.stringify(serializeDraft($form)));
                } catch (e) {}
            }

            function restoreDraft($form) {
                var raw = null;
                try {
                    raw = sessionStorage.getItem(draftKey);
                } catch (e) {}
                if (!raw) {
                    return;
                }

                var data = {};
                try {
                    data = JSON.parse(raw);
                } catch (e) {
                    return;
                }

                Object.keys(data).forEach(function (name) {
                    if (name === '__step') {
                        return;
                    }

                    var $fields = $form.find('[name="' + name + '"]');
                    $fields.each(function () {
                        if (this.type === 'checkbox') {
                            this.checked = data[name] === '1';
                        } else {
                            $(this).val(data[name]);
                        }
                    });
                });

                var $wizard = $form.find('.funcionario-wizard');
                $wizard.data('step', Math.max(0, Number(data.__step || 0)));
                toggleNifConjugue($wizard);
                if ($wizard.find('.js-tipo-contrato').val()) {
                    fillContrato($wizard.find('.js-tipo-contrato'));
                }
                fillPinPonto($wizard);
                refreshWizard($wizard);
            }

            $('.funcionario-wizard').each(function () {
                var $wizard = $(this);
                $wizard.data('step', 0);
                $wizard.find('.js-numero-mecanografico').data('previous', $wizard.find('.js-numero-mecanografico').val());
                toggleNifConjugue($wizard);
                if ($wizard.find('.js-tipo-contrato').val()) {
                    fillContrato($wizard.find('.js-tipo-contrato'));
                }
                fillPinPonto($wizard);
                refreshWizard($wizard);
            });

            $(document).on('click', '.wizard-prev', function () {
                var $wizard = $(this).closest('.funcionario-wizard');
                var index = Number($wizard.data('step') || 0);
                $wizard.data('step', Math.max(0, index - 1));
                refreshWizard($wizard);
                if ($wizard.closest('#modalCriarFuncionario').length) {
                    saveDraft($wizard.closest('form'));
                }
            });

            $(document).on('click', '.wizard-next', function () {
                var $wizard = $(this).closest('.funcionario-wizard');
                var $pages = $wizard.find('.wizard-page');
                var index = Number($wizard.data('step') || 0);

                if (!validateWizardPage($wizard)) {
                    return;
                }

                if (index >= $pages.length - 1) {
                    $wizard.closest('form').trigger('submit');
                    return;
                }

                $wizard.data('step', index + 1);
                refreshWizard($wizard);
                if ($wizard.closest('#modalCriarFuncionario').length) {
                    saveDraft($wizard.closest('form'));
                }
            });

            $(document).on('change', '.js-tipo-contrato', function () {
                fillContrato($(this));
            });

            $(document).on('change', '.js-estado-civil', function () {
                var $wizard = $(this).closest('.funcionario-wizard');
                $wizard.find('.js-irs-estado-civil').val($(this).val());
            });

            $(document).on('change', '.js-irs-estado-civil', function () {
                var $wizard = $(this).closest('.funcionario-wizard');
                $wizard.find('.js-estado-civil').val($(this).val());
            });

            $(document).on('input', '.js-codigo-postal', function () {
                var digits = $(this).val().replace(/\D/g, '').slice(0, 7);
                if (digits.length > 4) {
                    digits = digits.slice(0, 4) + '-' + digits.slice(4);
                }
                $(this).val(digits);
            });

            $(document).on('input', '.js-digits-only', function () {
                filterDigits($(this), Number($(this).attr('maxlength') || 0));
            });

            $(document).on('input', '.js-decimal-only', function () {
                filterDecimal($(this));
            });

            $(document).on('input', '.js-numero-mecanografico', function () {
                fillPinPonto($(this).closest('.funcionario-wizard'));
            });

            $(document).on('change', '.js-numero-mecanografico', function () {
                fillPinPonto($(this).closest('.funcionario-wizard'));
            });

            $(document).on('change', '.js-conjugue-toggle', function () {
                toggleNifConjugue($(this).closest('.funcionario-wizard'));
            });

            $(document).on('change', '.js-date-not-past', function () {
                if ($(this).val() && $(this).val() < getToday()) {
                    this.setCustomValidity('A data de validade nao pode ser anterior a data atual.');
                } else {
                    this.setCustomValidity('');
                }
            });

            $(document).on('change', '.js-date-before-today', function () {
                if ($(this).val() && $(this).val() >= getToday()) {
                    this.setCustomValidity('A data de nascimento tem de ser anterior a data atual.');
                } else {
                    this.setCustomValidity('');
                }
            });

            $(document).on('keydown', '.funcionario-wizard input, .funcionario-wizard select', function (event) {
                if (event.key !== 'Enter') {
                    return;
                }

                event.preventDefault();
                focusNextControl(this);
            });

            var $criarFuncionarioForm = $('#modalCriarFuncionario form');
            restoreDraft($criarFuncionarioForm);

            $criarFuncionarioForm.on('input change', 'input[name], select[name], textarea[name]', function () {
                saveDraft($criarFuncionarioForm);
            });

            $('.needs-validation').on('submit', function (event) {
                var $form = $(this);
                if (($form.find('button[type="submit"][clicked=true]').val() || '') === 'criar_tipo_contrato') {
                    return;
                }

                var $wizard = $form.find('.funcionario-wizard');
                var firstInvalidPage = -1;
                var firstInvalidControl = null;

                $wizard.find('.wizard-page').each(function (pageIndex) {
                    $(this).find('input, select, textarea').each(function () {
                        if (!this.checkValidity()) {
                            firstInvalidPage = pageIndex;
                            firstInvalidControl = this;
                            return false;
                        }
                    });

                    return firstInvalidPage === -1;
                });

                if (firstInvalidPage !== -1) {
                    event.preventDefault();
                    event.stopPropagation();
                    $wizard.data('step', firstInvalidPage);
                    refreshWizard($wizard);
                    setTimeout(function () {
                        firstInvalidControl.reportValidity();
                    }, 50);
                }

                if (firstInvalidPage === -1 && $form.closest('#modalCriarFuncionario').length) {
                    try {
                        sessionStorage.setItem(pendingCreateKey, '1');
                    } catch (e) {}
                }

                $form.addClass('was-validated');
            });

            $(document).on('click', 'button[type="submit"]', function () {
                $('button[type="submit"]').removeAttr('clicked');
                $(this).attr('clicked', 'true');
            });

            var params = new URLSearchParams(window.location.search);
            if (params.get('type') === 'success' && params.get('modal') !== 'adicionar_funcionario') {
                try {
                    if (sessionStorage.getItem(pendingCreateKey) === '1') {
                        sessionStorage.removeItem(draftKey);
                        sessionStorage.removeItem(pendingCreateKey);
                    }
                } catch (e) {}
            }

            if (params.get('modal') === 'adicionar_funcionario') {
                var $modal = $('#modalCriarFuncionario');
                var step = parseInt(params.get('step') || '0', 10);
                var $wizard = $modal.find('.funcionario-wizard');
                $wizard.data('step', isNaN(step) ? 0 : step);
                refreshWizard($wizard);
                $modal.modal('show');
            }
        });
    </script>
</body>

</html>

