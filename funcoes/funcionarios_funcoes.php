<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_with_message($type, $message, array $params = [])
{
    header('Location: funcionarios.php?' . http_build_query(array_merge($params, [
        'type' => $type,
        'message' => $message,
    ])));
    exit;
}

function normalizar_codigo_postal($value)
{
    $digits = preg_replace('/\D+/', '', (string) $value);

    if ($digits === '') {
        return null;
    }

    $digits = substr($digits, 0, 7);

    if (strlen($digits) <= 4) {
        return $digits;
    }

    return substr($digits, 0, 4) . '-' . substr($digits, 4);
}

function estado_civil_opcoes()
{
    return [
        'Solteiro',
        'Casado',
        'Divorciado',
        'Separado',
        'Viúvo',
    ];
}

function normalizar_estado_civil($value)
{
    $value = trim((string) $value);
    $legacy = [
        'Solteiro/a' => 'Solteiro',
        'Casado/a' => 'Casado',
        'Divorciado/a' => 'Divorciado',
        'Separado/a' => 'Separado',
        'Viuvo/a' => 'Viúvo',
    ];

    if (isset($legacy[$value])) {
        return $legacy[$value];
    }

    if (preg_match('/^Vi.*vo\/a$/u', $value) === 1) {
        return 'Viúvo';
    }

    return $value;
}

function get_post_value($key)
{
    return trim($_POST[$key] ?? '');
}

function nullable_text($value)
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function nullable_int($value)
{
    return $value === '' ? null : (int) $value;
}

function nullable_date($value)
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function nullable_decimal($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    return (float) str_replace(',', '.', $value);
}

function is_decimal_value($value)
{
    $value = trim((string) $value);
    return $value === '' || preg_match('/^\d+(?:[,.]\d+)?$/', $value) === 1;
}

function has_max_digits($value, $maxDigits)
{
    $value = trim((string) $value);
    return $value === '' || preg_match('/^\d{1,' . (int) $maxDigits . '}$/', $value) === 1;
}

function has_exact_digits($value, $digits)
{
    $value = trim((string) $value);
    return $value === '' || preg_match('/^\d{' . (int) $digits . '}$/', $value) === 1;
}

function ensure_column($conn, $table, $column, $definition)
{
    if (!fe_table_exists($conn, $table) || fe_column_exists($conn, $table, $column)) {
        return;
    }

    mysqli_query($conn, "ALTER TABLE `$table` ADD COLUMN `$column` $definition");
}

function ensure_funcionarios_extended_schema($conn)
{
    if (!fe_table_exists($conn, 'funcionarios')) {
        return;
    }

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS funcionario_tipos_contrato (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        codigo VARCHAR(40) DEFAULT NULL,
        nome VARCHAR(120) NOT NULL,
        tipo_rendimento VARCHAR(120) DEFAULT NULL,
        taxa_irs DECIMAL(6,2) DEFAULT NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_funcionario_tipos_contrato_nome (nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    mysqli_query($conn, "INSERT IGNORE INTO funcionario_tipos_contrato (codigo, nome, tipo_rendimento, taxa_irs) VALUES
        ('1', 'TRABALHO DEPENDENTE', 'A - TRABALHO DEPENDENTE', NULL)");

    $columns = [
        'codigo_picagem' => "VARCHAR(80) DEFAULT NULL AFTER `equipa_id`",
        'codigo_picagem_hash' => "VARCHAR(255) DEFAULT NULL AFTER `codigo_picagem`",
        'codigo_picagem_tentativas' => "INT NOT NULL DEFAULT 0 AFTER `codigo_picagem_hash`",
        'codigo_picagem_bloqueado_ate' => "DATETIME DEFAULT NULL AFTER `codigo_picagem_tentativas`",
        'data_nascimento' => "DATE DEFAULT NULL AFTER `telefone`",
        'tipo_horario' => "VARCHAR(80) DEFAULT NULL AFTER `tipo_contrato`",
        'entidade' => "VARCHAR(180) DEFAULT NULL AFTER `equipa_id`",
        'data_ficha' => "DATE DEFAULT NULL AFTER `numero_mecanografico`",
        'doc_identificacao' => "VARCHAR(80) DEFAULT NULL AFTER `data_nascimento`",
        'data_validade_doc' => "DATE DEFAULT NULL AFTER `doc_identificacao`",
        'local_emissao' => "VARCHAR(120) DEFAULT NULL AFTER `data_validade_doc`",
        'naturalidade' => "VARCHAR(120) DEFAULT NULL AFTER `local_emissao`",
        'codigo_residencia' => "VARCHAR(40) DEFAULT NULL AFTER `naturalidade`",
        'genero' => "VARCHAR(30) DEFAULT NULL AFTER `codigo_residencia`",
        'estado_civil' => "VARCHAR(60) DEFAULT NULL AFTER `genero`",
        'tipo_contrato_id' => "INT UNSIGNED DEFAULT NULL AFTER `tipo_contrato`",
        'tipo_contrato_codigo' => "VARCHAR(40) DEFAULT NULL AFTER `tipo_contrato_id`",
        'tipo_contrato_tipo_rendimento' => "VARCHAR(120) DEFAULT NULL AFTER `tipo_contrato_codigo`",
        'tipo_contrato_taxa_irs' => "DECIMAL(6,2) DEFAULT NULL AFTER `tipo_contrato_tipo_rendimento`",
        'morada' => "VARCHAR(255) DEFAULT NULL AFTER `telefone`",
        'localidade' => "VARCHAR(120) DEFAULT NULL AFTER `morada`",
        'codigo_pais' => "VARCHAR(20) DEFAULT NULL AFTER `localidade`",
        'codigo_postal' => "VARCHAR(20) DEFAULT NULL AFTER `codigo_pais`",
        'telemovel' => "VARCHAR(40) DEFAULT NULL AFTER `codigo_postal`",
        'codigo_admissao' => "VARCHAR(40) DEFAULT NULL AFTER `data_admissao`",
        'codigo_demissao' => "VARCHAR(40) DEFAULT NULL AFTER `data_cessacao`",
        'nif' => "VARCHAR(20) DEFAULT NULL AFTER `codigo_demissao`",
        'irs_estado_civil' => "VARCHAR(60) DEFAULT NULL AFTER `nif`",
        'conjugue' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `irs_estado_civil`",
        'nif_conjugue' => "VARCHAR(20) DEFAULT NULL AFTER `conjugue`",
        'residencia_irs' => "VARCHAR(120) DEFAULT NULL AFTER `nif_conjugue`",
        'beneficio_fiscal' => "VARCHAR(80) DEFAULT NULL AFTER `residencia_irs`",
        'numero_filhos' => "INT UNSIGNED DEFAULT NULL AFTER `beneficio_fiscal`",
        'dependentes_deducao' => "INT UNSIGNED DEFAULT NULL AFTER `numero_filhos`",
        'deficientes_dependentes' => "INT UNSIGNED DEFAULT NULL AFTER `dependentes_deducao`",
        'titularidade_dependentes' => "INT UNSIGNED DEFAULT NULL AFTER `deficientes_dependentes`",
        'irct' => "VARCHAR(120) DEFAULT NULL AFTER `categoria_profissional`",
        'cct' => "VARCHAR(120) DEFAULT NULL AFTER `irct`",
        'nivel_profissional' => "VARCHAR(80) DEFAULT NULL AFTER `cct`",
        'defice_percentagem_paga' => "DECIMAL(6,2) DEFAULT NULL AFTER `nivel_profissional`",
        'moeda' => "VARCHAR(10) DEFAULT NULL AFTER `defice_percentagem_paga`",
        'seccao' => "VARCHAR(120) DEFAULT NULL AFTER `moeda`",
        'local_pagamento' => "VARCHAR(120) DEFAULT NULL AFTER `seccao`",
        'codigo_subsidio_natal' => "VARCHAR(40) DEFAULT NULL AFTER `local_pagamento`",
        'codigo_subsidio_ferias' => "VARCHAR(40) DEFAULT NULL AFTER `codigo_subsidio_natal`",
        'tipo_horario_codigo' => "VARCHAR(40) DEFAULT NULL AFTER `tipo_horario`",
        'tipo_horario_unidade' => "VARCHAR(40) DEFAULT NULL AFTER `tipo_horario_codigo`",
        'tipo_horario_tratamento' => "VARCHAR(80) DEFAULT NULL AFTER `tipo_horario_unidade`",
        'tipo_horario_desc_semanal' => "VARCHAR(120) DEFAULT NULL AFTER `tipo_horario_tratamento`",
        'horas_mes' => "DECIMAL(6,2) DEFAULT NULL AFTER `carga_horaria_semanal`",
        'salario_mensal' => "DECIMAL(10,2) DEFAULT NULL AFTER `horas_mes`",
        'seguranca_social_codigo' => "VARCHAR(40) DEFAULT NULL AFTER `salario_mensal`",
        'seguranca_social_numero_beneficiario' => "VARCHAR(80) DEFAULT NULL AFTER `seguranca_social_codigo`",
        'relatorio_unico_estabelecimento' => "VARCHAR(120) DEFAULT NULL AFTER `seguranca_social_numero_beneficiario`",
        'relatorio_unico_habilitacoes' => "VARCHAR(120) DEFAULT NULL AFTER `relatorio_unico_estabelecimento`",
        'relatorio_unico_profissao' => "VARCHAR(120) DEFAULT NULL AFTER `relatorio_unico_habilitacoes`",
        'relatorio_unico_situacao' => "VARCHAR(120) DEFAULT NULL AFTER `relatorio_unico_profissao`",
        'relatorio_unico_nivel' => "VARCHAR(80) DEFAULT NULL AFTER `relatorio_unico_situacao`",
        'relatorio_unico_nacionalidade' => "VARCHAR(80) DEFAULT NULL AFTER `relatorio_unico_nivel`",
        'relatorio_unico_regime' => "VARCHAR(80) DEFAULT NULL AFTER `relatorio_unico_nacionalidade`",
        'carta_conducao_numero' => "VARCHAR(80) DEFAULT NULL AFTER `relatorio_unico_regime`",
        'carta_conducao_categoria_1' => "VARCHAR(40) DEFAULT NULL AFTER `carta_conducao_numero`",
        'carta_conducao_categoria_2' => "VARCHAR(40) DEFAULT NULL AFTER `carta_conducao_categoria_1`",
        'carta_conducao_data_inicio' => "DATE DEFAULT NULL AFTER `carta_conducao_categoria_2`",
        'carta_conducao_data_validade' => "DATE DEFAULT NULL AFTER `carta_conducao_data_inicio`",
        'cursos' => "TEXT DEFAULT NULL AFTER `carta_conducao_data_validade`",
        'linguas' => "TEXT DEFAULT NULL AFTER `cursos`",
        'seguro' => "VARCHAR(160) DEFAULT NULL AFTER `observacoes`",
        'sindicato' => "VARCHAR(120) DEFAULT NULL AFTER `seguro`",
        'sindicato_numero' => "VARCHAR(40) DEFAULT NULL AFTER `sindicato`",
        'banco' => "VARCHAR(120) DEFAULT NULL AFTER `sindicato_numero`",
        'iban' => "VARCHAR(60) DEFAULT NULL AFTER `banco`",
        'conta_empresa' => "VARCHAR(80) DEFAULT NULL AFTER `iban`",
        'dados_fixos_codigo' => "VARCHAR(40) DEFAULT NULL AFTER `conta_empresa`",
        'dados_fixos_designacao' => "VARCHAR(160) DEFAULT NULL AFTER `dados_fixos_codigo`",
        'dados_fixos_quantidade' => "VARCHAR(80) DEFAULT NULL AFTER `dados_fixos_designacao`",
        'dados_fixos_valor' => "DECIMAL(10,2) DEFAULT NULL AFTER `dados_fixos_quantidade`",
    ];

    foreach ($columns as $column => $definition) {
        ensure_column($conn, 'funcionarios', $column, $definition);
    }
}

function execute_prepared($conn, $sql, $types, array $params)
{
    $stmt = mysqli_prepare($conn, $sql);
    $bindParams = [$types];
    foreach ($params as $key => $value) {
        $bindParams[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function funcionario_estado_badge($estado)
{
    if ($estado === 'ativo') {
        return 'success';
    }

    if ($estado === 'suspenso') {
        return 'warning';
    }

    return 'secondary';
}

function funcionario_tem_dependencias($conn, $funcionarioId)
{
    $checks = [
        ['registos_ponto', 'funcionario_id'],
        ['horarios_turno', 'funcionario_id'],
        ['banco_horas', 'funcionario_id'],
        ['escala_funcionarios', 'funcionario_id'],
        ['ferias_ausencias', 'funcionario_id'],
    ];

    foreach ($checks as [$table, $column]) {
        if (!fe_table_exists($conn, $table) || !fe_column_exists($conn, $table, $column)) {
            continue;
        }

        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM $table WHERE $column = ?");
        mysqli_stmt_bind_param($stmt, 'i', $funcionarioId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ((int) ($row['total'] ?? 0) > 0) {
            return true;
        }
    }

    return false;
}
