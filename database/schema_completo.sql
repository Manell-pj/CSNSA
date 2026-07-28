-- Schema completo consolidado para gestor_assiduidade
-- Gerado a partir de schema.sql e das migracoes em database/.
-- Ordem: schema base, migracoes cronologicas.


-- ========================================================
-- Fonte: database/schema.sql
-- ========================================================

-- Schema inicial para sistema de assiduidade e RH
-- PHP procedural + MySQLi
-- MySQL 8+ / MariaDB compativel

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `gestor_assiduidade`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `gestor_assiduidade`;

-- --------------------------------------------------------
-- Papeis de utilizador
-- --------------------------------------------------------

CREATE TABLE `papeis` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(80) NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_papeis_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Departamentos
-- --------------------------------------------------------

CREATE TABLE `departamentos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(120) NOT NULL,
  `codigo` VARCHAR(30) DEFAULT NULL,
  `responsavel_id` INT UNSIGNED DEFAULT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_departamentos_codigo` (`codigo`),
  KEY `idx_departamentos_responsavel` (`responsavel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Utilizadores / colaboradores
-- --------------------------------------------------------

CREATE TABLE `utilizadores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `departamento_id` INT UNSIGNED DEFAULT NULL,
  `numero_mecanografico` VARCHAR(50) DEFAULT NULL,
  `nome` VARCHAR(160) NOT NULL,
  `email` VARCHAR(160) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `telefone` VARCHAR(40) DEFAULT NULL,
  `cargo` VARCHAR(120) DEFAULT NULL,
  `data_nascimento` DATE DEFAULT NULL,
  `data_admissao` DATE DEFAULT NULL,
  `tipo_contrato` VARCHAR(80) DEFAULT NULL,
  `foto` VARCHAR(255) DEFAULT NULL,
  `pin_ponto` VARCHAR(20) DEFAULT NULL,
  `codigo_cartao` VARCHAR(80) DEFAULT NULL,
  `codigo_biometrico` VARCHAR(80) DEFAULT NULL,
  `ultimo_login_at` DATETIME DEFAULT NULL,
  `estado` ENUM('ativo','suspenso','inativo') NOT NULL DEFAULT 'ativo',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_utilizadores_email` (`email`),
  UNIQUE KEY `uk_utilizadores_numero_mecanografico` (`numero_mecanografico`),
  UNIQUE KEY `uk_utilizadores_pin_ponto` (`pin_ponto`),
  UNIQUE KEY `uk_utilizadores_codigo_cartao` (`codigo_cartao`),
  UNIQUE KEY `uk_utilizadores_codigo_biometrico` (`codigo_biometrico`),
  KEY `idx_utilizadores_departamento` (`departamento_id`),
  KEY `idx_utilizadores_estado` (`estado`),
  CONSTRAINT `fk_utilizadores_departamento`
    FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `departamentos`
  ADD CONSTRAINT `fk_departamentos_responsavel`
  FOREIGN KEY (`responsavel_id`) REFERENCES `utilizadores` (`id`)
  ON UPDATE CASCADE
  ON DELETE SET NULL;

-- --------------------------------------------------------
-- Relacao utilizadores <-> papeis
-- --------------------------------------------------------

CREATE TABLE `utilizador_papeis` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT UNSIGNED NOT NULL,
  `papel_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_utilizador_papel` (`utilizador_id`, `papel_id`),
  KEY `idx_utilizador_papeis_papel` (`papel_id`),
  CONSTRAINT `fk_utilizador_papeis_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT `fk_utilizador_papeis_papel`
    FOREIGN KEY (`papel_id`) REFERENCES `papeis` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Turnos
-- --------------------------------------------------------

CREATE TABLE `turnos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(120) NOT NULL,
  `codigo` VARCHAR(40) DEFAULT NULL,
  `hora_entrada` TIME NOT NULL,
  `hora_saida` TIME NOT NULL,
  `inicio_pausa` TIME DEFAULT NULL,
  `fim_pausa` TIME DEFAULT NULL,
  `tolerancia_entrada_min` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `tolerancia_saida_min` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `horas_previstas` DECIMAL(5,2) NOT NULL DEFAULT 8.00,
  `turno_noturno` TINYINT(1) NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_turnos_codigo` (`codigo`),
  KEY `idx_turnos_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Horarios atribuidos aos utilizadores
-- --------------------------------------------------------

CREATE TABLE `horarios_turno` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT UNSIGNED NOT NULL,
  `turno_id` INT UNSIGNED NOT NULL,
  `data_inicio` DATE NOT NULL,
  `data_fim` DATE DEFAULT NULL,
  `dia_semana` TINYINT UNSIGNED DEFAULT NULL COMMENT '1=segunda, 7=domingo; NULL=todos os dias no periodo',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_horarios_utilizador` (`utilizador_id`),
  KEY `idx_horarios_turno` (`turno_id`),
  KEY `idx_horarios_periodo` (`data_inicio`, `data_fim`),
  CONSTRAINT `fk_horarios_turno_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT `fk_horarios_turno_turno`
    FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT `chk_horarios_turno_dia_semana`
    CHECK (`dia_semana` IS NULL OR `dia_semana` BETWEEN 1 AND 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dispositivos biometricos / relogios de ponto
-- --------------------------------------------------------

CREATE TABLE `dispositivos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(120) NOT NULL,
  `marca` VARCHAR(80) DEFAULT NULL,
  `modelo` VARCHAR(80) DEFAULT NULL,
  `numero_serie` VARCHAR(120) DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `porta` INT UNSIGNED DEFAULT NULL,
  `localizacao` VARCHAR(160) DEFAULT NULL,
  `tipo` ENUM('biometrico','rfid','facial','manual','outro') NOT NULL DEFAULT 'biometrico',
  `estado` ENUM('ativo','inativo','offline','manutencao') NOT NULL DEFAULT 'ativo',
  `ultima_sincronizacao_at` DATETIME DEFAULT NULL,
  `observacoes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dispositivos_numero_serie` (`numero_serie`),
  KEY `idx_dispositivos_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Registos de ponto
-- --------------------------------------------------------

CREATE TABLE `registos_ponto` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT UNSIGNED NOT NULL,
  `dispositivo_id` INT UNSIGNED DEFAULT NULL,
  `tipo` ENUM('entrada','saida','inicio_pausa','fim_pausa','entrada_segundo_turno','saida_segundo_turno') NOT NULL,
  `data_hora` DATETIME NOT NULL,
  `origem` ENUM('manual','dispositivo','importacao','api') NOT NULL DEFAULT 'manual',
  `estado` ENUM('valido','pendente','corrigido','rejeitado','duplicado','anulado') NOT NULL DEFAULT 'valido',
  `observacoes` VARCHAR(255) DEFAULT NULL,
  `criado_por` INT UNSIGNED DEFAULT NULL,
  `atualizado_por` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_registos_utilizador_data` (`utilizador_id`, `data_hora`),
  KEY `idx_registos_dispositivo` (`dispositivo_id`),
  KEY `idx_registos_tipo` (`tipo`),
  KEY `idx_registos_estado` (`estado`),
  KEY `idx_registos_criado_por` (`criado_por`),
  KEY `idx_registos_atualizado_por` (`atualizado_por`),
  CONSTRAINT `fk_registos_ponto_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT `fk_registos_ponto_dispositivo`
    FOREIGN KEY (`dispositivo_id`) REFERENCES `dispositivos` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT `fk_registos_ponto_criado_por`
    FOREIGN KEY (`criado_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT `fk_registos_ponto_atualizado_por`
    FOREIGN KEY (`atualizado_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tipos de ausencia
-- --------------------------------------------------------

CREATE TABLE `tipos_ausencia` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `remunerada` TINYINT(1) NOT NULL DEFAULT 1,
  `desconta_ferias` TINYINT(1) NOT NULL DEFAULT 0,
  `exige_justificativo` TINYINT(1) NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tipos_ausencia_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Pedidos de ausencia / ferias / faltas
-- --------------------------------------------------------

CREATE TABLE `pedidos_ausencia` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT UNSIGNED NOT NULL,
  `tipo_ausencia_id` INT UNSIGNED NOT NULL,
  `data_inicio` DATE NOT NULL,
  `data_fim` DATE NOT NULL,
  `hora_inicio` TIME DEFAULT NULL,
  `hora_fim` TIME DEFAULT NULL,
  `total_dias` DECIMAL(6,2) DEFAULT NULL,
  `total_horas` DECIMAL(6,2) DEFAULT NULL,
  `motivo` TEXT DEFAULT NULL,
  `ficheiro_justificativo` VARCHAR(255) DEFAULT NULL,
  `estado` ENUM('pendente','aprovado','rejeitado','cancelado') NOT NULL DEFAULT 'pendente',
  `aprovado_por` INT UNSIGNED DEFAULT NULL,
  `aprovado_at` DATETIME DEFAULT NULL,
  `observacoes_aprovacao` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pedidos_utilizador` (`utilizador_id`),
  KEY `idx_pedidos_tipo` (`tipo_ausencia_id`),
  KEY `idx_pedidos_estado` (`estado`),
  KEY `idx_pedidos_periodo` (`data_inicio`, `data_fim`),
  KEY `idx_pedidos_aprovado_por` (`aprovado_por`),
  CONSTRAINT `fk_pedidos_ausencia_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT `fk_pedidos_ausencia_tipo`
    FOREIGN KEY (`tipo_ausencia_id`) REFERENCES `tipos_ausencia` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT `fk_pedidos_ausencia_aprovado_por`
    FOREIGN KEY (`aprovado_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT `chk_pedidos_ausencia_datas`
    CHECK (`data_fim` >= `data_inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Banco de horas
-- --------------------------------------------------------

CREATE TABLE `banco_horas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT UNSIGNED NOT NULL,
  `data_movimento` DATE NOT NULL,
  `tipo_movimento` ENUM('credito','debito','ajuste','compensacao') NOT NULL,
  `minutos` INT NOT NULL,
  `origem` ENUM('ponto','ausencia','manual','importacao') NOT NULL DEFAULT 'manual',
  `registo_ponto_id` BIGINT UNSIGNED DEFAULT NULL,
  `pedido_ausencia_id` INT UNSIGNED DEFAULT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `criado_por` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_banco_horas_utilizador_data` (`utilizador_id`, `data_movimento`),
  KEY `idx_banco_horas_tipo` (`tipo_movimento`),
  KEY `idx_banco_horas_registo` (`registo_ponto_id`),
  KEY `idx_banco_horas_pedido` (`pedido_ausencia_id`),
  KEY `idx_banco_horas_criado_por` (`criado_por`),
  CONSTRAINT `fk_banco_horas_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT `fk_banco_horas_registo_ponto`
    FOREIGN KEY (`registo_ponto_id`) REFERENCES `registos_ponto` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT `fk_banco_horas_pedido_ausencia`
    FOREIGN KEY (`pedido_ausencia_id`) REFERENCES `pedidos_ausencia` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT `fk_banco_horas_criado_por`
    FOREIGN KEY (`criado_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Logs do sistema
-- --------------------------------------------------------

CREATE TABLE `logs_sistema` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT UNSIGNED DEFAULT NULL,
  `acao` VARCHAR(120) NOT NULL,
  `modulo` VARCHAR(80) DEFAULT NULL,
  `tabela` VARCHAR(80) DEFAULT NULL,
  `registo_id` BIGINT UNSIGNED DEFAULT NULL,
  `descricao` TEXT DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `dados_anteriores` JSON DEFAULT NULL,
  `dados_novos` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_utilizador` (`utilizador_id`),
  KEY `idx_logs_acao` (`acao`),
  KEY `idx_logs_modulo` (`modulo`),
  KEY `idx_logs_created_at` (`created_at`),
  CONSTRAINT `fk_logs_sistema_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dados iniciais
-- --------------------------------------------------------

INSERT INTO `papeis` (`nome`, `slug`, `descricao`) VALUES
('Administrador', 'administrador', 'Acesso total ao sistema'),
('Recursos Humanos', 'recursos-humanos', 'Gestão de colaboradores, assiduidade e ausências'),
('Chefia', 'chefia', 'Consulta e aprovação da própria equipa'),
('Colaborador', 'colaborador', 'Acesso ao próprio perfil, ponto e pedidos');

INSERT INTO `tipos_ausencia`
(`nome`, `slug`, `descricao`, `remunerada`, `desconta_ferias`, `exige_justificativo`) VALUES
('Férias', 'ferias', 'Período de férias aprovado', 1, 1, 0),
('Falta Justificada', 'falta-justificada', 'Ausência com justificação aceite', 1, 0, 1),
('Falta Injustificada', 'falta-injustificada', 'Ausência sem justificação aceite', 0, 0, 0),
('Baixa Médica', 'baixa-medica', 'Ausência por motivo de saúde', 1, 0, 1),
('Licença', 'licenca', 'Licença autorizada', 1, 0, 1),
('Formação', 'formacao', 'Ausência por formação', 1, 0, 0);

COMMIT;

-- ========================================================
-- Fonte: database/2026_05_15_lar_idosos_assiduidade.sql
-- ========================================================

-- Adaptacao incremental para lar de idosos
-- Nao apaga dados existentes. Mantem compatibilidade com o schema atual.
-- PHP procedural + MySQLi + prepared statements

USE `gestor_assiduidade`;

SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_column_if_missing $$
CREATE PROCEDURE add_column_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64),
    IN p_column_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND COLUMN_NAME = p_column_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN `', p_column_name, '` ', p_column_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS add_index_if_missing $$
CREATE PROCEDURE add_index_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_index_name VARCHAR(64),
    IN p_index_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND INDEX_NAME = p_index_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD ', p_index_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS add_fk_if_missing $$
CREATE PROCEDURE add_fk_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_constraint_name VARCHAR(64),
    IN p_constraint_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND CONSTRAINT_NAME = p_constraint_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD CONSTRAINT `', p_constraint_name, '` ', p_constraint_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

-- --------------------------------------------------------
-- Setores/funcoes da instituicao
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `setores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(140) NOT NULL,
  `codigo` VARCHAR(40) NOT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `responsavel_id` INT UNSIGNED DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setores_codigo` (`codigo`),
  KEY `idx_setores_responsavel` (`responsavel_id`),
  KEY `idx_setores_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_fk_if_missing(
  'setores',
  'fk_setores_responsavel',
  'FOREIGN KEY (`responsavel_id`) REFERENCES `utilizadores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);

INSERT INTO `setores` (`nome`, `codigo`, `descricao`) VALUES
('Acao Direta', 'ACAO_DIRETA', 'Prestacao direta de cuidados aos utentes'),
('Servicos Gerais', 'SERVICOS_GERAIS', 'Servicos gerais de apoio'),
('Refeitorio/Copa', 'REFEITORIO_COPA', 'Refeitorio, copa e apoio a refeicoes'),
('Cozinha', 'COZINHA', 'Preparacao e confeccao alimentar'),
('Lavandaria', 'LAVANDARIA', 'Tratamento de roupa e lavandaria'),
('Servicos Tecnicos e Administrativos', 'TECNICOS_ADMIN', 'Secretaria, direcao tecnica e servicos administrativos'),
('Motoristas', 'MOTORISTAS', 'Transporte de utentes, pessoal e servicos externos')
ON DUPLICATE KEY UPDATE
  `nome` = VALUES(`nome`),
  `descricao` = VALUES(`descricao`),
  `ativo` = 1;

-- --------------------------------------------------------
-- Equipas
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `equipas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setor_id` INT UNSIGNED NOT NULL,
  `nome` VARCHAR(140) NOT NULL,
  `codigo` VARCHAR(40) NOT NULL,
  `responsavel_id` INT UNSIGNED DEFAULT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_equipas_codigo` (`codigo`),
  KEY `idx_equipas_setor` (`setor_id`),
  KEY `idx_equipas_responsavel` (`responsavel_id`),
  KEY `idx_equipas_ativo` (`ativo`),
  CONSTRAINT `fk_equipas_setor`
    FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_fk_if_missing(
  'equipas',
  'fk_equipas_responsavel',
  'FOREIGN KEY (`responsavel_id`) REFERENCES `utilizadores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);

INSERT INTO `equipas` (`setor_id`, `nome`, `codigo`, `descricao`)
SELECT s.id, CONCAT(s.nome, ' - Equipa Geral'), CONCAT(s.codigo, '_GERAL'), 'Equipa geral do setor'
FROM `setores` s
ON DUPLICATE KEY UPDATE
  `nome` = VALUES(`nome`),
  `descricao` = VALUES(`descricao`),
  `ativo` = 1;

-- Mantem o modulo Departamentos funcional e permite mapear departamentos para setores.
CALL add_column_if_missing('departamentos', 'setor_id', 'INT UNSIGNED DEFAULT NULL AFTER `id`');
CALL add_column_if_missing('departamentos', 'equipa_id', 'INT UNSIGNED DEFAULT NULL AFTER `setor_id`');
CALL add_index_if_missing('departamentos', 'idx_departamentos_setor', 'INDEX `idx_departamentos_setor` (`setor_id`)');
CALL add_index_if_missing('departamentos', 'idx_departamentos_equipa', 'INDEX `idx_departamentos_equipa` (`equipa_id`)');
CALL add_fk_if_missing(
  'departamentos',
  'fk_departamentos_setor',
  'FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);
CALL add_fk_if_missing(
  'departamentos',
  'fk_departamentos_equipa',
  'FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);

-- --------------------------------------------------------
-- Funcionarios
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `funcionarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT UNSIGNED DEFAULT NULL,
  `setor_id` INT UNSIGNED DEFAULT NULL,
  `equipa_id` INT UNSIGNED DEFAULT NULL,
  `numero_mecanografico` VARCHAR(50) DEFAULT NULL,
  `nome` VARCHAR(160) NOT NULL,
  `email` VARCHAR(160) DEFAULT NULL,
  `telefone` VARCHAR(40) DEFAULT NULL,
  `funcao` VARCHAR(120) DEFAULT NULL,
  `categoria_profissional` VARCHAR(120) DEFAULT NULL,
  `data_admissao` DATE DEFAULT NULL,
  `data_cessacao` DATE DEFAULT NULL,
  `tipo_contrato` VARCHAR(80) DEFAULT NULL,
  `carga_horaria_semanal` DECIMAL(5,2) NOT NULL DEFAULT 40.00,
  `pin_ponto` VARCHAR(20) DEFAULT NULL,
  `codigo_cartao` VARCHAR(80) DEFAULT NULL,
  `codigo_biometrico` VARCHAR(80) DEFAULT NULL,
  `estado` ENUM('ativo','suspenso','inativo') NOT NULL DEFAULT 'ativo',
  `observacoes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_funcionarios_utilizador` (`utilizador_id`),
  UNIQUE KEY `uk_funcionarios_numero_mecanografico` (`numero_mecanografico`),
  UNIQUE KEY `uk_funcionarios_pin_ponto` (`pin_ponto`),
  UNIQUE KEY `uk_funcionarios_codigo_cartao` (`codigo_cartao`),
  UNIQUE KEY `uk_funcionarios_codigo_biometrico` (`codigo_biometrico`),
  KEY `idx_funcionarios_setor` (`setor_id`),
  KEY `idx_funcionarios_equipa` (`equipa_id`),
  KEY `idx_funcionarios_estado` (`estado`),
  CONSTRAINT `fk_funcionarios_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_funcionarios_setor`
    FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_funcionarios_equipa`
    FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `funcionarios`
(`utilizador_id`, `numero_mecanografico`, `nome`, `email`, `telefone`, `funcao`, `data_admissao`,
 `tipo_contrato`, `pin_ponto`, `codigo_cartao`, `codigo_biometrico`, `estado`)
SELECT u.id, u.numero_mecanografico, u.nome, u.email, u.telefone, u.cargo, u.data_admissao,
       u.tipo_contrato, u.pin_ponto, u.codigo_cartao, u.codigo_biometrico, u.estado
FROM `utilizadores` u
WHERE NOT EXISTS (
    SELECT 1 FROM `funcionarios` f WHERE f.utilizador_id = u.id
);

CALL add_column_if_missing('utilizadores', 'setor_id', 'INT UNSIGNED DEFAULT NULL AFTER `departamento_id`');
CALL add_column_if_missing('utilizadores', 'equipa_id', 'INT UNSIGNED DEFAULT NULL AFTER `setor_id`');
CALL add_column_if_missing('utilizadores', 'funcionario_id', 'INT UNSIGNED DEFAULT NULL AFTER `equipa_id`');
CALL add_index_if_missing('utilizadores', 'idx_utilizadores_setor', 'INDEX `idx_utilizadores_setor` (`setor_id`)');
CALL add_index_if_missing('utilizadores', 'idx_utilizadores_equipa', 'INDEX `idx_utilizadores_equipa` (`equipa_id`)');
CALL add_index_if_missing('utilizadores', 'idx_utilizadores_funcionario', 'INDEX `idx_utilizadores_funcionario` (`funcionario_id`)');
CALL add_fk_if_missing(
  'utilizadores',
  'fk_utilizadores_setor',
  'FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);
CALL add_fk_if_missing(
  'utilizadores',
  'fk_utilizadores_equipa',
  'FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);
CALL add_fk_if_missing(
  'utilizadores',
  'fk_utilizadores_funcionario',
  'FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);

UPDATE `utilizadores` u
INNER JOIN `funcionarios` f ON f.utilizador_id = u.id
SET u.funcionario_id = f.id
WHERE u.funcionario_id IS NULL;

-- Horarios/turnos passam a estar ligados a funcionarios. Mantem utilizador_id
-- apenas para compatibilidade historica com dados antigos.
ALTER TABLE `horarios_turno`
  MODIFY `utilizador_id` INT UNSIGNED DEFAULT NULL;
CALL add_column_if_missing('horarios_turno', 'funcionario_id', 'INT UNSIGNED DEFAULT NULL AFTER `utilizador_id`');
CALL add_index_if_missing('horarios_turno', 'idx_horarios_funcionario', 'INDEX `idx_horarios_funcionario` (`funcionario_id`)');
CALL add_fk_if_missing(
  'horarios_turno',
  'fk_horarios_turno_funcionario',
  'FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE'
);

UPDATE `horarios_turno` ht
INNER JOIN `funcionarios` f ON f.utilizador_id = ht.utilizador_id
SET ht.funcionario_id = f.id
WHERE ht.funcionario_id IS NULL;

-- --------------------------------------------------------
-- Turnos com um ou mais periodos
-- --------------------------------------------------------

CALL add_column_if_missing('turnos', 'descricao', 'VARCHAR(255) DEFAULT NULL AFTER `codigo`');
CALL add_column_if_missing('turnos', 'total_periodos', 'TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `horas_previstas`');
CALL add_column_if_missing('turnos', 'permite_multiplos_periodos', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `total_periodos`');
CALL add_column_if_missing('turnos', 'tolerancia_antes_min', 'SMALLINT UNSIGNED NOT NULL DEFAULT 15 AFTER `tolerancia_saida_min`');
CALL add_column_if_missing('turnos', 'tolerancia_depois_min', 'SMALLINT UNSIGNED NOT NULL DEFAULT 15 AFTER `tolerancia_antes_min`');

UPDATE `turnos`
SET `tolerancia_entrada_min` = 15,
    `tolerancia_saida_min` = 15,
    `tolerancia_antes_min` = 15,
    `tolerancia_depois_min` = 15
WHERE (`tolerancia_entrada_min` = 0 OR `tolerancia_saida_min` = 0);

CREATE TABLE IF NOT EXISTS `turno_periodos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `turno_id` INT UNSIGNED NOT NULL,
  `sequencia` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `hora_inicio` TIME NOT NULL,
  `hora_fim` TIME NOT NULL,
  `cruza_dia` TINYINT(1) NOT NULL DEFAULT 0,
  `tolerancia_antes_min` SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  `tolerancia_depois_min` SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  `minutos_previstos` SMALLINT UNSIGNED NOT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_turno_periodos_seq` (`turno_id`, `sequencia`),
  KEY `idx_turno_periodos_turno` (`turno_id`),
  CONSTRAINT `fk_turno_periodos_turno`
    FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `chk_turno_periodos_seq`
    CHECK (`sequencia` BETWEEN 1 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migra turnos atuais de periodo unico para a nova tabela de periodos.
INSERT INTO `turno_periodos`
(`turno_id`, `sequencia`, `hora_inicio`, `hora_fim`, `cruza_dia`, `tolerancia_antes_min`, `tolerancia_depois_min`, `minutos_previstos`)
SELECT t.id, 1, t.hora_entrada, t.hora_saida, IF(t.hora_saida <= t.hora_entrada, 1, 0),
       15, 15,
       TIMESTAMPDIFF(
         MINUTE,
         TIMESTAMP('2000-01-01', t.hora_entrada),
         TIMESTAMP(IF(t.hora_saida <= t.hora_entrada, '2000-01-02', '2000-01-01'), t.hora_saida)
       )
FROM `turnos` t
WHERE NOT EXISTS (
  SELECT 1 FROM `turno_periodos` tp WHERE tp.turno_id = t.id
);

INSERT INTO `turnos`
(`nome`, `codigo`, `descricao`, `hora_entrada`, `hora_saida`, `tolerancia_entrada_min`, `tolerancia_saida_min`,
 `tolerancia_antes_min`, `tolerancia_depois_min`, `horas_previstas`, `turno_noturno`, `total_periodos`, `permite_multiplos_periodos`, `ativo`)
VALUES
('00:00-08:00', 'T_0000_0800', 'Turno noturno/madrugada', '00:00:00', '08:00:00', 15, 15, 15, 15, 8.00, 1, 1, 0, 1),
('08:00-16:00', 'T_0800_1600', 'Turno da manha/tarde', '08:00:00', '16:00:00', 15, 15, 15, 15, 8.00, 0, 1, 0, 1),
('16:00-00:00', 'T_1600_0000', 'Turno da tarde/noite', '16:00:00', '00:00:00', 15, 15, 15, 15, 8.00, 1, 1, 0, 1),
('08:30-16:30', 'T_0830_1630', 'Turno administrativo', '08:30:00', '16:30:00', 15, 15, 15, 15, 8.00, 0, 1, 0, 1),
('08:00-13:00 e 17:00-20:00', 'T_0800_1300_1700_2000', 'Turno repartido', '08:00:00', '20:00:00', 15, 15, 15, 15, 8.00, 0, 2, 1, 1),
('08:00-14:00', 'T_0800_1400', 'Turno parcial de 6 horas', '08:00:00', '14:00:00', 15, 15, 15, 15, 6.00, 0, 1, 0, 1),
('08:00-12:00 e 18:00-20:00', 'T_0800_1200_1800_2000', 'Turno repartido parcial', '08:00:00', '20:00:00', 15, 15, 15, 15, 6.00, 0, 2, 1, 1),
('12:00-20:00', 'T_1200_2000', 'Turno tarde', '12:00:00', '20:00:00', 15, 15, 15, 15, 8.00, 0, 1, 0, 1),
('09:00-12:30 e 14:00-17:30', 'T_0900_1230_1400_1730', 'Turno administrativo repartido', '09:00:00', '17:30:00', 15, 15, 15, 15, 7.00, 0, 2, 1, 1)
ON DUPLICATE KEY UPDATE
  `nome` = VALUES(`nome`),
  `descricao` = VALUES(`descricao`),
  `hora_entrada` = VALUES(`hora_entrada`),
  `hora_saida` = VALUES(`hora_saida`),
  `tolerancia_entrada_min` = 15,
  `tolerancia_saida_min` = 15,
  `tolerancia_antes_min` = 15,
  `tolerancia_depois_min` = 15,
  `horas_previstas` = VALUES(`horas_previstas`),
  `turno_noturno` = VALUES(`turno_noturno`),
  `total_periodos` = VALUES(`total_periodos`),
  `permite_multiplos_periodos` = VALUES(`permite_multiplos_periodos`),
  `ativo` = 1;

INSERT INTO `turno_periodos` (`turno_id`, `sequencia`, `hora_inicio`, `hora_fim`, `cruza_dia`, `minutos_previstos`)
SELECT t.id, p.sequencia, p.hora_inicio, p.hora_fim, p.cruza_dia, p.minutos_previstos
FROM `turnos` t
INNER JOIN (
    SELECT 'T_0000_0800' codigo, 1 sequencia, '00:00:00' hora_inicio, '08:00:00' hora_fim, 0 cruza_dia, 480 minutos_previstos
    UNION ALL SELECT 'T_0800_1600', 1, '08:00:00', '16:00:00', 0, 480
    UNION ALL SELECT 'T_1600_0000', 1, '16:00:00', '00:00:00', 1, 480
    UNION ALL SELECT 'T_0830_1630', 1, '08:30:00', '16:30:00', 0, 480
    UNION ALL SELECT 'T_0800_1300_1700_2000', 1, '08:00:00', '13:00:00', 0, 300
    UNION ALL SELECT 'T_0800_1300_1700_2000', 2, '17:00:00', '20:00:00', 0, 180
    UNION ALL SELECT 'T_0800_1400', 1, '08:00:00', '14:00:00', 0, 360
    UNION ALL SELECT 'T_0800_1200_1800_2000', 1, '08:00:00', '12:00:00', 0, 240
    UNION ALL SELECT 'T_0800_1200_1800_2000', 2, '18:00:00', '20:00:00', 0, 120
    UNION ALL SELECT 'T_1200_2000', 1, '12:00:00', '20:00:00', 0, 480
    UNION ALL SELECT 'T_0900_1230_1400_1730', 1, '09:00:00', '12:30:00', 0, 210
    UNION ALL SELECT 'T_0900_1230_1400_1730', 2, '14:00:00', '17:30:00', 0, 210
) p ON p.codigo = t.codigo
ON DUPLICATE KEY UPDATE
  `hora_inicio` = VALUES(`hora_inicio`),
  `hora_fim` = VALUES(`hora_fim`),
  `cruza_dia` = VALUES(`cruza_dia`),
  `tolerancia_antes_min` = 15,
  `tolerancia_depois_min` = 15,
  `minutos_previstos` = VALUES(`minutos_previstos`),
  `ativo` = 1;

-- --------------------------------------------------------
-- Escala mensal
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `escala_mensal` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ano` SMALLINT UNSIGNED NOT NULL,
  `mes` TINYINT UNSIGNED NOT NULL,
  `setor_id` INT UNSIGNED DEFAULT NULL,
  `equipa_id` INT UNSIGNED DEFAULT NULL,
  `nome` VARCHAR(160) DEFAULT NULL,
  `estado` ENUM('rascunho','publicada','fechada','cancelada') NOT NULL DEFAULT 'rascunho',
  `publicada_at` DATETIME DEFAULT NULL,
  `publicada_por` INT UNSIGNED DEFAULT NULL,
  `fechada_at` DATETIME DEFAULT NULL,
  `fechada_por` INT UNSIGNED DEFAULT NULL,
  `observacoes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_escala_mensal_contexto` (`ano`, `mes`, `setor_id`, `equipa_id`),
  KEY `idx_escala_mensal_setor` (`setor_id`),
  KEY `idx_escala_mensal_equipa` (`equipa_id`),
  KEY `idx_escala_mensal_estado` (`estado`),
  CONSTRAINT `fk_escala_mensal_setor`
    FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_mensal_equipa`
    FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_mensal_publicada_por`
    FOREIGN KEY (`publicada_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_mensal_fechada_por`
    FOREIGN KEY (`fechada_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_mensal_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_escala_mensal_mes`
    CHECK (`mes` BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `escala_mensal_dias` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `escala_mensal_id` BIGINT UNSIGNED NOT NULL,
  `funcionario_id` INT UNSIGNED DEFAULT NULL,
  `utilizador_id` INT UNSIGNED DEFAULT NULL,
  `setor_id` INT UNSIGNED DEFAULT NULL,
  `equipa_id` INT UNSIGNED DEFAULT NULL,
  `data_escala` DATE NOT NULL,
  `turno_id` INT UNSIGNED DEFAULT NULL,
  `tipo_dia` ENUM('trabalho','folga','feriado','ferias','ausencia','descanso','formacao') NOT NULL DEFAULT 'trabalho',
  `minutos_previstos` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `observacoes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_escala_dia_funcionario` (`funcionario_id`, `data_escala`),
  KEY `idx_escala_dias_escala` (`escala_mensal_id`),
  KEY `idx_escala_dias_utilizador` (`utilizador_id`, `data_escala`),
  KEY `idx_escala_dias_setor` (`setor_id`, `data_escala`),
  KEY `idx_escala_dias_equipa` (`equipa_id`, `data_escala`),
  KEY `idx_escala_dias_turno` (`turno_id`),
  CONSTRAINT `fk_escala_dias_escala`
    FOREIGN KEY (`escala_mensal_id`) REFERENCES `escala_mensal` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_escala_dias_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_dias_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_dias_setor`
    FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_dias_equipa`
    FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_dias_turno`
    FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `escala_funcionarios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `funcionario_id` INT UNSIGNED NOT NULL,
  `utilizador_id` INT UNSIGNED DEFAULT NULL,
  `setor_id` INT UNSIGNED DEFAULT NULL,
  `equipa_id` INT UNSIGNED DEFAULT NULL,
  `ano` SMALLINT UNSIGNED NOT NULL,
  `mes` TINYINT UNSIGNED NOT NULL,
  `data_escala` DATE NOT NULL,
  `dia` TINYINT UNSIGNED NOT NULL,
  `tipo_dia` ENUM('turno','folga','ferias','falta','baixa','substituicao','licenca_amamentacao') NOT NULL DEFAULT 'turno',
  `turno_id` INT UNSIGNED DEFAULT NULL,
  `substitui_funcionario_id` INT UNSIGNED DEFAULT NULL,
  `folga_trabalhada` TINYINT(1) NOT NULL DEFAULT 0,
  `observacoes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_escala_funcionario_dia` (`funcionario_id`, `data_escala`),
  KEY `idx_escala_funcionarios_periodo` (`ano`, `mes`),
  KEY `idx_escala_funcionarios_setor` (`setor_id`, `data_escala`),
  KEY `idx_escala_funcionarios_equipa` (`equipa_id`, `data_escala`),
  KEY `idx_escala_funcionarios_turno` (`turno_id`),
  KEY `idx_escala_funcionarios_substitui` (`substitui_funcionario_id`),
  CONSTRAINT `fk_escala_funcionarios_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_escala_funcionarios_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_funcionarios_setor`
    FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_funcionarios_equipa`
    FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_funcionarios_turno`
    FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_funcionarios_substitui`
    FOREIGN KEY (`substitui_funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_escala_funcionarios_mes`
    CHECK (`mes` BETWEEN 1 AND 12),
  CONSTRAINT `chk_escala_funcionarios_dia`
    CHECK (`dia` BETWEEN 1 AND 31)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Ferias/ausencias operacionais
-- Mantem pedidos_ausencia como pedido/aprovacao e cria uma tabela propria
-- para calendario, impacto na escala e relatorios.
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ferias_ausencias` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pedido_ausencia_id` INT UNSIGNED DEFAULT NULL,
  `funcionario_id` INT UNSIGNED DEFAULT NULL,
  `utilizador_id` INT UNSIGNED DEFAULT NULL,
  `tipo_ausencia_id` INT UNSIGNED NOT NULL,
  `data_inicio` DATE NOT NULL,
  `data_fim` DATE NOT NULL,
  `hora_inicio` TIME DEFAULT NULL,
  `hora_fim` TIME DEFAULT NULL,
  `dia_completo` TINYINT(1) NOT NULL DEFAULT 1,
  `minutos_justificados` INT UNSIGNED DEFAULT NULL,
  `afeta_assiduidade` TINYINT(1) NOT NULL DEFAULT 1,
  `desconta_banco_horas` TINYINT(1) NOT NULL DEFAULT 0,
  `estado` ENUM('pendente','aprovado','rejeitado','cancelado') NOT NULL DEFAULT 'pendente',
  `motivo` TEXT DEFAULT NULL,
  `ficheiro_justificativo` VARCHAR(255) DEFAULT NULL,
  `aprovado_por` INT UNSIGNED DEFAULT NULL,
  `aprovado_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ferias_funcionario_periodo` (`funcionario_id`, `data_inicio`, `data_fim`),
  KEY `idx_ferias_utilizador_periodo` (`utilizador_id`, `data_inicio`, `data_fim`),
  KEY `idx_ferias_tipo` (`tipo_ausencia_id`),
  KEY `idx_ferias_estado` (`estado`),
  KEY `idx_ferias_pedido` (`pedido_ausencia_id`),
  CONSTRAINT `fk_ferias_pedido`
    FOREIGN KEY (`pedido_ausencia_id`) REFERENCES `pedidos_ausencia` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_ferias_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_ferias_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_ferias_tipo`
    FOREIGN KEY (`tipo_ausencia_id`) REFERENCES `tipos_ausencia` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_ferias_aprovado_por`
    FOREIGN KEY (`aprovado_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_ferias_datas`
    CHECK (`data_fim` >= `data_inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ferias_ausencias`
(`pedido_ausencia_id`, `funcionario_id`, `utilizador_id`, `tipo_ausencia_id`, `data_inicio`, `data_fim`,
 `hora_inicio`, `hora_fim`, `dia_completo`, `minutos_justificados`, `estado`, `motivo`,
 `ficheiro_justificativo`, `aprovado_por`, `aprovado_at`, `created_at`, `updated_at`)
SELECT p.id, f.id, p.utilizador_id, p.tipo_ausencia_id, p.data_inicio, p.data_fim,
       p.hora_inicio, p.hora_fim,
       IF(p.hora_inicio IS NULL AND p.hora_fim IS NULL, 1, 0),
       CASE WHEN p.total_horas IS NULL THEN NULL ELSE ROUND(p.total_horas * 60) END,
       p.estado, p.motivo, p.ficheiro_justificativo, p.aprovado_por, p.aprovado_at,
       p.created_at, p.updated_at
FROM `pedidos_ausencia` p
LEFT JOIN `funcionarios` f ON f.utilizador_id = p.utilizador_id
WHERE NOT EXISTS (
    SELECT 1 FROM `ferias_ausencias` fa WHERE fa.pedido_ausencia_id = p.id
);

-- --------------------------------------------------------
-- Registos de ponto enriquecidos para escala e periodos
-- --------------------------------------------------------

ALTER TABLE `registos_ponto`
  MODIFY `utilizador_id` INT UNSIGNED DEFAULT NULL;
CALL add_column_if_missing('registos_ponto', 'funcionario_id', 'INT UNSIGNED DEFAULT NULL AFTER `utilizador_id`');
CALL add_column_if_missing('registos_ponto', 'escala_mensal_dia_id', 'BIGINT UNSIGNED DEFAULT NULL AFTER `dispositivo_id`');
CALL add_column_if_missing('registos_ponto', 'turno_periodo_id', 'INT UNSIGNED DEFAULT NULL AFTER `escala_mensal_dia_id`');
CALL add_column_if_missing('registos_ponto', 'data_referencia', 'DATE DEFAULT NULL AFTER `data_hora`');
CALL add_column_if_missing('registos_ponto', 'data_hora_prevista', 'DATETIME DEFAULT NULL AFTER `data_referencia`');
CALL add_column_if_missing('registos_ponto', 'dentro_tolerancia', 'TINYINT(1) DEFAULT NULL AFTER `data_hora_prevista`');
CALL add_column_if_missing('registos_ponto', 'minutos_desvio', 'INT DEFAULT NULL AFTER `dentro_tolerancia`');
CALL add_column_if_missing('registos_ponto', 'motivo_correcao', 'VARCHAR(255) DEFAULT NULL AFTER `observacoes`');
CALL add_index_if_missing('registos_ponto', 'idx_registos_funcionario_data', 'INDEX `idx_registos_funcionario_data` (`funcionario_id`, `data_hora`)');
CALL add_index_if_missing('registos_ponto', 'idx_registos_data_referencia', 'INDEX `idx_registos_data_referencia` (`data_referencia`)');
CALL add_index_if_missing('registos_ponto', 'idx_registos_escala_dia', 'INDEX `idx_registos_escala_dia` (`escala_mensal_dia_id`)');
CALL add_index_if_missing('registos_ponto', 'idx_registos_turno_periodo', 'INDEX `idx_registos_turno_periodo` (`turno_periodo_id`)');
CALL add_fk_if_missing(
  'registos_ponto',
  'fk_registos_ponto_funcionario',
  'FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);
CALL add_fk_if_missing(
  'registos_ponto',
  'fk_registos_ponto_escala_dia',
  'FOREIGN KEY (`escala_mensal_dia_id`) REFERENCES `escala_mensal_dias` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);
CALL add_fk_if_missing(
  'registos_ponto',
  'fk_registos_ponto_turno_periodo',
  'FOREIGN KEY (`turno_periodo_id`) REFERENCES `turno_periodos` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);

UPDATE `registos_ponto` rp
INNER JOIN `funcionarios` f ON f.utilizador_id = rp.utilizador_id
SET rp.funcionario_id = f.id,
    rp.data_referencia = DATE(rp.data_hora)
WHERE rp.funcionario_id IS NULL OR rp.data_referencia IS NULL;

-- --------------------------------------------------------
-- Resumo diario de assiduidade
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `resumo_diario_assiduidade` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `funcionario_id` INT UNSIGNED DEFAULT NULL,
  `utilizador_id` INT UNSIGNED DEFAULT NULL,
  `setor_id` INT UNSIGNED DEFAULT NULL,
  `equipa_id` INT UNSIGNED DEFAULT NULL,
  `data` DATE NOT NULL,
  `escala_mensal_dia_id` BIGINT UNSIGNED DEFAULT NULL,
  `turno_id` INT UNSIGNED DEFAULT NULL,
  `minutos_previstos` INT NOT NULL DEFAULT 0,
  `minutos_trabalhados` INT NOT NULL DEFAULT 0,
  `horas_previstas` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `horas_realizadas` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `minutos_ausencia_justificada` INT NOT NULL DEFAULT 0,
  `minutos_atraso` INT NOT NULL DEFAULT 0,
  `minutos_saida_antecipada` INT NOT NULL DEFAULT 0,
  `minutos_extra` INT NOT NULL DEFAULT 0,
  `minutos_saldo` INT NOT NULL DEFAULT 0,
  `dentro_tolerancia` TINYINT(1) NOT NULL DEFAULT 1,
  `estado` ENUM('previsto','presente','ausente','ferias','folga','feriado','incompleto','corrigido','sem_escala') NOT NULL DEFAULT 'previsto',
  `entrada_prevista` DATETIME DEFAULT NULL,
  `saida_prevista` DATETIME DEFAULT NULL,
  `entrada_real` DATETIME DEFAULT NULL,
  `saida_real` DATETIME DEFAULT NULL,
  `falta` TINYINT(1) NOT NULL DEFAULT 0,
  `folga_trabalhada` TINYINT(1) NOT NULL DEFAULT 0,
  `substituicao` TINYINT(1) NOT NULL DEFAULT 0,
  `substitui_funcionario_id` INT UNSIGNED DEFAULT NULL,
  `licenca_amamentacao` TINYINT(1) NOT NULL DEFAULT 0,
  `primeira_entrada` DATETIME DEFAULT NULL,
  `ultima_saida` DATETIME DEFAULT NULL,
  `observacoes` VARCHAR(255) DEFAULT NULL,
  `calculado_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_resumo_diario_funcionario` (`funcionario_id`, `data`),
  KEY `idx_resumo_diario_utilizador` (`utilizador_id`, `data`),
  KEY `idx_resumo_diario_setor` (`setor_id`, `data`),
  KEY `idx_resumo_diario_equipa` (`equipa_id`, `data`),
  KEY `idx_resumo_diario_estado` (`estado`),
  KEY `idx_resumo_substitui_funcionario` (`substitui_funcionario_id`),
  CONSTRAINT `fk_resumo_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_resumo_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_resumo_setor`
    FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_resumo_equipa`
    FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_resumo_escala_dia`
    FOREIGN KEY (`escala_mensal_dia_id`) REFERENCES `escala_mensal_dias` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_resumo_turno`
    FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_resumo_substitui_funcionario`
    FOREIGN KEY (`substitui_funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_column_if_missing('resumo_diario_assiduidade', 'entrada_prevista', 'DATETIME DEFAULT NULL AFTER `estado`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'horas_previstas', 'DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER `minutos_trabalhados`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'horas_realizadas', 'DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER `horas_previstas`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'saida_prevista', 'DATETIME DEFAULT NULL AFTER `entrada_prevista`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'entrada_real', 'DATETIME DEFAULT NULL AFTER `saida_prevista`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'saida_real', 'DATETIME DEFAULT NULL AFTER `entrada_real`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'falta', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `saida_real`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'folga_trabalhada', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `falta`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'substituicao', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `folga_trabalhada`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'substitui_funcionario_id', 'INT UNSIGNED DEFAULT NULL AFTER `substituicao`');
CALL add_column_if_missing('resumo_diario_assiduidade', 'licenca_amamentacao', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `substitui_funcionario_id`');
CALL add_index_if_missing('resumo_diario_assiduidade', 'idx_resumo_substitui_funcionario', 'INDEX `idx_resumo_substitui_funcionario` (`substitui_funcionario_id`)');
CALL add_fk_if_missing(
  'resumo_diario_assiduidade',
  'fk_resumo_substitui_funcionario',
  'FOREIGN KEY (`substitui_funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);

-- --------------------------------------------------------
-- Relatorios mensais por funcionario, equipa e setor
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `relatorios_mensais` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ano` SMALLINT UNSIGNED NOT NULL,
  `mes` TINYINT UNSIGNED NOT NULL,
  `tipo` ENUM('funcionario','equipa','setor') NOT NULL,
  `funcionario_id` INT UNSIGNED DEFAULT NULL,
  `equipa_id` INT UNSIGNED DEFAULT NULL,
  `setor_id` INT UNSIGNED DEFAULT NULL,
  `estado` ENUM('rascunho','calculado','validado','fechado') NOT NULL DEFAULT 'rascunho',
  `dias_previstos` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `dias_trabalhados` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `dias_ferias` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `dias_ausencia` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `minutos_previstos` INT NOT NULL DEFAULT 0,
  `minutos_trabalhados` INT NOT NULL DEFAULT 0,
  `minutos_ausencia_justificada` INT NOT NULL DEFAULT 0,
  `minutos_atraso` INT NOT NULL DEFAULT 0,
  `minutos_saida_antecipada` INT NOT NULL DEFAULT 0,
  `minutos_extra` INT NOT NULL DEFAULT 0,
  `minutos_saldo` INT NOT NULL DEFAULT 0,
  `gerado_por` INT UNSIGNED DEFAULT NULL,
  `gerado_at` DATETIME DEFAULT NULL,
  `validado_por` INT UNSIGNED DEFAULT NULL,
  `validado_at` DATETIME DEFAULT NULL,
  `observacoes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_relatorio_mensal_contexto` (`ano`, `mes`, `tipo`, `funcionario_id`, `equipa_id`, `setor_id`),
  KEY `idx_relatorios_funcionario` (`funcionario_id`),
  KEY `idx_relatorios_equipa` (`equipa_id`),
  KEY `idx_relatorios_setor` (`setor_id`),
  KEY `idx_relatorios_estado` (`estado`),
  CONSTRAINT `fk_relatorios_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_relatorios_equipa`
    FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_relatorios_setor`
    FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_relatorios_gerado_por`
    FOREIGN KEY (`gerado_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_relatorios_validado_por`
    FOREIGN KEY (`validado_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_relatorios_mes`
    CHECK (`mes` BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `relatorio_mensal_linhas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `relatorio_mensal_id` BIGINT UNSIGNED NOT NULL,
  `resumo_diario_id` BIGINT UNSIGNED DEFAULT NULL,
  `funcionario_id` INT UNSIGNED DEFAULT NULL,
  `data` DATE NOT NULL,
  `estado` VARCHAR(40) NOT NULL,
  `minutos_previstos` INT NOT NULL DEFAULT 0,
  `minutos_trabalhados` INT NOT NULL DEFAULT 0,
  `minutos_saldo` INT NOT NULL DEFAULT 0,
  `observacoes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_relatorio_linha_dia` (`relatorio_mensal_id`, `funcionario_id`, `data`),
  KEY `idx_relatorio_linhas_resumo` (`resumo_diario_id`),
  KEY `idx_relatorio_linhas_funcionario` (`funcionario_id`, `data`),
  CONSTRAINT `fk_relatorio_linhas_relatorio`
    FOREIGN KEY (`relatorio_mensal_id`) REFERENCES `relatorios_mensais` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_relatorio_linhas_resumo`
    FOREIGN KEY (`resumo_diario_id`) REFERENCES `resumo_diario_assiduidade` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_relatorio_linhas_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Ajustes ao banco de horas para ligar ao resumo diario
-- --------------------------------------------------------

ALTER TABLE `banco_horas`
  MODIFY `utilizador_id` INT UNSIGNED DEFAULT NULL;
CALL add_column_if_missing('banco_horas', 'funcionario_id', 'INT UNSIGNED DEFAULT NULL AFTER `utilizador_id`');
CALL add_column_if_missing('banco_horas', 'resumo_diario_id', 'BIGINT UNSIGNED DEFAULT NULL AFTER `pedido_ausencia_id`');
CALL add_index_if_missing('banco_horas', 'idx_banco_horas_funcionario_data', 'INDEX `idx_banco_horas_funcionario_data` (`funcionario_id`, `data_movimento`)');
CALL add_index_if_missing('banco_horas', 'idx_banco_horas_resumo', 'INDEX `idx_banco_horas_resumo` (`resumo_diario_id`)');
CALL add_fk_if_missing(
  'banco_horas',
  'fk_banco_horas_funcionario',
  'FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);
CALL add_fk_if_missing(
  'banco_horas',
  'fk_banco_horas_resumo',
  'FOREIGN KEY (`resumo_diario_id`) REFERENCES `resumo_diario_assiduidade` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);

UPDATE `banco_horas` bh
INNER JOIN `funcionarios` f ON f.utilizador_id = bh.utilizador_id
SET bh.funcionario_id = f.id
WHERE bh.funcionario_id IS NULL;

-- --------------------------------------------------------
-- Views de consulta para PHP/MySQLi
-- --------------------------------------------------------

CREATE OR REPLACE VIEW `vw_funcionarios_contexto` AS
SELECT
  f.id AS funcionario_id,
  f.utilizador_id,
  COALESCE(f.numero_mecanografico, u.numero_mecanografico) AS numero_mecanografico,
  COALESCE(f.nome, u.nome) AS nome,
  COALESCE(f.email, u.email) AS email,
  f.funcao,
  f.estado,
  f.setor_id,
  s.nome AS setor_nome,
  f.equipa_id,
  e.nome AS equipa_nome
FROM `funcionarios` f
LEFT JOIN `utilizadores` u ON u.id = f.utilizador_id
LEFT JOIN `setores` s ON s.id = f.setor_id
LEFT JOIN `equipas` e ON e.id = f.equipa_id;

CREATE OR REPLACE VIEW `vw_turnos_periodos` AS
SELECT
  t.id AS turno_id,
  t.nome AS turno_nome,
  t.codigo AS turno_codigo,
  t.horas_previstas,
  t.total_periodos,
  t.permite_multiplos_periodos,
  tp.id AS periodo_id,
  tp.sequencia,
  tp.hora_inicio,
  tp.hora_fim,
  tp.cruza_dia,
  tp.tolerancia_antes_min,
  tp.tolerancia_depois_min,
  tp.minutos_previstos
FROM `turnos` t
INNER JOIN `turno_periodos` tp ON tp.turno_id = t.id
WHERE t.ativo = 1
  AND tp.ativo = 1;

CREATE OR REPLACE VIEW `vw_relatorio_mensal_assiduidade` AS
SELECT
  rda.data,
  YEAR(rda.data) AS ano,
  MONTH(rda.data) AS mes,
  rda.funcionario_id,
  f.nome AS funcionario_nome,
  rda.setor_id,
  s.nome AS setor_nome,
  rda.equipa_id,
  e.nome AS equipa_nome,
  rda.estado,
  rda.minutos_previstos,
  rda.minutos_trabalhados,
  rda.minutos_ausencia_justificada,
  rda.minutos_atraso,
  rda.minutos_saida_antecipada,
  rda.minutos_extra,
  rda.minutos_saldo
FROM `resumo_diario_assiduidade` rda
LEFT JOIN `funcionarios` f ON f.id = rda.funcionario_id
LEFT JOIN `setores` s ON s.id = rda.setor_id
LEFT JOIN `equipas` e ON e.id = rda.equipa_id;

DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS add_index_if_missing;
DROP PROCEDURE IF EXISTS add_fk_if_missing;

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;

-- ========================================================
-- Fonte: database/2026_05_20_equipas_sem_setores.sql
-- ========================================================

-- Simplifica a estrutura operacional: funcionarios pertencem a equipas.
-- O antigo setor fica apenas como coluna legada opcional para bases ja migradas.

SET @fk_equipas_setor := (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'equipas'
    AND COLUMN_NAME = 'setor_id'
    AND REFERENCED_TABLE_NAME = 'setores'
  LIMIT 1
);

SET @drop_fk_equipas_setor := IF(
  @fk_equipas_setor IS NULL,
  'SELECT 1',
  CONCAT('ALTER TABLE `equipas` DROP FOREIGN KEY `', @fk_equipas_setor, '`')
);

PREPARE stmt FROM @drop_fk_equipas_setor;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `equipas`
  MODIFY `setor_id` INT UNSIGNED DEFAULT NULL;

ALTER TABLE `equipas`
  ADD CONSTRAINT `fk_equipas_setor`
    FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL;

UPDATE `funcionarios`
SET `setor_id` = NULL,
    `categoria_profissional` = NULL;

-- ========================================================
-- Fonte: database/2026_07_14_migracao_assiduidade.sql
-- ========================================================

-- Migração segura: suporte ampliado para assiduidade, turnos, escalas, ausências, ponto, banco de horas e permissões.
-- Não elimina dados existentes. Usa ALTER TABLE / CREATE TABLE IF NOT EXISTS e adiciona índices/chaves estrangeiras.
-- Reverter manualmente: ver secção final de comentários.

USE `gestor_assiduidade`;

SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_column_if_missing $$
CREATE PROCEDURE add_column_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64),
    IN p_column_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND COLUMN_NAME = p_column_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN `', p_column_name, '` ', p_column_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS add_index_if_missing $$
CREATE PROCEDURE add_index_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_index_name VARCHAR(64),
    IN p_index_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
        AND INDEX_NAME = p_index_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD ', p_index_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS add_fk_if_missing $$
CREATE PROCEDURE add_fk_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_constraint_name VARCHAR(64),
    IN p_constraint_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND CONSTRAINT_NAME = p_constraint_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD CONSTRAINT `', p_constraint_name, '` ', p_constraint_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

-- --------------------------------------------------------
-- 1. Equipas
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `equipas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(80) NOT NULL,
  `nome` VARCHAR(140) NOT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_equipas_codigo` (`codigo`),
  KEY `idx_equipas_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_column_if_missing('equipas', 'descricao', 'VARCHAR(255) DEFAULT NULL AFTER `nome`');
CALL add_column_if_missing('equipas', 'ativo', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER `descricao`');
CALL add_index_if_missing('equipas', 'idx_equipas_ativo', 'INDEX `idx_equipas_ativo` (`ativo`)');

-- --------------------------------------------------------
-- 2. Funcionários
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `funcionario_tipos_contrato` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(40) DEFAULT NULL,
  `nome` VARCHAR(120) NOT NULL,
  `tipo_rendimento` VARCHAR(120) DEFAULT NULL,
  `taxa_irs` DECIMAL(6,2) DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_funcionario_tipos_contrato_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `funcionario_tipos_contrato` (`codigo`, `nome`, `tipo_rendimento`, `taxa_irs`) VALUES
('1', 'TRABALHO DEPENDENTE', 'A - TRABALHO DEPENDENTE', NULL);

CREATE TABLE IF NOT EXISTS `funcionarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT UNSIGNED DEFAULT NULL,
  `equipa_id` INT UNSIGNED DEFAULT NULL,
  `codigo_picagem` VARCHAR(80) DEFAULT NULL,
  `entidade` VARCHAR(180) DEFAULT NULL,
  `numero_mecanografico` VARCHAR(50) DEFAULT NULL,
  `data_ficha` DATE DEFAULT NULL,
  `nome` VARCHAR(160) NOT NULL,
  `email` VARCHAR(160) DEFAULT NULL,
  `telefone` VARCHAR(40) DEFAULT NULL,
  `morada` VARCHAR(255) DEFAULT NULL,
  `localidade` VARCHAR(120) DEFAULT NULL,
  `codigo_pais` VARCHAR(20) DEFAULT NULL,
  `codigo_postal` VARCHAR(20) DEFAULT NULL,
  `telemovel` VARCHAR(40) DEFAULT NULL,
  `data_nascimento` DATE DEFAULT NULL,
  `doc_identificacao` VARCHAR(80) DEFAULT NULL,
  `data_validade_doc` DATE DEFAULT NULL,
  `local_emissao` VARCHAR(120) DEFAULT NULL,
  `naturalidade` VARCHAR(120) DEFAULT NULL,
  `codigo_residencia` VARCHAR(40) DEFAULT NULL,
  `genero` VARCHAR(30) DEFAULT NULL,
  `estado_civil` VARCHAR(60) DEFAULT NULL,
  `data_admissao` DATE DEFAULT NULL,
  `codigo_admissao` VARCHAR(40) DEFAULT NULL,
  `data_cessacao` DATE DEFAULT NULL,
  `codigo_demissao` VARCHAR(40) DEFAULT NULL,
  `data_inicio_diuturnidade` DATE DEFAULT NULL,
  `motivo_inativacao` VARCHAR(255) DEFAULT NULL,
  `funcao` VARCHAR(120) DEFAULT NULL,
  `categoria_profissional` VARCHAR(120) DEFAULT NULL,
  `irct` VARCHAR(120) DEFAULT NULL,
  `cct` VARCHAR(120) DEFAULT NULL,
  `nivel_profissional` VARCHAR(80) DEFAULT NULL,
  `defice_percentagem_paga` DECIMAL(6,2) DEFAULT NULL,
  `moeda` VARCHAR(10) DEFAULT NULL,
  `seccao` VARCHAR(120) DEFAULT NULL,
  `local_pagamento` VARCHAR(120) DEFAULT NULL,
  `codigo_subsidio_natal` VARCHAR(40) DEFAULT NULL,
  `codigo_subsidio_ferias` VARCHAR(40) DEFAULT NULL,
  `tipo_contrato` VARCHAR(80) DEFAULT NULL,
  `tipo_contrato_id` INT UNSIGNED DEFAULT NULL,
  `tipo_contrato_codigo` VARCHAR(40) DEFAULT NULL,
  `tipo_contrato_tipo_rendimento` VARCHAR(120) DEFAULT NULL,
  `tipo_contrato_taxa_irs` DECIMAL(6,2) DEFAULT NULL,
  `tipo_horario` VARCHAR(80) DEFAULT NULL,
  `tipo_horario_codigo` VARCHAR(40) DEFAULT NULL,
  `tipo_horario_unidade` VARCHAR(40) DEFAULT NULL,
  `tipo_horario_tratamento` VARCHAR(80) DEFAULT NULL,
  `tipo_horario_desc_semanal` VARCHAR(120) DEFAULT NULL,
  `carga_horaria_diaria` DECIMAL(5,2) NOT NULL DEFAULT 8.00,
  `carga_horaria_semanal` DECIMAL(5,2) NOT NULL DEFAULT 40.00,
  `horas_mes` DECIMAL(6,2) DEFAULT NULL,
  `salario_mensal` DECIMAL(10,2) DEFAULT NULL,
  `seguranca_social_codigo` VARCHAR(40) DEFAULT NULL,
  `seguranca_social_numero_beneficiario` VARCHAR(80) DEFAULT NULL,
  `nif` VARCHAR(20) DEFAULT NULL,
  `irs_estado_civil` VARCHAR(60) DEFAULT NULL,
  `conjugue` TINYINT(1) NOT NULL DEFAULT 0,
  `nif_conjugue` VARCHAR(20) DEFAULT NULL,
  `residencia_irs` VARCHAR(120) DEFAULT NULL,
  `beneficio_fiscal` VARCHAR(80) DEFAULT NULL,
  `numero_filhos` INT UNSIGNED DEFAULT NULL,
  `dependentes_deducao` INT UNSIGNED DEFAULT NULL,
  `deficientes_dependentes` INT UNSIGNED DEFAULT NULL,
  `titularidade_dependentes` INT UNSIGNED DEFAULT NULL,
  `relatorio_unico_estabelecimento` VARCHAR(120) DEFAULT NULL,
  `relatorio_unico_habilitacoes` VARCHAR(120) DEFAULT NULL,
  `relatorio_unico_profissao` VARCHAR(120) DEFAULT NULL,
  `relatorio_unico_situacao` VARCHAR(120) DEFAULT NULL,
  `relatorio_unico_nivel` VARCHAR(80) DEFAULT NULL,
  `relatorio_unico_nacionalidade` VARCHAR(80) DEFAULT NULL,
  `relatorio_unico_regime` VARCHAR(80) DEFAULT NULL,
  `carta_conducao_numero` VARCHAR(80) DEFAULT NULL,
  `carta_conducao_categoria_1` VARCHAR(40) DEFAULT NULL,
  `carta_conducao_categoria_2` VARCHAR(40) DEFAULT NULL,
  `carta_conducao_data_inicio` DATE DEFAULT NULL,
  `carta_conducao_data_validade` DATE DEFAULT NULL,
  `cursos` TEXT DEFAULT NULL,
  `linguas` TEXT DEFAULT NULL,
  `pin_ponto` VARCHAR(20) DEFAULT NULL,
  `codigo_cartao` VARCHAR(80) DEFAULT NULL,
  `codigo_biometrico` VARCHAR(80) DEFAULT NULL,
  `estado` ENUM('ativo','suspenso','inativo') NOT NULL DEFAULT 'ativo',
  `observacoes` TEXT DEFAULT NULL,
  `seguro` VARCHAR(160) DEFAULT NULL,
  `sindicato` VARCHAR(120) DEFAULT NULL,
  `sindicato_numero` VARCHAR(40) DEFAULT NULL,
  `banco` VARCHAR(120) DEFAULT NULL,
  `iban` VARCHAR(60) DEFAULT NULL,
  `conta_empresa` VARCHAR(80) DEFAULT NULL,
  `dados_fixos_codigo` VARCHAR(40) DEFAULT NULL,
  `dados_fixos_designacao` VARCHAR(160) DEFAULT NULL,
  `dados_fixos_quantidade` VARCHAR(80) DEFAULT NULL,
  `dados_fixos_valor` DECIMAL(10,2) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_funcionarios_codigo_picagem` (`codigo_picagem`),
  UNIQUE KEY `uk_funcionarios_numero_mecanografico` (`numero_mecanografico`),
  UNIQUE KEY `uk_funcionarios_pin_ponto` (`pin_ponto`),
  UNIQUE KEY `uk_funcionarios_codigo_cartao` (`codigo_cartao`),
  UNIQUE KEY `uk_funcionarios_codigo_biometrico` (`codigo_biometrico`),
  KEY `idx_funcionarios_equipa` (`equipa_id`),
  KEY `idx_funcionarios_tipo_contrato` (`tipo_contrato_id`),
  KEY `idx_funcionarios_estado` (`estado`),
  CONSTRAINT `fk_funcionarios_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_funcionarios_equipa` FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_funcionarios_tipo_contrato` FOREIGN KEY (`tipo_contrato_id`) REFERENCES `funcionario_tipos_contrato` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_column_if_missing('funcionarios', 'codigo_picagem', 'VARCHAR(80) DEFAULT NULL AFTER `equipa_id`');
CALL add_index_if_missing('funcionarios', 'uk_funcionarios_codigo_picagem', 'UNIQUE KEY `uk_funcionarios_codigo_picagem` (`codigo_picagem`)');
CALL add_column_if_missing('funcionarios', 'entidade', 'VARCHAR(180) DEFAULT NULL AFTER `equipa_id`');
CALL add_column_if_missing('funcionarios', 'data_ficha', 'DATE DEFAULT NULL AFTER `numero_mecanografico`');
CALL add_column_if_missing('funcionarios', 'data_nascimento', 'DATE DEFAULT NULL AFTER `telefone`');
CALL add_column_if_missing('funcionarios', 'doc_identificacao', 'VARCHAR(80) DEFAULT NULL AFTER `data_nascimento`');
CALL add_column_if_missing('funcionarios', 'data_validade_doc', 'DATE DEFAULT NULL AFTER `doc_identificacao`');
CALL add_column_if_missing('funcionarios', 'local_emissao', 'VARCHAR(120) DEFAULT NULL AFTER `data_validade_doc`');
CALL add_column_if_missing('funcionarios', 'naturalidade', 'VARCHAR(120) DEFAULT NULL AFTER `local_emissao`');
CALL add_column_if_missing('funcionarios', 'codigo_residencia', 'VARCHAR(40) DEFAULT NULL AFTER `naturalidade`');
CALL add_column_if_missing('funcionarios', 'genero', 'VARCHAR(30) DEFAULT NULL AFTER `codigo_residencia`');
CALL add_column_if_missing('funcionarios', 'estado_civil', 'VARCHAR(60) DEFAULT NULL AFTER `genero`');
CALL add_column_if_missing('funcionarios', 'morada', 'VARCHAR(255) DEFAULT NULL AFTER `telefone`');
CALL add_column_if_missing('funcionarios', 'localidade', 'VARCHAR(120) DEFAULT NULL AFTER `morada`');
CALL add_column_if_missing('funcionarios', 'codigo_pais', 'VARCHAR(20) DEFAULT NULL AFTER `localidade`');
CALL add_column_if_missing('funcionarios', 'codigo_postal', 'VARCHAR(20) DEFAULT NULL AFTER `codigo_pais`');
CALL add_column_if_missing('funcionarios', 'telemovel', 'VARCHAR(40) DEFAULT NULL AFTER `codigo_postal`');
CALL add_column_if_missing('funcionarios', 'codigo_admissao', 'VARCHAR(40) DEFAULT NULL AFTER `data_admissao`');
CALL add_column_if_missing('funcionarios', 'codigo_demissao', 'VARCHAR(40) DEFAULT NULL AFTER `data_cessacao`');
CALL add_column_if_missing('funcionarios', 'data_inicio_diuturnidade', 'DATE DEFAULT NULL AFTER `data_cessacao`');
CALL add_column_if_missing('funcionarios', 'motivo_inativacao', 'VARCHAR(255) DEFAULT NULL AFTER `estado`');
CALL add_column_if_missing('funcionarios', 'irct', 'VARCHAR(120) DEFAULT NULL AFTER `categoria_profissional`');
CALL add_column_if_missing('funcionarios', 'cct', 'VARCHAR(120) DEFAULT NULL AFTER `irct`');
CALL add_column_if_missing('funcionarios', 'nivel_profissional', 'VARCHAR(80) DEFAULT NULL AFTER `cct`');
CALL add_column_if_missing('funcionarios', 'defice_percentagem_paga', 'DECIMAL(6,2) DEFAULT NULL AFTER `nivel_profissional`');
CALL add_column_if_missing('funcionarios', 'moeda', 'VARCHAR(10) DEFAULT NULL AFTER `defice_percentagem_paga`');
CALL add_column_if_missing('funcionarios', 'seccao', 'VARCHAR(120) DEFAULT NULL AFTER `moeda`');
CALL add_column_if_missing('funcionarios', 'local_pagamento', 'VARCHAR(120) DEFAULT NULL AFTER `seccao`');
CALL add_column_if_missing('funcionarios', 'codigo_subsidio_natal', 'VARCHAR(40) DEFAULT NULL AFTER `local_pagamento`');
CALL add_column_if_missing('funcionarios', 'codigo_subsidio_ferias', 'VARCHAR(40) DEFAULT NULL AFTER `codigo_subsidio_natal`');
CALL add_column_if_missing('funcionarios', 'tipo_contrato_id', 'INT UNSIGNED DEFAULT NULL AFTER `tipo_contrato`');
CALL add_index_if_missing('funcionarios', 'idx_funcionarios_tipo_contrato', 'INDEX `idx_funcionarios_tipo_contrato` (`tipo_contrato_id`)');
CALL add_fk_if_missing('funcionarios', 'fk_funcionarios_tipo_contrato', 'FOREIGN KEY (`tipo_contrato_id`) REFERENCES `funcionario_tipos_contrato` (`id`) ON UPDATE CASCADE ON DELETE SET NULL');
CALL add_column_if_missing('funcionarios', 'tipo_contrato_codigo', 'VARCHAR(40) DEFAULT NULL AFTER `tipo_contrato_id`');
CALL add_column_if_missing('funcionarios', 'tipo_contrato_tipo_rendimento', 'VARCHAR(120) DEFAULT NULL AFTER `tipo_contrato_codigo`');
CALL add_column_if_missing('funcionarios', 'tipo_contrato_taxa_irs', 'DECIMAL(6,2) DEFAULT NULL AFTER `tipo_contrato_tipo_rendimento`');
CALL add_column_if_missing('funcionarios', 'tipo_horario', 'VARCHAR(80) DEFAULT NULL AFTER `tipo_contrato`');
CALL add_column_if_missing('funcionarios', 'tipo_horario_codigo', 'VARCHAR(40) DEFAULT NULL AFTER `tipo_horario`');
CALL add_column_if_missing('funcionarios', 'tipo_horario_unidade', 'VARCHAR(40) DEFAULT NULL AFTER `tipo_horario_codigo`');
CALL add_column_if_missing('funcionarios', 'tipo_horario_tratamento', 'VARCHAR(80) DEFAULT NULL AFTER `tipo_horario_unidade`');
CALL add_column_if_missing('funcionarios', 'tipo_horario_desc_semanal', 'VARCHAR(120) DEFAULT NULL AFTER `tipo_horario_tratamento`');
CALL add_column_if_missing('funcionarios', 'carga_horaria_diaria', 'DECIMAL(5,2) NOT NULL DEFAULT 8.00 AFTER `tipo_horario`');
CALL add_column_if_missing('funcionarios', 'horas_mes', 'DECIMAL(6,2) DEFAULT NULL AFTER `carga_horaria_semanal`');
CALL add_column_if_missing('funcionarios', 'salario_mensal', 'DECIMAL(10,2) DEFAULT NULL AFTER `horas_mes`');
CALL add_column_if_missing('funcionarios', 'seguranca_social_codigo', 'VARCHAR(40) DEFAULT NULL AFTER `salario_mensal`');
CALL add_column_if_missing('funcionarios', 'seguranca_social_numero_beneficiario', 'VARCHAR(80) DEFAULT NULL AFTER `seguranca_social_codigo`');
CALL add_column_if_missing('funcionarios', 'nif', 'VARCHAR(20) DEFAULT NULL AFTER `codigo_demissao`');
CALL add_column_if_missing('funcionarios', 'irs_estado_civil', 'VARCHAR(60) DEFAULT NULL AFTER `nif`');
CALL add_column_if_missing('funcionarios', 'conjugue', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `irs_estado_civil`');
CALL add_column_if_missing('funcionarios', 'nif_conjugue', 'VARCHAR(20) DEFAULT NULL AFTER `conjugue`');
CALL add_column_if_missing('funcionarios', 'residencia_irs', 'VARCHAR(120) DEFAULT NULL AFTER `nif_conjugue`');
CALL add_column_if_missing('funcionarios', 'beneficio_fiscal', 'VARCHAR(80) DEFAULT NULL AFTER `residencia_irs`');
CALL add_column_if_missing('funcionarios', 'numero_filhos', 'INT UNSIGNED DEFAULT NULL AFTER `beneficio_fiscal`');
CALL add_column_if_missing('funcionarios', 'dependentes_deducao', 'INT UNSIGNED DEFAULT NULL AFTER `numero_filhos`');
CALL add_column_if_missing('funcionarios', 'deficientes_dependentes', 'INT UNSIGNED DEFAULT NULL AFTER `dependentes_deducao`');
CALL add_column_if_missing('funcionarios', 'titularidade_dependentes', 'INT UNSIGNED DEFAULT NULL AFTER `deficientes_dependentes`');
CALL add_column_if_missing('funcionarios', 'relatorio_unico_estabelecimento', 'VARCHAR(120) DEFAULT NULL AFTER `seguranca_social_numero_beneficiario`');
CALL add_column_if_missing('funcionarios', 'relatorio_unico_habilitacoes', 'VARCHAR(120) DEFAULT NULL AFTER `relatorio_unico_estabelecimento`');
CALL add_column_if_missing('funcionarios', 'relatorio_unico_profissao', 'VARCHAR(120) DEFAULT NULL AFTER `relatorio_unico_habilitacoes`');
CALL add_column_if_missing('funcionarios', 'relatorio_unico_situacao', 'VARCHAR(120) DEFAULT NULL AFTER `relatorio_unico_profissao`');
CALL add_column_if_missing('funcionarios', 'relatorio_unico_nivel', 'VARCHAR(80) DEFAULT NULL AFTER `relatorio_unico_situacao`');
CALL add_column_if_missing('funcionarios', 'relatorio_unico_nacionalidade', 'VARCHAR(80) DEFAULT NULL AFTER `relatorio_unico_nivel`');
CALL add_column_if_missing('funcionarios', 'relatorio_unico_regime', 'VARCHAR(80) DEFAULT NULL AFTER `relatorio_unico_nacionalidade`');
CALL add_column_if_missing('funcionarios', 'carta_conducao_numero', 'VARCHAR(80) DEFAULT NULL AFTER `relatorio_unico_regime`');
CALL add_column_if_missing('funcionarios', 'carta_conducao_categoria_1', 'VARCHAR(40) DEFAULT NULL AFTER `carta_conducao_numero`');
CALL add_column_if_missing('funcionarios', 'carta_conducao_categoria_2', 'VARCHAR(40) DEFAULT NULL AFTER `carta_conducao_categoria_1`');
CALL add_column_if_missing('funcionarios', 'carta_conducao_data_inicio', 'DATE DEFAULT NULL AFTER `carta_conducao_categoria_2`');
CALL add_column_if_missing('funcionarios', 'carta_conducao_data_validade', 'DATE DEFAULT NULL AFTER `carta_conducao_data_inicio`');
CALL add_column_if_missing('funcionarios', 'cursos', 'TEXT DEFAULT NULL AFTER `carta_conducao_data_validade`');
CALL add_column_if_missing('funcionarios', 'linguas', 'TEXT DEFAULT NULL AFTER `cursos`');
CALL add_column_if_missing('funcionarios', 'observacoes', 'TEXT DEFAULT NULL AFTER `estado`');
CALL add_column_if_missing('funcionarios', 'seguro', 'VARCHAR(160) DEFAULT NULL AFTER `observacoes`');
CALL add_column_if_missing('funcionarios', 'sindicato', 'VARCHAR(120) DEFAULT NULL AFTER `seguro`');
CALL add_column_if_missing('funcionarios', 'sindicato_numero', 'VARCHAR(40) DEFAULT NULL AFTER `sindicato`');
CALL add_column_if_missing('funcionarios', 'banco', 'VARCHAR(120) DEFAULT NULL AFTER `sindicato_numero`');
CALL add_column_if_missing('funcionarios', 'iban', 'VARCHAR(60) DEFAULT NULL AFTER `banco`');
CALL add_column_if_missing('funcionarios', 'conta_empresa', 'VARCHAR(80) DEFAULT NULL AFTER `iban`');
CALL add_column_if_missing('funcionarios', 'dados_fixos_codigo', 'VARCHAR(40) DEFAULT NULL AFTER `conta_empresa`');
CALL add_column_if_missing('funcionarios', 'dados_fixos_designacao', 'VARCHAR(160) DEFAULT NULL AFTER `dados_fixos_codigo`');
CALL add_column_if_missing('funcionarios', 'dados_fixos_quantidade', 'VARCHAR(80) DEFAULT NULL AFTER `dados_fixos_designacao`');
CALL add_column_if_missing('funcionarios', 'dados_fixos_valor', 'DECIMAL(10,2) DEFAULT NULL AFTER `dados_fixos_quantidade`');
CALL add_column_if_missing('funcionarios', 'equipa_id', 'INT UNSIGNED DEFAULT NULL AFTER `utilizador_id`');
CALL add_index_if_missing('funcionarios', 'idx_funcionarios_equipa', 'INDEX `idx_funcionarios_equipa` (`equipa_id`)');
CALL add_fk_if_missing('funcionarios', 'fk_funcionarios_equipa', 'FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`) ON UPDATE CASCADE ON DELETE SET NULL');
CALL add_fk_if_missing('funcionarios', 'fk_funcionarios_utilizador', 'FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL');

-- --------------------------------------------------------
-- 3. Turnos
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `turnos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(120) NOT NULL,
  `codigo` VARCHAR(40) DEFAULT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `equipa_id` INT UNSIGNED DEFAULT NULL,
  `funcionario_id` INT UNSIGNED DEFAULT NULL,
  `hora_entrada` TIME NOT NULL,
  `hora_saida` TIME NOT NULL,
  `inicio_pausa` TIME DEFAULT NULL,
  `fim_pausa` TIME DEFAULT NULL,
  `tolerancia_entrada_min` SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  `tolerancia_saida_min` SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  `tolerancia_antes_min` SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  `tolerancia_depois_min` SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  `carga_diaria` DECIMAL(5,2) NOT NULL DEFAULT 8.00,
  `horas_previstas` DECIMAL(5,2) NOT NULL DEFAULT 8.00,
  `turno_noturno` TINYINT(1) NOT NULL DEFAULT 0,
  `total_periodos` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `permite_multiplos_periodos` TINYINT(1) NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_turnos_codigo` (`codigo`),
  KEY `idx_turnos_ativo` (`ativo`),
  KEY `idx_turnos_equipa` (`equipa_id`),
  KEY `idx_turnos_funcionario` (`funcionario_id`),
  CONSTRAINT `fk_turnos_equipa` FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_turnos_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_column_if_missing('turnos', 'descricao', 'VARCHAR(255) DEFAULT NULL AFTER `codigo`');
CALL add_column_if_missing('turnos', 'equipa_id', 'INT UNSIGNED DEFAULT NULL AFTER `codigo`');
CALL add_column_if_missing('turnos', 'funcionario_id', 'INT UNSIGNED DEFAULT NULL AFTER `equipa_id`');
CALL add_column_if_missing('turnos', 'tolerancia_antes_min', 'SMALLINT UNSIGNED NOT NULL DEFAULT 15 AFTER `tolerancia_saida_min`');
CALL add_column_if_missing('turnos', 'tolerancia_depois_min', 'SMALLINT UNSIGNED NOT NULL DEFAULT 15 AFTER `tolerancia_antes_min`');
CALL add_column_if_missing('turnos', 'carga_diaria', 'DECIMAL(5,2) NOT NULL DEFAULT 8.00 AFTER `tolerancia_depois_min`');
CALL add_column_if_missing('turnos', 'total_periodos', 'TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `horas_previstas`');
CALL add_column_if_missing('turnos', 'permite_multiplos_periodos', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `total_periodos`');
CALL add_index_if_missing('turnos', 'idx_turnos_equipa', 'INDEX `idx_turnos_equipa` (`equipa_id`)');
CALL add_index_if_missing('turnos', 'idx_turnos_funcionario', 'INDEX `idx_turnos_funcionario` (`funcionario_id`)');
CALL add_fk_if_missing('turnos', 'fk_turnos_equipa', 'FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`) ON UPDATE CASCADE ON DELETE SET NULL');
CALL add_fk_if_missing('turnos', 'fk_turnos_funcionario', 'FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE SET NULL');

CREATE TABLE IF NOT EXISTS `turno_periodos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `turno_id` INT UNSIGNED NOT NULL,
  `sequencia` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `hora_inicio` TIME NOT NULL,
  `hora_fim` TIME NOT NULL,
  `cruza_dia` TINYINT(1) NOT NULL DEFAULT 0,
  `tolerancia_antes_min` SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  `tolerancia_depois_min` SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  `minutos_previstos` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_turno_periodos_seq` (`turno_id`, `sequencia`),
  KEY `idx_turno_periodos_turno` (`turno_id`),
  CONSTRAINT `fk_turno_periodos_turno` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_column_if_missing('turno_periodos', 'cruza_dia', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `hora_fim`');
CALL add_column_if_missing('turno_periodos', 'tolerancia_antes_min', 'SMALLINT UNSIGNED NOT NULL DEFAULT 15 AFTER `cruza_dia`');
CALL add_column_if_missing('turno_periodos', 'tolerancia_depois_min', 'SMALLINT UNSIGNED NOT NULL DEFAULT 15 AFTER `tolerancia_antes_min`');
CALL add_column_if_missing('turno_periodos', 'minutos_previstos', 'SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `tolerancia_depois_min`');
CALL add_index_if_missing('turno_periodos', 'idx_turno_periodos_turno', 'INDEX `idx_turno_periodos_turno` (`turno_id`)');
CALL add_fk_if_missing('turno_periodos', 'fk_turno_periodos_turno', 'FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`) ON UPDATE CASCADE ON DELETE CASCADE');

-- --------------------------------------------------------
-- 4. Escalas
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `escala_funcionarios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `funcionario_id` INT UNSIGNED NOT NULL,
  `utilizador_id` INT UNSIGNED DEFAULT NULL,
  `setor_id` INT UNSIGNED DEFAULT NULL,
  `equipa_id` INT UNSIGNED DEFAULT NULL,
  `ano` SMALLINT UNSIGNED NOT NULL,
  `mes` TINYINT UNSIGNED NOT NULL,
  `data_escala` DATE NOT NULL,
  `dia` TINYINT UNSIGNED NOT NULL,
  `tipo_dia` ENUM('turno','folga','ferias','falta','baixa','substituicao','licenca_amamentacao') NOT NULL DEFAULT 'turno',
  `turno_id` INT UNSIGNED DEFAULT NULL,
  `substitui_funcionario_id` INT UNSIGNED DEFAULT NULL,
  `folga_trabalhada` TINYINT(1) NOT NULL DEFAULT 0,
  `origem` ENUM('manual','importacao') NOT NULL DEFAULT 'manual',
  `importado_por` INT UNSIGNED DEFAULT NULL,
  `observacoes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_escala_funcionario_dia` (`funcionario_id`, `data_escala`),
  KEY `idx_escala_funcionarios_periodo` (`ano`, `mes`),
  KEY `idx_escala_funcionarios_equipa` (`equipa_id`, `data_escala`),
  KEY `idx_escala_funcionarios_turno` (`turno_id`),
  KEY `idx_escala_funcionarios_origem` (`origem`),
  CONSTRAINT `fk_escala_funcionarios_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_escala_funcionarios_turno` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_escala_funcionarios_substitui` FOREIGN KEY (`substitui_funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_column_if_missing('escala_funcionarios', 'origem', 'ENUM(\'manual\',\'importacao\') NOT NULL DEFAULT \'manual\' AFTER `folga_trabalhada`');
CALL add_column_if_missing('escala_funcionarios', 'importado_por', 'INT UNSIGNED DEFAULT NULL AFTER `origem`');
CALL add_index_if_missing('escala_funcionarios', 'idx_escala_funcionarios_origem', 'INDEX `idx_escala_funcionarios_origem` (`origem`)');
CALL add_fk_if_missing('escala_funcionarios', 'fk_escala_funcionarios_funcionario', 'FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE');
CALL add_fk_if_missing('escala_funcionarios', 'fk_escala_funcionarios_turno', 'FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`) ON UPDATE CASCADE ON DELETE SET NULL');
CALL add_fk_if_missing('escala_funcionarios', 'fk_escala_funcionarios_substitui', 'FOREIGN KEY (`substitui_funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE SET NULL');

-- --------------------------------------------------------
-- 5. Ausências
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ferias_ausencias` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pedido_ausencia_id` INT UNSIGNED DEFAULT NULL,
  `funcionario_id` INT UNSIGNED DEFAULT NULL,
  `utilizador_id` INT UNSIGNED DEFAULT NULL,
  `tipo_ausencia_id` INT UNSIGNED NOT NULL,
  `data_inicio` DATE NOT NULL,
  `data_fim` DATE NOT NULL,
  `hora_inicio` TIME DEFAULT NULL,
  `hora_fim` TIME DEFAULT NULL,
  `dia_completo` TINYINT(1) NOT NULL DEFAULT 1,
  `minutos_justificados` INT UNSIGNED DEFAULT NULL,
  `afeta_assiduidade` TINYINT(1) NOT NULL DEFAULT 1,
  `desconta_banco_horas` TINYINT(1) NOT NULL DEFAULT 0,
  `estado` ENUM('pendente','aprovado','rejeitado','cancelado') NOT NULL DEFAULT 'pendente',
  `motivo` TEXT DEFAULT NULL,
  `ficheiro_justificativo` VARCHAR(255) DEFAULT NULL,
  `aprovado_por` INT UNSIGNED DEFAULT NULL,
  `aprovado_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ferias_funcionario_periodo` (`funcionario_id`, `data_inicio`, `data_fim`),
  KEY `idx_ferias_estado` (`estado`),
  KEY `idx_ferias_tipo` (`tipo_ausencia_id`),
  CONSTRAINT `fk_ferias_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_ferias_tipo` FOREIGN KEY (`tipo_ausencia_id`) REFERENCES `tipos_ausencia` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_ferias_aprovado_por` FOREIGN KEY (`aprovado_por`) REFERENCES `utilizadores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_column_if_missing('ferias_ausencias', 'pedido_ausencia_id', 'INT UNSIGNED DEFAULT NULL AFTER `id`');
CALL add_column_if_missing('ferias_ausencias', 'motivo', 'TEXT DEFAULT NULL AFTER `estado`');
CALL add_column_if_missing('ferias_ausencias', 'ficheiro_justificativo', 'VARCHAR(255) DEFAULT NULL AFTER `motivo`');
CALL add_column_if_missing('ferias_ausencias', 'aprovado_por', 'INT UNSIGNED DEFAULT NULL AFTER `ficheiro_justificativo`');
CALL add_column_if_missing('ferias_ausencias', 'aprovado_at', 'DATETIME DEFAULT NULL AFTER `aprovado_por`');
CALL add_fk_if_missing('ferias_ausencias', 'fk_ferias_funcionario', 'FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE SET NULL');
CALL add_fk_if_missing('ferias_ausencias', 'fk_ferias_tipo', 'FOREIGN KEY (`tipo_ausencia_id`) REFERENCES `tipos_ausencia` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT');
CALL add_fk_if_missing('ferias_ausencias', 'fk_ferias_aprovado_por', 'FOREIGN KEY (`aprovado_por`) REFERENCES `utilizadores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL');

CALL add_column_if_missing('pedidos_ausencia', 'observacoes_aprovacao', 'TEXT DEFAULT NULL AFTER `motivo`');
CALL add_column_if_missing('pedidos_ausencia', 'ficheiro_justificativo', 'VARCHAR(255) DEFAULT NULL AFTER `motivo`');
CALL add_column_if_missing('pedidos_ausencia', 'aprovado_por', 'INT UNSIGNED DEFAULT NULL AFTER `observacoes_aprovacao`');
CALL add_column_if_missing('pedidos_ausencia', 'aprovado_at', 'DATETIME DEFAULT NULL AFTER `aprovado_por`');
CALL add_column_if_missing('pedidos_ausencia', 'estado', 'ENUM(\'pendente\',\'aprovado\',\'rejeitado\',\'cancelado\') NOT NULL DEFAULT \'pendente\' AFTER `data_fim`');
CALL add_fk_if_missing('pedidos_ausencia', 'fk_pedidos_ausencia_aprovado_por', 'FOREIGN KEY (`aprovado_por`) REFERENCES `utilizadores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL');

-- --------------------------------------------------------
-- 6. Registos de ponto
-- --------------------------------------------------------
CALL add_column_if_missing('registos_ponto', 'funcionario_id', 'INT UNSIGNED DEFAULT NULL AFTER `utilizador_id`');
CALL add_column_if_missing('registos_ponto', 'data_referencia', 'DATE DEFAULT NULL AFTER `data_hora`');
CALL add_column_if_missing('registos_ponto', 'registo_manual', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `origem`');
CALL add_column_if_missing('registos_ponto', 'motivo_correcao', 'VARCHAR(255) DEFAULT NULL AFTER `observacoes`');
CALL add_column_if_missing('registos_ponto', 'atualizado_por', 'INT UNSIGNED DEFAULT NULL AFTER `criado_por`');
CALL add_index_if_missing('registos_ponto', 'idx_registos_funcionario_data', 'INDEX `idx_registos_funcionario_data` (`funcionario_id`, `data_hora`)');
CALL add_index_if_missing('registos_ponto', 'idx_registos_data_referencia', 'INDEX `idx_registos_data_referencia` (`data_referencia`)');
CALL add_fk_if_missing('registos_ponto', 'fk_registos_ponto_funcionario', 'FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE SET NULL');
CALL add_fk_if_missing('registos_ponto', 'fk_registos_ponto_atualizado_por', 'FOREIGN KEY (`atualizado_por`) REFERENCES `utilizadores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL');

-- --------------------------------------------------------
-- 7. Banco de horas
-- --------------------------------------------------------
CALL add_column_if_missing('banco_horas', 'funcionario_id', 'INT UNSIGNED DEFAULT NULL AFTER `utilizador_id`');
CALL add_column_if_missing('banco_horas', 'resumo_diario_id', 'BIGINT UNSIGNED DEFAULT NULL AFTER `pedido_ausencia_id`');
CALL add_column_if_missing('banco_horas', 'manual', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `origem`');
CALL add_index_if_missing('banco_horas', 'idx_banco_horas_funcionario_data', 'INDEX `idx_banco_horas_funcionario_data` (`funcionario_id`, `data_movimento`)');
CALL add_index_if_missing('banco_horas', 'idx_banco_horas_resumo', 'INDEX `idx_banco_horas_resumo` (`resumo_diario_id`)');
CALL add_fk_if_missing('banco_horas', 'fk_banco_horas_funcionario', 'FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE SET NULL');
CALL add_fk_if_missing('banco_horas', 'fk_banco_horas_resumo', 'FOREIGN KEY (`resumo_diario_id`) REFERENCES `resumo_diario_assiduidade` (`id`) ON UPDATE CASCADE ON DELETE SET NULL');

-- --------------------------------------------------------
-- 8. Permissões e exceções por utilizador
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permissoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(120) NOT NULL,
  `nome` VARCHAR(160) NOT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permissoes_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_column_if_missing('permissoes', 'codigo', 'VARCHAR(120) NOT NULL DEFAULT "" AFTER `id`');
CALL add_column_if_missing('permissoes', 'nome', 'VARCHAR(160) NOT NULL DEFAULT "" AFTER `codigo`');
CALL add_column_if_missing('permissoes', 'descricao', 'VARCHAR(255) DEFAULT NULL AFTER `nome`');
CALL add_column_if_missing('permissoes', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `descricao`');
UPDATE `permissoes`
SET `codigo` = COALESCE(NULLIF(`codigo`, ''), `nome`)
WHERE `codigo` = '';
CALL add_index_if_missing('permissoes', 'uk_permissoes_codigo', 'UNIQUE KEY `uk_permissoes_codigo` (`codigo`)');

CREATE TABLE IF NOT EXISTS `papel_permissoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `papel_id` INT UNSIGNED NOT NULL,
  `permissao_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_papel_permissoes` (`papel_id`, `permissao_id`),
  KEY `idx_papel_permissoes_permissao` (`permissao_id`),
  CONSTRAINT `fk_papel_permissoes_papel` FOREIGN KEY (`papel_id`) REFERENCES `papeis` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_papel_permissoes_permissao` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `utilizador_permissoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT UNSIGNED NOT NULL,
  `permissao_id` INT UNSIGNED NOT NULL,
  `efeito` ENUM('permitir','negar') NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_utilizador_permissoes` (`utilizador_id`, `permissao_id`),
  KEY `idx_utilizador_permissoes_permissao` (`permissao_id`),
  CONSTRAINT `fk_utilizador_permissoes_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_utilizador_permissoes_permissao` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_column_if_missing('utilizador_permissoes', 'efeito', 'ENUM(\'permitir\',\'negar\') NOT NULL DEFAULT \'permitir\' AFTER `permissao_id`');
CALL add_column_if_missing('utilizador_permissoes', 'updated_at', 'DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');

-- --------------------------------------------------------
-- Fim da migração
-- --------------------------------------------------------

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;

-- Reversão manual sugerida:
-- ALTER TABLE `funcionarios` DROP COLUMN `codigo_picagem`, `data_nascimento`, `data_inicio_diuturnidade`, `motivo_inativacao`, `tipo_horario`, `carga_horaria_diaria`, `observacoes`, `equipa_id`;
-- ALTER TABLE `turnos` DROP COLUMN `descricao`, `equipa_id`, `funcionario_id`, `tolerancia_antes_min`, `tolerancia_depois_min`, `carga_diaria`, `total_periodos`, `permite_multiplos_periodos`;
-- ALTER TABLE `turno_periodos` DROP TABLE IF EXISTS `turno_periodos`;
-- ALTER TABLE `escala_funcionarios` DROP COLUMN `origem`, `importado_por`;
-- ALTER TABLE `registos_ponto` DROP COLUMN `funcionario_id`, `data_referencia`, `registo_manual`, `motivo_correcao`, `atualizado_por`;
-- ALTER TABLE `banco_horas` DROP COLUMN `funcionario_id`, `resumo_diario_id`, `manual`;
-- DROP TABLE IF EXISTS `permissoes`, `papel_permissoes`, `utilizador_permissoes`, `ferias_ausencias`;
-- Nota: use DROP TABLEs e ALTERs apenas após validação, porque podem remover dados existentes.

-- ========================================================
-- Fonte: database/2026_07_15_codigo_picagem_hash.sql
-- ========================================================

-- Migration: add codigo_picagem hash and attempts/lock fields to funcionarios.
-- Uses a helper procedure for MySQL/MariaDB compatibility.

USE `gestor_assiduidade`;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_column_if_missing $$
CREATE PROCEDURE add_column_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64),
    IN p_column_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND COLUMN_NAME = p_column_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN `', p_column_name, '` ', p_column_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

CALL add_column_if_missing('funcionarios', 'codigo_picagem_hash', 'VARCHAR(255) DEFAULT NULL AFTER `codigo_picagem`');
CALL add_column_if_missing('funcionarios', 'codigo_picagem_tentativas', 'INT NOT NULL DEFAULT 0 AFTER `codigo_picagem_hash`');
CALL add_column_if_missing('funcionarios', 'codigo_picagem_bloqueado_ate', 'DATETIME DEFAULT NULL AFTER `codigo_picagem_tentativas`');

DROP PROCEDURE IF EXISTS add_column_if_missing;

-- Existing codigo_picagem values may contain plaintext codes. Migrate them with an administrative script,
-- then clear plaintext values when the application no longer needs the fallback.

-- ========================================================
-- Fonte: database/2026_07_15_equipas_mudancas.sql
-- ========================================================

-- Migration: create equipa_mudancas table
CREATE TABLE IF NOT EXISTS `equipa_mudancas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `funcionario_id` INT UNSIGNED NOT NULL,
  `equipa_antiga_id` INT UNSIGNED DEFAULT NULL,
  `equipa_nova_id` INT UNSIGNED DEFAULT NULL,
  `data_efeito` DATE NOT NULL,
  `motivo` VARCHAR(1024) DEFAULT NULL,
  `utilizador_responsavel_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_equipa_mudancas_funcionario` (`funcionario_id`),
  KEY `idx_equipa_mudancas_equipa_antiga` (`equipa_antiga_id`),
  KEY `idx_equipa_mudancas_equipa_nova` (`equipa_nova_id`),
  KEY `idx_equipa_mudancas_responsavel` (`utilizador_responsavel_id`),
  CONSTRAINT `fk_equipa_mudancas_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_equipa_mudancas_equipa_antiga` FOREIGN KEY (`equipa_antiga_id`) REFERENCES `equipas` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_equipa_mudancas_equipa_nova` FOREIGN KEY (`equipa_nova_id`) REFERENCES `equipas` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_equipa_mudancas_responsavel` FOREIGN KEY (`utilizador_responsavel_id`) REFERENCES `utilizadores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- Fonte: database/2026_07_15_escala_periodos.sql
-- ========================================================

-- Migration: add escala_periodos for multiple shifts per day
CREATE TABLE IF NOT EXISTS `escala_periodos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `funcionario_id` INT UNSIGNED NOT NULL,
  `data_escala` DATE NOT NULL,
  `sequencia` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `turno_id` INT UNSIGNED DEFAULT NULL,
  `minutos_previstos` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `observacoes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_escala_periodos_funcionario_data` (`funcionario_id`, `data_escala`),
  CONSTRAINT `fk_escala_periodos_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_escala_periodos_turno` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- Fonte: database/2026_07_15_horas_extra_regras.sql
-- ========================================================

-- Migration: create table for overtime rules and default rules.

USE `gestor_assiduidade`;

CREATE TABLE IF NOT EXISTS `horas_extra_regras` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(100) NOT NULL,
  `nome` VARCHAR(255) NOT NULL,
  `porcentagem` INT NOT NULL COMMENT 'Percentagem a aplicar (ex: 150 para 150%)',
  `prioridade` INT NOT NULL DEFAULT 0,
  `data_inicio` DATE NOT NULL,
  `data_fim` DATE DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_horas_extra_regras_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default rules, valid from 2020-01-01 to cover historical periods.
INSERT INTO `horas_extra_regras`
(`codigo`, `nome`, `porcentagem`, `prioridade`, `data_inicio`, `data_fim`, `ativo`) VALUES
('noturno', 'Trabalho noturno (22:00-07:00)', 200, 200, '2020-01-01', NULL, 1),
('segundo_turno_primeira_hora', 'Primeira hora extra no 2o turno', 150, 100, '2020-01-01', NULL, 1),
('segundo_turno_subsequente', 'Horas extra subsequentes no 2o turno', 175, 90, '2020-01-01', NULL, 1)
ON DUPLICATE KEY UPDATE
  `nome` = VALUES(`nome`),
  `porcentagem` = VALUES(`porcentagem`),
  `prioridade` = VALUES(`prioridade`),
  `data_inicio` = VALUES(`data_inicio`),
  `data_fim` = VALUES(`data_fim`),
  `ativo` = VALUES(`ativo`);

-- Configuracao da resolucao de prioridade: por defeito 'maior_percentagem'.
CREATE TABLE IF NOT EXISTS `config_horas_extra` (
  `chave` VARCHAR(100) NOT NULL,
  `valor` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `config_horas_extra` (`chave`, `valor`)
VALUES ('resolucao_prioridade', 'maior_percentagem')
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`);

-- ========================================================
-- Fonte: database/2026_07_15_registos_ponto_logs.sql
-- ========================================================

-- Migration: create registos_ponto_logs table to audit corrections
CREATE TABLE IF NOT EXISTS `registos_ponto_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `registo_ponto_id` BIGINT UNSIGNED NOT NULL,
  `operacao` ENUM('insercao','correcao','remocao','importacao') NOT NULL,
  `dados_antigos` JSON DEFAULT NULL,
  `dados_novos` JSON DEFAULT NULL,
  `utilizador_id` INT UNSIGNED DEFAULT NULL,
  `motivo` VARCHAR(1024) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_registos_ponto_logs_registo` (`registo_ponto_id`),
  CONSTRAINT `fk_registos_ponto_logs_registo` FOREIGN KEY (`registo_ponto_id`) REFERENCES `registos_ponto` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- Fonte: database/2026_07_16_notificacoes_aniversarios_diuturnidades.sql
-- ========================================================

USE `gestor_assiduidade`;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_column_if_missing $$
CREATE PROCEDURE add_column_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64),
    IN p_column_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND COLUMN_NAME = p_column_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN `', p_column_name, '` ', p_column_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS add_index_if_missing $$
CREATE PROCEDURE add_index_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_index_name VARCHAR(64),
    IN p_index_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND INDEX_NAME = p_index_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD ', p_index_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

CALL add_column_if_missing('funcionarios', 'data_nascimento', 'DATE DEFAULT NULL AFTER `funcao`');
CALL add_column_if_missing('funcionarios', 'diuturnidade_data_base', 'DATE DEFAULT NULL AFTER `data_admissao`');
CALL add_column_if_missing('funcionarios', 'diuturnidade_ciclo_anos', 'SMALLINT UNSIGNED DEFAULT NULL AFTER `diuturnidade_data_base`');
CALL add_column_if_missing('funcionarios', 'diuturnidade_ativa', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER `diuturnidade_ciclo_anos`');
CALL add_index_if_missing('funcionarios', 'idx_funcionarios_data_nascimento', 'INDEX `idx_funcionarios_data_nascimento` (`data_nascimento`)');
CALL add_index_if_missing('funcionarios', 'idx_funcionarios_diuturnidade_base', 'INDEX `idx_funcionarios_diuturnidade_base` (`diuturnidade_data_base`)');

UPDATE `funcionarios` f
INNER JOIN `utilizadores` u ON u.id = f.utilizador_id
SET f.data_nascimento = u.data_nascimento
WHERE f.data_nascimento IS NULL
  AND u.data_nascimento IS NOT NULL;

UPDATE `funcionarios`
SET `diuturnidade_data_base` = `data_admissao`
WHERE `diuturnidade_data_base` IS NULL
  AND `data_admissao` IS NOT NULL;

CREATE TABLE IF NOT EXISTS `permissoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(120) NOT NULL,
  `nome` VARCHAR(160) NOT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permissoes_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_column_if_missing('permissoes', 'codigo', 'VARCHAR(120) NOT NULL DEFAULT "" AFTER `id`');
CALL add_column_if_missing('permissoes', 'nome', 'VARCHAR(160) NOT NULL DEFAULT "" AFTER `codigo`');
CALL add_column_if_missing('permissoes', 'descricao', 'VARCHAR(255) DEFAULT NULL AFTER `nome`');
CALL add_column_if_missing('permissoes', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `descricao`');
CALL add_index_if_missing('permissoes', 'uk_permissoes_codigo', 'UNIQUE KEY `uk_permissoes_codigo` (`codigo`)');

CREATE TABLE IF NOT EXISTS `papel_permissoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `papel_id` INT UNSIGNED NOT NULL,
  `permissao_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_papel_permissao` (`papel_id`, `permissao_id`),
  KEY `idx_papel_permissoes_permissao` (`permissao_id`),
  CONSTRAINT `fk_papel_permissoes_papel`
    FOREIGN KEY (`papel_id`) REFERENCES `papeis` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_papel_permissoes_permissao`
    FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissoes` (`codigo`, `nome`, `descricao`) VALUES
('notificacoes.ver', 'Ver notificações', 'Ver notificações de aniversários e diuturnidades'),
('notificacoes.gerir', 'Gerir notificações', 'Gerir notificações e confirmar diuturnidades'),
('funcionarios.ver_idade', 'Ver idade dos funcionários', 'Ver idade dos funcionários nas notificações')
ON DUPLICATE KEY UPDATE
  `nome` = VALUES(`nome`),
  `descricao` = VALUES(`descricao`);

INSERT INTO `papel_permissoes` (`papel_id`, `permissao_id`)
SELECT p.id, pe.id
FROM `papeis` p
INNER JOIN `permissoes` pe ON pe.codigo IN ('notificacoes.ver', 'notificacoes.gerir', 'funcionarios.ver_idade')
WHERE p.slug IN ('administrador', 'recursos-humanos')
ON DUPLICATE KEY UPDATE `papel_id` = VALUES(`papel_id`);

INSERT INTO `papel_permissoes` (`papel_id`, `permissao_id`)
SELECT p.id, pe.id
FROM `papeis` p
INNER JOIN `permissoes` pe ON pe.codigo = 'notificacoes.ver'
WHERE p.slug = 'chefia'
ON DUPLICATE KEY UPDATE `papel_id` = VALUES(`papel_id`);

CREATE TABLE IF NOT EXISTS `notificacoes_config` (
  `chave` VARCHAR(80) NOT NULL,
  `valor` VARCHAR(255) NOT NULL,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `notificacoes_config` (`chave`, `valor`) VALUES
('aniversarios_dias_aviso', '30'),
('diuturnidades_dias_aviso', '60'),
('diuturnidades_ciclo_anos', '5')
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`);

CREATE TABLE IF NOT EXISTS `notificacoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo` ENUM('aniversario','diuturnidade') NOT NULL,
  `funcionario_id` INT UNSIGNED NOT NULL,
  `titulo` VARCHAR(180) NOT NULL,
  `mensagem` TEXT NOT NULL,
  `data_evento` DATE NOT NULL,
  `referencia_ano` SMALLINT UNSIGNED NOT NULL,
  `ciclo_numero` SMALLINT UNSIGNED DEFAULT NULL,
  `permissao_codigo` VARCHAR(120) NOT NULL DEFAULT 'notificacoes.ver',
  `estado` ENUM('ativa','resolvida','ocultada') NOT NULL DEFAULT 'ativa',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_notificacao_evento` (`tipo`, `funcionario_id`, `referencia_ano`, `ciclo_numero`),
  KEY `idx_notificacoes_tipo_data` (`tipo`, `data_evento`),
  KEY `idx_notificacoes_estado` (`estado`),
  KEY `idx_notificacoes_funcionario` (`funcionario_id`),
  CONSTRAINT `fk_notificacoes_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notificacao_destinatarios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `notificacao_id` BIGINT UNSIGNED NOT NULL,
  `utilizador_id` INT UNSIGNED NOT NULL,
  `lida_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_notificacao_destinatario` (`notificacao_id`, `utilizador_id`),
  KEY `idx_notificacao_destinatarios_utilizador` (`utilizador_id`, `lida_at`),
  CONSTRAINT `fk_notificacao_destinatarios_notificacao`
    FOREIGN KEY (`notificacao_id`) REFERENCES `notificacoes` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_notificacao_destinatarios_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `diuturnidades_atribuicoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `funcionario_id` INT UNSIGNED NOT NULL,
  `data_base` DATE NOT NULL,
  `ciclo_anos` SMALLINT UNSIGNED NOT NULL,
  `ciclo_numero` SMALLINT UNSIGNED NOT NULL,
  `data_vencimento` DATE NOT NULL,
  `confirmado_por` INT UNSIGNED DEFAULT NULL,
  `confirmado_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `observacoes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_diuturnidade_atribuicao` (`funcionario_id`, `ciclo_numero`, `data_vencimento`),
  KEY `idx_diuturnidades_funcionario` (`funcionario_id`, `data_vencimento`),
  KEY `idx_diuturnidades_confirmado_por` (`confirmado_por`),
  CONSTRAINT `fk_diuturnidades_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_diuturnidades_confirmado_por`
    FOREIGN KEY (`confirmado_por`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS add_index_if_missing;


-- ========================================================
-- Fonte: database/2026_07_17_controlo_acesso_permissoes.sql
-- ========================================================

USE `gestor_assiduidade`;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_column_if_missing $$
CREATE PROCEDURE add_column_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64),
    IN p_column_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND COLUMN_NAME = p_column_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN `', p_column_name, '` ', p_column_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

CREATE TABLE IF NOT EXISTS `permissoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(120) NOT NULL,
  `nome` VARCHAR(160) NOT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permissoes_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_column_if_missing('permissoes', 'nome', 'VARCHAR(160) NOT NULL DEFAULT "" AFTER `codigo`');
CALL add_column_if_missing('permissoes', 'descricao', 'VARCHAR(255) DEFAULT NULL AFTER `nome`');
CALL add_column_if_missing('permissoes', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `descricao`');

CREATE TABLE IF NOT EXISTS `papel_permissoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `papel_id` INT UNSIGNED NOT NULL,
  `permissao_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_papel_permissao` (`papel_id`, `permissao_id`),
  KEY `idx_papel_permissoes_permissao` (`permissao_id`),
  CONSTRAINT `fk_papel_permissoes_papel`
    FOREIGN KEY (`papel_id`) REFERENCES `papeis` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_papel_permissoes_permissao`
    FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `utilizador_permissoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT UNSIGNED NOT NULL,
  `permissao_id` INT UNSIGNED NOT NULL,
  `efeito` ENUM('permitir','negar') NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_utilizador_permissao` (`utilizador_id`, `permissao_id`),
  KEY `idx_utilizador_permissoes_permissao` (`permissao_id`),
  CONSTRAINT `fk_utilizador_permissoes_utilizador`
    FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_utilizador_permissoes_permissao`
    FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `papeis` (`nome`, `slug`, `descricao`, `ativo`) VALUES
('Administrador', 'administrador', 'Acesso total ao sistema', 1),
('Secretaria', 'secretaria', 'Operação administrativa corrente', 1),
('Direção', 'direcao', 'Consulta e validação de gestão', 1),
('Consulta/Auditoria', 'consulta-auditoria', 'Consulta, auditoria e relatórios', 1)
ON DUPLICATE KEY UPDATE
  `nome` = VALUES(`nome`),
  `descricao` = VALUES(`descricao`),
  `ativo` = 1;

INSERT INTO `permissoes` (`codigo`, `nome`, `descricao`) VALUES
('funcionarios.consultar', 'Consultar funcionários', 'Aceder a listagens e detalhe de funcionários'),
('funcionarios.editar', 'Editar funcionários', 'Criar e alterar dados de funcionários'),
('funcionarios.dados_sensiveis', 'Consultar dados pessoais sensíveis', 'Ver dados pessoais sensíveis dos funcionários'),
('funcionarios.desativar', 'Desativar funcionários', 'Desativar ou reativar funcionários'),
('equipas.gerir', 'Gerir equipas', 'Criar, editar e remover equipas'),
('turnos.gerir', 'Gerir turnos', 'Criar, editar, remover e associar turnos'),
('escalas.gerir', 'Gerir escalas', 'Consultar e editar escalas mensais'),
('escalas.importar', 'Importar escalas', 'Importar escalas externas'),
('ponto.consultar', 'Consultar ponto', 'Consultar registos de ponto'),
('ponto.corrigir', 'Corrigir ponto', 'Registar ou corrigir movimentos de ponto'),
('ocorrencias.validar', 'Validar ocorrências', 'Validar ocorrências operacionais'),
('ausencias.gerir', 'Gerir ausências', 'Criar e gerir pedidos de ausência'),
('justificacoes.validar', 'Validar justificações', 'Aprovar ou recusar justificações'),
('ferias.gerir', 'Gerir férias', 'Gerir pedidos e marcações de férias'),
('banco_horas.consultar', 'Consultar banco de horas', 'Consultar saldos e movimentos do banco de horas'),
('banco_horas.ajustar', 'Ajustar banco de horas', 'Criar ajustes manuais no banco de horas'),
('relatorios.consultar', 'Consultar relatórios', 'Consultar relatórios'),
('relatorios.exportar', 'Exportar relatórios', 'Exportar relatórios'),
('utilizadores.gerir', 'Gerir utilizadores', 'Criar, editar e remover utilizadores'),
('permissoes.gerir', 'Gerir permissões', 'Gerir permissões por utilizador'),
('notificacoes.ver', 'Ver notificações', 'Ver notificações de aniversário e diuturnidade'),
('notificacoes.gerir', 'Gerir notificações', 'Configurar notificações e confirmar diuturnidades')
ON DUPLICATE KEY UPDATE
  `nome` = VALUES(`nome`),
  `descricao` = VALUES(`descricao`);

INSERT IGNORE INTO `papel_permissoes` (`papel_id`, `permissao_id`)
SELECT p.id, pe.id
FROM `papeis` p
CROSS JOIN `permissoes` pe
WHERE p.slug = 'administrador';

INSERT IGNORE INTO `papel_permissoes` (`papel_id`, `permissao_id`)
SELECT p.id, pe.id
FROM `papeis` p
INNER JOIN `permissoes` pe ON pe.codigo IN (
  'funcionarios.consultar',
  'funcionarios.editar',
  'funcionarios.dados_sensiveis',
  'equipas.gerir',
  'turnos.gerir',
  'escalas.gerir',
  'escalas.importar',
  'ponto.consultar',
  'ponto.corrigir',
  'ausencias.gerir',
  'justificacoes.validar',
  'ferias.gerir',
  'banco_horas.consultar',
  'banco_horas.ajustar',
  'relatorios.consultar',
  'relatorios.exportar',
  'notificacoes.ver'
)
WHERE p.slug = 'secretaria';

INSERT IGNORE INTO `papel_permissoes` (`papel_id`, `permissao_id`)
SELECT p.id, pe.id
FROM `papeis` p
INNER JOIN `permissoes` pe ON pe.codigo IN (
  'funcionarios.consultar',
  'funcionarios.dados_sensiveis',
  'funcionarios.desativar',
  'equipas.gerir',
  'turnos.gerir',
  'escalas.gerir',
  'ponto.consultar',
  'ocorrencias.validar',
  'ausencias.gerir',
  'justificacoes.validar',
  'ferias.gerir',
  'banco_horas.consultar',
  'relatorios.consultar',
  'relatorios.exportar',
  'notificacoes.ver',
  'notificacoes.gerir'
)
WHERE p.slug = 'direcao';

INSERT IGNORE INTO `papel_permissoes` (`papel_id`, `permissao_id`)
SELECT p.id, pe.id
FROM `papeis` p
INNER JOIN `permissoes` pe ON pe.codigo IN (
  'funcionarios.consultar',
  'ponto.consultar',
  'banco_horas.consultar',
  'relatorios.consultar',
  'relatorios.exportar',
  'notificacoes.ver'
)
WHERE p.slug = 'consulta-auditoria';

INSERT IGNORE INTO `papel_permissoes` (`papel_id`, `permissao_id`)
SELECT p.id, pe.id
FROM `papeis` p
INNER JOIN `permissoes` pe ON pe.codigo IN (
  'funcionarios.consultar',
  'funcionarios.editar',
  'funcionarios.dados_sensiveis',
  'funcionarios.desativar',
  'equipas.gerir',
  'turnos.gerir',
  'escalas.gerir',
  'ponto.consultar',
  'ponto.corrigir',
  'ausencias.gerir',
  'justificacoes.validar',
  'ferias.gerir',
  'banco_horas.consultar',
  'banco_horas.ajustar',
  'relatorios.consultar',
  'relatorios.exportar',
  'notificacoes.ver',
  'notificacoes.gerir'
)
WHERE p.slug IN ('recursos-humanos', 'chefia');

DROP PROCEDURE IF EXISTS add_column_if_missing;


-- ========================================================
-- Fonte: database/2026_07_18_registos_ponto_segundo_turno.sql
-- ========================================================

-- Migration: allow second-shift entry/exit movements in registos_ponto.

USE `gestor_assiduidade`;

ALTER TABLE `registos_ponto`
  MODIFY `tipo` ENUM(
    'entrada',
    'saida',
    'inicio_pausa',
    'fim_pausa',
    'entrada_segundo_turno',
    'saida_segundo_turno'
  ) NOT NULL;
