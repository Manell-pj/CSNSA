-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 29-Jul-2026 às 13:17
-- Versão do servidor: 11.8.8-MariaDB-log
-- versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de dados: `u130245564_csnsa`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `banco_horas`
--

CREATE TABLE `banco_horas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `utilizador_id` int(10) UNSIGNED DEFAULT NULL,
  `funcionario_id` int(10) UNSIGNED DEFAULT NULL,
  `data_movimento` date NOT NULL,
  `tipo_movimento` enum('credito','debito','ajuste','compensacao') NOT NULL,
  `minutos` int(11) NOT NULL,
  `origem` enum('ponto','ausencia','manual','importacao') NOT NULL DEFAULT 'manual',
  `manual` tinyint(1) NOT NULL DEFAULT 0,
  `registo_ponto_id` bigint(20) UNSIGNED DEFAULT NULL,
  `pedido_ausencia_id` int(10) UNSIGNED DEFAULT NULL,
  `resumo_diario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `criado_por` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `biometric_commands`
--

CREATE TABLE `biometric_commands` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `operation` enum('upsert_user','delete_user') NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `available_at` datetime NOT NULL DEFAULT current_timestamp(),
  `claimed_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `last_error` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `biometric_commands`
--

INSERT INTO `biometric_commands` (`id`, `operation`, `payload`, `status`, `attempts`, `available_at`, `claimed_at`, `completed_at`, `last_error`, `created_at`, `updated_at`) VALUES
(1, 'upsert_user', '{\"uid\":13,\"name\":\"ibra\",\"pin\":\"1023\"}', 'completed', 1, '2026-07-28 13:47:45', '2026-07-28 13:47:48', '2026-07-28 13:47:48', NULL, '2026-07-28 13:47:45', '2026-07-28 13:47:48'),
(2, 'upsert_user', '{\"uid\":1,\"name\":\"ibraima\",\"pin\":\"1001\"}', 'completed', 1, '2026-07-28 13:51:25', '2026-07-28 13:51:27', '2026-07-28 13:51:29', NULL, '2026-07-28 13:51:25', '2026-07-28 13:51:29'),
(3, 'upsert_user', '{\"uid\":2,\"name\":\"Manel\",\"pin\":\"1002\"}', 'completed', 1, '2026-07-28 13:59:37', '2026-07-28 13:59:39', '2026-07-28 13:59:40', NULL, '2026-07-28 13:59:37', '2026-07-28 13:59:40'),
(4, 'upsert_user', '{\"uid\":1,\"name\":\"ibra\",\"pin\":\"1001\",\"delete_on_failure\":true}', 'pending', 0, '2026-07-29 12:53:37', NULL, NULL, NULL, '2026-07-29 12:53:37', '2026-07-29 12:53:37');

-- --------------------------------------------------------

--
-- Estrutura da tabela `config_horas_extra`
--

CREATE TABLE `config_horas_extra` (
  `chave` varchar(100) NOT NULL,
  `valor` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `config_horas_extra`
--

INSERT INTO `config_horas_extra` (`chave`, `valor`) VALUES
('resolucao_prioridade', 'maior_percentagem');

-- --------------------------------------------------------

--
-- Estrutura da tabela `departamentos`
--

CREATE TABLE `departamentos` (
  `id` int(10) UNSIGNED NOT NULL,
  `setor_id` int(10) UNSIGNED DEFAULT NULL,
  `equipa_id` int(10) UNSIGNED DEFAULT NULL,
  `nome` varchar(120) NOT NULL,
  `codigo` varchar(30) DEFAULT NULL,
  `responsavel_id` int(10) UNSIGNED DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `dispositivos`
--

CREATE TABLE `dispositivos` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `marca` varchar(80) DEFAULT NULL,
  `modelo` varchar(80) DEFAULT NULL,
  `numero_serie` varchar(120) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `porta` int(10) UNSIGNED DEFAULT NULL,
  `localizacao` varchar(160) DEFAULT NULL,
  `tipo` enum('biometrico','rfid','facial','manual','outro') NOT NULL DEFAULT 'biometrico',
  `estado` enum('ativo','inativo','offline','manutencao') NOT NULL DEFAULT 'ativo',
  `ultima_sincronizacao_at` datetime DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `diuturnidades_atribuicoes`
--

CREATE TABLE `diuturnidades_atribuicoes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `funcionario_id` int(10) UNSIGNED NOT NULL,
  `data_base` date NOT NULL,
  `ciclo_anos` smallint(5) UNSIGNED NOT NULL,
  `ciclo_numero` smallint(5) UNSIGNED NOT NULL,
  `data_vencimento` date NOT NULL,
  `confirmado_por` int(10) UNSIGNED DEFAULT NULL,
  `confirmado_at` datetime NOT NULL DEFAULT current_timestamp(),
  `observacoes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `equipas`
--

CREATE TABLE `equipas` (
  `id` int(10) UNSIGNED NOT NULL,
  `setor_id` int(10) UNSIGNED DEFAULT NULL,
  `nome` varchar(140) NOT NULL,
  `codigo` varchar(40) NOT NULL,
  `responsavel_id` int(10) UNSIGNED DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `equipas`
--

INSERT INTO `equipas` (`id`, `setor_id`, `nome`, `codigo`, `responsavel_id`, `descricao`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 1, 'Acao Direta - Equipa Geral', 'ACAO_DIRETA_GERAL', NULL, 'Equipa geral do setor', 1, '2026-07-24 11:31:10', NULL),
(2, 2, 'Servicos Gerais - Equipa Geral', 'SERVICOS_GERAIS_GERAL', NULL, 'Equipa geral do setor', 1, '2026-07-24 11:31:10', NULL),
(3, 3, 'Refeitorio/Copa - Equipa Geral', 'REFEITORIO_COPA_GERAL', NULL, 'Equipa geral do setor', 1, '2026-07-24 11:31:10', NULL),
(4, 4, 'Cozinha - Equipa Geral', 'COZINHA_GERAL', NULL, 'Equipa geral do setor', 1, '2026-07-24 11:31:10', NULL),
(5, 5, 'Lavandaria - Equipa Geral', 'LAVANDARIA_GERAL', NULL, 'Equipa geral do setor', 1, '2026-07-24 11:31:10', NULL),
(6, 6, 'Servicos Tecnicos e Administrativos - Equipa Geral', 'TECNICOS_ADMIN_GERAL', NULL, 'Equipa geral do setor', 1, '2026-07-24 11:31:10', NULL),
(7, 7, 'Motoristas - Equipa Geral', 'MOTORISTAS_GERAL', NULL, 'Equipa geral do setor', 1, '2026-07-24 11:31:10', NULL),
(8, 1, 'equipa teste', '100', NULL, 'equepa de teste', 1, '2026-07-27 11:57:45', '2026-07-27 11:58:05');

-- --------------------------------------------------------

--
-- Estrutura da tabela `equipa_mudancas`
--

CREATE TABLE `equipa_mudancas` (
  `id` int(10) UNSIGNED NOT NULL,
  `funcionario_id` int(10) UNSIGNED NOT NULL,
  `equipa_antiga_id` int(10) UNSIGNED DEFAULT NULL,
  `equipa_nova_id` int(10) UNSIGNED DEFAULT NULL,
  `data_efeito` date NOT NULL,
  `motivo` varchar(1024) DEFAULT NULL,
  `utilizador_responsavel_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `escala_funcionarios`
--

CREATE TABLE `escala_funcionarios` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `funcionario_id` int(10) UNSIGNED NOT NULL,
  `utilizador_id` int(10) UNSIGNED DEFAULT NULL,
  `setor_id` int(10) UNSIGNED DEFAULT NULL,
  `equipa_id` int(10) UNSIGNED DEFAULT NULL,
  `ano` smallint(5) UNSIGNED NOT NULL,
  `mes` tinyint(3) UNSIGNED NOT NULL,
  `data_escala` date NOT NULL,
  `dia` tinyint(3) UNSIGNED NOT NULL,
  `tipo_dia` enum('turno','folga','ferias','falta','baixa','substituicao','licenca_amamentacao') NOT NULL DEFAULT 'turno',
  `turno_id` int(10) UNSIGNED DEFAULT NULL,
  `substitui_funcionario_id` int(10) UNSIGNED DEFAULT NULL,
  `folga_trabalhada` tinyint(1) NOT NULL DEFAULT 0,
  `origem` enum('manual','importacao') NOT NULL DEFAULT 'manual',
  `importado_por` int(10) UNSIGNED DEFAULT NULL,
  `observacoes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estrutura da tabela `escala_mensal`
--

CREATE TABLE `escala_mensal` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ano` smallint(5) UNSIGNED NOT NULL,
  `mes` tinyint(3) UNSIGNED NOT NULL,
  `setor_id` int(10) UNSIGNED DEFAULT NULL,
  `equipa_id` int(10) UNSIGNED DEFAULT NULL,
  `nome` varchar(160) DEFAULT NULL,
  `estado` enum('rascunho','publicada','fechada','cancelada') NOT NULL DEFAULT 'rascunho',
  `publicada_at` datetime DEFAULT NULL,
  `publicada_por` int(10) UNSIGNED DEFAULT NULL,
  `fechada_at` datetime DEFAULT NULL,
  `fechada_por` int(10) UNSIGNED DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estrutura da tabela `escala_mensal_dias`
--

CREATE TABLE `escala_mensal_dias` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `escala_mensal_id` bigint(20) UNSIGNED NOT NULL,
  `funcionario_id` int(10) UNSIGNED DEFAULT NULL,
  `utilizador_id` int(10) UNSIGNED DEFAULT NULL,
  `setor_id` int(10) UNSIGNED DEFAULT NULL,
  `equipa_id` int(10) UNSIGNED DEFAULT NULL,
  `data_escala` date NOT NULL,
  `turno_id` int(10) UNSIGNED DEFAULT NULL,
  `tipo_dia` enum('trabalho','folga','feriado','ferias','ausencia','descanso','formacao') NOT NULL DEFAULT 'trabalho',
  `minutos_previstos` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `observacoes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `escala_periodos`
--

CREATE TABLE `escala_periodos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `funcionario_id` int(10) UNSIGNED NOT NULL,
  `data_escala` date NOT NULL,
  `sequencia` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `turno_id` int(10) UNSIGNED DEFAULT NULL,
  `minutos_previstos` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `observacoes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `ferias_ausencias`
--

CREATE TABLE `ferias_ausencias` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pedido_ausencia_id` int(10) UNSIGNED DEFAULT NULL,
  `funcionario_id` int(10) UNSIGNED DEFAULT NULL,
  `utilizador_id` int(10) UNSIGNED DEFAULT NULL,
  `tipo_ausencia_id` int(10) UNSIGNED NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fim` time DEFAULT NULL,
  `dia_completo` tinyint(1) NOT NULL DEFAULT 1,
  `minutos_justificados` int(10) UNSIGNED DEFAULT NULL,
  `afeta_assiduidade` tinyint(1) NOT NULL DEFAULT 1,
  `desconta_banco_horas` tinyint(1) NOT NULL DEFAULT 0,
  `estado` enum('pendente','aprovado','rejeitado','cancelado') NOT NULL DEFAULT 'pendente',
  `motivo` text DEFAULT NULL,
  `ficheiro_justificativo` varchar(255) DEFAULT NULL,
  `aprovado_por` int(10) UNSIGNED DEFAULT NULL,
  `aprovado_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

--
-- Extraindo dados da tabela `ferias_ausencias`
--

INSERT INTO `ferias_ausencias` (`id`, `pedido_ausencia_id`, `funcionario_id`, `utilizador_id`, `tipo_ausencia_id`, `data_inicio`, `data_fim`, `hora_inicio`, `hora_fim`, `dia_completo`, `minutos_justificados`, `afeta_assiduidade`, `desconta_banco_horas`, `estado`, `motivo`, `ficheiro_justificativo`, `aprovado_por`, `aprovado_at`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 1, 1, '2026-07-20', '2026-07-27', NULL, NULL, 1, NULL, 1, 0, 'aprovado', 'férias', NULL, 1, '2026-07-27 12:02:08', '2026-07-27 12:02:08', NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `funcionarios`
--

CREATE TABLE `funcionarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `utilizador_id` int(10) UNSIGNED DEFAULT NULL,
  `setor_id` int(10) UNSIGNED DEFAULT NULL,
  `equipa_id` int(10) UNSIGNED DEFAULT NULL,
  `entidade` varchar(180) DEFAULT NULL,
  `codigo_picagem` varchar(80) DEFAULT NULL,
  `codigo_picagem_hash` varchar(255) DEFAULT NULL,
  `codigo_picagem_tentativas` int(11) NOT NULL DEFAULT 0,
  `codigo_picagem_bloqueado_ate` datetime DEFAULT NULL,
  `numero_mecanografico` varchar(50) DEFAULT NULL,
  `data_ficha` date DEFAULT NULL,
  `nome` varchar(160) NOT NULL,
  `email` varchar(160) DEFAULT NULL,
  `telefone` varchar(40) DEFAULT NULL,
  `morada` varchar(255) DEFAULT NULL,
  `localidade` varchar(120) DEFAULT NULL,
  `codigo_pais` varchar(20) DEFAULT NULL,
  `codigo_postal` varchar(20) DEFAULT NULL,
  `telemovel` varchar(40) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `doc_identificacao` varchar(80) DEFAULT NULL,
  `data_validade_doc` date DEFAULT NULL,
  `local_emissao` varchar(120) DEFAULT NULL,
  `naturalidade` varchar(120) DEFAULT NULL,
  `codigo_residencia` varchar(40) DEFAULT NULL,
  `genero` varchar(30) DEFAULT NULL,
  `estado_civil` varchar(60) DEFAULT NULL,
  `funcao` varchar(120) DEFAULT NULL,
  `categoria_profissional` varchar(120) DEFAULT NULL,
  `irct` varchar(120) DEFAULT NULL,
  `cct` varchar(120) DEFAULT NULL,
  `nivel_profissional` varchar(80) DEFAULT NULL,
  `defice_percentagem_paga` decimal(6,2) DEFAULT NULL,
  `moeda` varchar(10) DEFAULT NULL,
  `seccao` varchar(120) DEFAULT NULL,
  `local_pagamento` varchar(120) DEFAULT NULL,
  `codigo_subsidio_natal` varchar(40) DEFAULT NULL,
  `codigo_subsidio_ferias` varchar(40) DEFAULT NULL,
  `data_admissao` date DEFAULT NULL,
  `diuturnidade_data_base` date DEFAULT NULL,
  `diuturnidade_ciclo_anos` smallint(5) UNSIGNED DEFAULT NULL,
  `diuturnidade_ativa` tinyint(1) NOT NULL DEFAULT 1,
  `codigo_admissao` varchar(40) DEFAULT NULL,
  `data_cessacao` date DEFAULT NULL,
  `data_inicio_diuturnidade` date DEFAULT NULL,
  `codigo_demissao` varchar(40) DEFAULT NULL,
  `nif` varchar(20) DEFAULT NULL,
  `irs_estado_civil` varchar(60) DEFAULT NULL,
  `conjugue` tinyint(1) NOT NULL DEFAULT 0,
  `nif_conjugue` varchar(20) DEFAULT NULL,
  `residencia_irs` varchar(120) DEFAULT NULL,
  `beneficio_fiscal` varchar(80) DEFAULT NULL,
  `numero_filhos` int(10) UNSIGNED DEFAULT NULL,
  `dependentes_deducao` int(10) UNSIGNED DEFAULT NULL,
  `deficientes_dependentes` int(10) UNSIGNED DEFAULT NULL,
  `titularidade_dependentes` int(10) UNSIGNED DEFAULT NULL,
  `tipo_contrato` varchar(80) DEFAULT NULL,
  `tipo_horario` varchar(80) DEFAULT NULL,
  `carga_horaria_diaria` decimal(5,2) NOT NULL DEFAULT 8.00,
  `tipo_horario_codigo` varchar(40) DEFAULT NULL,
  `tipo_horario_unidade` varchar(40) DEFAULT NULL,
  `tipo_horario_tratamento` varchar(80) DEFAULT NULL,
  `tipo_horario_desc_semanal` varchar(120) DEFAULT NULL,
  `tipo_contrato_id` int(10) UNSIGNED DEFAULT NULL,
  `tipo_contrato_codigo` varchar(40) DEFAULT NULL,
  `tipo_contrato_tipo_rendimento` varchar(120) DEFAULT NULL,
  `tipo_contrato_taxa_irs` decimal(6,2) DEFAULT NULL,
  `carga_horaria_semanal` decimal(5,2) NOT NULL DEFAULT 40.00,
  `horas_mes` decimal(6,2) DEFAULT NULL,
  `salario_mensal` decimal(10,2) DEFAULT NULL,
  `seguranca_social_codigo` varchar(40) DEFAULT NULL,
  `seguranca_social_numero_beneficiario` varchar(80) DEFAULT NULL,
  `relatorio_unico_estabelecimento` varchar(120) DEFAULT NULL,
  `relatorio_unico_habilitacoes` varchar(120) DEFAULT NULL,
  `relatorio_unico_profissao` varchar(120) DEFAULT NULL,
  `relatorio_unico_situacao` varchar(120) DEFAULT NULL,
  `relatorio_unico_nivel` varchar(80) DEFAULT NULL,
  `relatorio_unico_nacionalidade` varchar(80) DEFAULT NULL,
  `relatorio_unico_regime` varchar(80) DEFAULT NULL,
  `carta_conducao_numero` varchar(80) DEFAULT NULL,
  `carta_conducao_categoria_1` varchar(40) DEFAULT NULL,
  `carta_conducao_categoria_2` varchar(40) DEFAULT NULL,
  `carta_conducao_data_inicio` date DEFAULT NULL,
  `carta_conducao_data_validade` date DEFAULT NULL,
  `cursos` text DEFAULT NULL,
  `linguas` text DEFAULT NULL,
  `pin_ponto` varchar(20) DEFAULT NULL,
  `codigo_cartao` varchar(80) DEFAULT NULL,
  `codigo_biometrico` varchar(80) DEFAULT NULL,
  `estado` enum('ativo','suspenso','inativo') NOT NULL DEFAULT 'ativo',
  `motivo_inativacao` varchar(255) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `seguro` varchar(160) DEFAULT NULL,
  `sindicato` varchar(120) DEFAULT NULL,
  `sindicato_numero` varchar(40) DEFAULT NULL,
  `banco` varchar(120) DEFAULT NULL,
  `iban` varchar(60) DEFAULT NULL,
  `conta_empresa` varchar(80) DEFAULT NULL,
  `dados_fixos_codigo` varchar(40) DEFAULT NULL,
  `dados_fixos_designacao` varchar(160) DEFAULT NULL,
  `dados_fixos_quantidade` varchar(80) DEFAULT NULL,
  `dados_fixos_valor` decimal(10,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `funcionarios`
--

INSERT INTO `funcionarios` (`id`, `utilizador_id`, `setor_id`, `equipa_id`, `entidade`, `codigo_picagem`, `codigo_picagem_hash`, `codigo_picagem_tentativas`, `codigo_picagem_bloqueado_ate`, `numero_mecanografico`, `data_ficha`, `nome`, `email`, `telefone`, `morada`, `localidade`, `codigo_pais`, `codigo_postal`, `telemovel`, `data_nascimento`, `doc_identificacao`, `data_validade_doc`, `local_emissao`, `naturalidade`, `codigo_residencia`, `genero`, `estado_civil`, `funcao`, `categoria_profissional`, `irct`, `cct`, `nivel_profissional`, `defice_percentagem_paga`, `moeda`, `seccao`, `local_pagamento`, `codigo_subsidio_natal`, `codigo_subsidio_ferias`, `data_admissao`, `diuturnidade_data_base`, `diuturnidade_ciclo_anos`, `diuturnidade_ativa`, `codigo_admissao`, `data_cessacao`, `data_inicio_diuturnidade`, `codigo_demissao`, `nif`, `irs_estado_civil`, `conjugue`, `nif_conjugue`, `residencia_irs`, `beneficio_fiscal`, `numero_filhos`, `dependentes_deducao`, `deficientes_dependentes`, `titularidade_dependentes`, `tipo_contrato`, `tipo_horario`, `carga_horaria_diaria`, `tipo_horario_codigo`, `tipo_horario_unidade`, `tipo_horario_tratamento`, `tipo_horario_desc_semanal`, `tipo_contrato_id`, `tipo_contrato_codigo`, `tipo_contrato_tipo_rendimento`, `tipo_contrato_taxa_irs`, `carga_horaria_semanal`, `horas_mes`, `salario_mensal`, `seguranca_social_codigo`, `seguranca_social_numero_beneficiario`, `relatorio_unico_estabelecimento`, `relatorio_unico_habilitacoes`, `relatorio_unico_profissao`, `relatorio_unico_situacao`, `relatorio_unico_nivel`, `relatorio_unico_nacionalidade`, `relatorio_unico_regime`, `carta_conducao_numero`, `carta_conducao_categoria_1`, `carta_conducao_categoria_2`, `carta_conducao_data_inicio`, `carta_conducao_data_validade`, `cursos`, `linguas`, `pin_ponto`, `codigo_cartao`, `codigo_biometrico`, `estado`, `motivo_inativacao`, `observacoes`, `seguro`, `sindicato`, `sindicato_numero`, `banco`, `iban`, `conta_empresa`, `dados_fixos_codigo`, `dados_fixos_designacao`, `dados_fixos_quantidade`, `dados_fixos_valor`, `created_at`, `updated_at`) VALUES
(1, NULL, NULL, NULL, 'Centro Social Nossa Senhora Auxiliador', NULL, NULL, 0, NULL, '1001', '2026-07-29', 'ibra', NULL, NULL, 'dasda', 'sasd', NULL, '2222-222', NULL, '1233-03-12', '2342', '2026-07-29', NULL, '123', NULL, 'Masculino', 'Solteiro', NULL, '23', NULL, NULL, '23', 23.00, 'EUR', NULL, NULL, NULL, NULL, '2026-07-29', NULL, NULL, 1, 'qweq', NULL, NULL, NULL, 'qweqw', 'Separado', 0, NULL, NULL, 'qweqw', 12, 12, NULL, 2323, NULL, NULL, 8.00, '2323', '232', '23', NULL, NULL, NULL, NULL, NULL, 40.00, 23.00, NULL, '23', NULL, NULL, NULL, NULL, '23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ativo', NULL, NULL, '23', NULL, NULL, '23', '23', '23', NULL, NULL, NULL, NULL, '2026-07-29 12:53:37', NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `funcionario_tipos_contrato`
--

CREATE TABLE `funcionario_tipos_contrato` (
  `id` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(40) DEFAULT NULL,
  `nome` varchar(120) NOT NULL,
  `tipo_rendimento` varchar(120) DEFAULT NULL,
  `taxa_irs` decimal(6,2) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `funcionario_tipos_contrato`
--

INSERT INTO `funcionario_tipos_contrato` (`id`, `codigo`, `nome`, `tipo_rendimento`, `taxa_irs`, `ativo`, `created_at`) VALUES
(1, '1', 'TRABALHO DEPENDENTE', 'A - TRABALHO DEPENDENTE', NULL, 1, '2026-07-24 11:31:10'),
(49, '02', 'Trabalho independenete', '20', NULL, 1, '2026-07-27 11:49:23');

-- --------------------------------------------------------

--
-- Estrutura da tabela `horarios_turno`
--

CREATE TABLE `horarios_turno` (
  `id` int(10) UNSIGNED NOT NULL,
  `utilizador_id` int(10) UNSIGNED DEFAULT NULL,
  `funcionario_id` int(10) UNSIGNED DEFAULT NULL,
  `turno_id` int(10) UNSIGNED NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `dia_semana` tinyint(3) UNSIGNED DEFAULT NULL COMMENT '1=segunda, 7=domingo; NULL=todos os dias no periodo',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estrutura da tabela `horas_extra_regras`
--

CREATE TABLE `horas_extra_regras` (
  `id` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(100) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `porcentagem` int(11) NOT NULL COMMENT 'Percentagem a aplicar (ex: 150 para 150%)',
  `prioridade` int(11) NOT NULL DEFAULT 0,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `horas_extra_regras`
--

INSERT INTO `horas_extra_regras` (`id`, `codigo`, `nome`, `porcentagem`, `prioridade`, `data_inicio`, `data_fim`, `ativo`, `criado_em`) VALUES
(1, 'noturno', 'Trabalho noturno (22:00-07:00)', 200, 200, '2020-01-01', NULL, 1, '2026-07-24 11:31:11'),
(2, 'segundo_turno_primeira_hora', 'Primeira hora extra no 2o turno', 150, 100, '2020-01-01', NULL, 1, '2026-07-24 11:31:11'),
(3, 'segundo_turno_subsequente', 'Horas extra subsequentes no 2o turno', 175, 90, '2020-01-01', NULL, 1, '2026-07-24 11:31:11');

-- --------------------------------------------------------

--
-- Estrutura da tabela `logs_sistema`
--

CREATE TABLE `logs_sistema` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `utilizador_id` int(10) UNSIGNED DEFAULT NULL,
  `acao` varchar(120) NOT NULL,
  `modulo` varchar(80) DEFAULT NULL,
  `tabela` varchar(80) DEFAULT NULL,
  `registo_id` bigint(20) UNSIGNED DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `dados_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_anteriores`)),
  `dados_novos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_novos`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `logs_sistema`
--

INSERT INTO `logs_sistema` (`id`, `utilizador_id`, `acao`, `modulo`, `tabela`, `registo_id`, `descricao`, `ip`, `user_agent`, `dados_anteriores`, `dados_novos`, `created_at`) VALUES
(1, NULL, 'acesso_negado', 'permissoes', NULL, NULL, 'Permissão exigida: ponto.consultar', '87.196.73.238', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', NULL, NULL, '2026-07-27 12:09:54'),
(2, NULL, 'acesso_negado', 'permissoes', NULL, NULL, 'Permissão exigida: ponto.consultar', '87.196.73.238', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', NULL, NULL, '2026-07-27 12:10:05'),
(3, NULL, 'acesso_negado', 'permissoes', NULL, NULL, 'Permissão exigida: ponto.consultar', '87.196.73.238', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', NULL, NULL, '2026-07-27 12:10:11'),
(4, NULL, 'acesso_negado', 'permissoes', NULL, NULL, 'Permissão exigida: utilizadores.gerir', '87.196.73.238', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', NULL, NULL, '2026-07-27 12:10:12'),
(5, NULL, 'acesso_negado', 'permissoes', NULL, NULL, 'Permissão exigida: logs.consultar', '87.196.73.238', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', NULL, NULL, '2026-07-27 12:10:12'),
(6, NULL, 'acesso_negado', 'permissoes', NULL, NULL, 'Permissão exigida: permissoes.gerir', '87.196.73.238', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', NULL, NULL, '2026-07-27 12:10:13');

-- --------------------------------------------------------

--
-- Estrutura da tabela `notificacao_destinatarios`
--

CREATE TABLE `notificacao_destinatarios` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `notificacao_id` bigint(20) UNSIGNED NOT NULL,
  `utilizador_id` int(10) UNSIGNED NOT NULL,
  `lida_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `notificacoes`
--

CREATE TABLE `notificacoes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tipo` enum('aniversario','diuturnidade') NOT NULL,
  `funcionario_id` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `mensagem` text NOT NULL,
  `data_evento` date NOT NULL,
  `referencia_ano` smallint(5) UNSIGNED NOT NULL,
  `ciclo_numero` smallint(5) UNSIGNED DEFAULT NULL,
  `permissao_codigo` varchar(120) NOT NULL DEFAULT 'notificacoes.ver',
  `estado` enum('ativa','resolvida','ocultada') NOT NULL DEFAULT 'ativa',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `notificacoes_config`
--

CREATE TABLE `notificacoes_config` (
  `chave` varchar(80) NOT NULL,
  `valor` varchar(255) NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `notificacoes_config`
--

INSERT INTO `notificacoes_config` (`chave`, `valor`, `updated_at`) VALUES
('aniversarios_dias_aviso', '30', NULL),
('diuturnidades_ciclo_anos', '5', NULL),
('diuturnidades_dias_aviso', '60', NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `papeis`
--

CREATE TABLE `papeis` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `papeis`
--

INSERT INTO `papeis` (`id`, `nome`, `slug`, `descricao`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 'Administrador', 'administrador', 'Acesso total ao sistema', 1, '2026-07-24 11:31:10', NULL),
(2, 'Recursos Humanos', 'recursos-humanos', 'Gestão de colaboradores, assiduidade e ausências', 1, '2026-07-24 11:31:10', NULL),
(3, 'Chefia', 'chefia', 'Consulta e aprovação da própria equipa', 1, '2026-07-24 11:31:10', NULL),
(4, 'Colaborador', 'colaborador', 'Acesso ao próprio perfil, ponto e pedidos', 1, '2026-07-24 11:31:10', NULL),
(5, 'Secretaria', 'secretaria', 'Operação administrativa corrente', 1, '2026-07-24 11:31:11', NULL),
(6, 'Direção', 'direcao', 'Consulta e validação de gestão', 1, '2026-07-24 11:31:11', NULL),
(7, 'Consulta/Auditoria', 'consulta-auditoria', 'Consulta, auditoria e relatórios', 1, '2026-07-24 11:31:11', NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `papel_permissoes`
--

CREATE TABLE `papel_permissoes` (
  `id` int(10) UNSIGNED NOT NULL,
  `papel_id` int(10) UNSIGNED NOT NULL,
  `permissao_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `papel_permissoes`
--

INSERT INTO `papel_permissoes` (`id`, `papel_id`, `permissao_id`, `created_at`) VALUES
(1, 1, 3, '2026-07-24 11:31:11'),
(2, 2, 3, '2026-07-24 11:31:11'),
(3, 1, 2, '2026-07-24 11:31:11'),
(4, 2, 2, '2026-07-24 11:31:11'),
(5, 1, 1, '2026-07-24 11:31:11'),
(6, 2, 1, '2026-07-24 11:31:11'),
(8, 3, 1, '2026-07-24 11:31:11'),
(9, 1, 15, '2026-07-24 11:31:11'),
(10, 1, 19, '2026-07-24 11:31:11'),
(11, 1, 18, '2026-07-24 11:31:11'),
(12, 1, 8, '2026-07-24 11:31:11'),
(13, 1, 10, '2026-07-24 11:31:11'),
(14, 1, 11, '2026-07-24 11:31:11'),
(15, 1, 17, '2026-07-24 11:31:11'),
(16, 1, 4, '2026-07-24 11:31:11'),
(17, 1, 6, '2026-07-24 11:31:11'),
(18, 1, 7, '2026-07-24 11:31:11'),
(19, 1, 5, '2026-07-24 11:31:11'),
(20, 1, 16, '2026-07-24 11:31:11'),
(22, 1, 14, '2026-07-24 11:31:11'),
(23, 1, 23, '2026-07-24 11:31:11'),
(24, 1, 12, '2026-07-24 11:31:11'),
(25, 1, 13, '2026-07-24 11:31:11'),
(26, 1, 20, '2026-07-24 11:31:11'),
(27, 1, 21, '2026-07-24 11:31:11'),
(28, 1, 9, '2026-07-24 11:31:11'),
(29, 1, 22, '2026-07-24 11:31:11'),
(40, 5, 15, '2026-07-24 11:31:11'),
(41, 5, 19, '2026-07-24 11:31:11'),
(42, 5, 18, '2026-07-24 11:31:11'),
(43, 5, 8, '2026-07-24 11:31:11'),
(44, 5, 10, '2026-07-24 11:31:11'),
(45, 5, 11, '2026-07-24 11:31:11'),
(46, 5, 17, '2026-07-24 11:31:11'),
(47, 5, 4, '2026-07-24 11:31:11'),
(48, 5, 6, '2026-07-24 11:31:11'),
(49, 5, 5, '2026-07-24 11:31:11'),
(50, 5, 16, '2026-07-24 11:31:11'),
(51, 5, 1, '2026-07-24 11:31:11'),
(52, 5, 12, '2026-07-24 11:31:11'),
(53, 5, 13, '2026-07-24 11:31:11'),
(54, 5, 20, '2026-07-24 11:31:11'),
(55, 5, 21, '2026-07-24 11:31:11'),
(56, 5, 9, '2026-07-24 11:31:11'),
(71, 6, 15, '2026-07-24 11:31:11'),
(72, 6, 18, '2026-07-24 11:31:11'),
(73, 6, 8, '2026-07-24 11:31:11'),
(74, 6, 10, '2026-07-24 11:31:11'),
(75, 6, 17, '2026-07-24 11:31:11'),
(76, 6, 4, '2026-07-24 11:31:11'),
(77, 6, 6, '2026-07-24 11:31:11'),
(78, 6, 7, '2026-07-24 11:31:11'),
(79, 6, 16, '2026-07-24 11:31:11'),
(81, 6, 2, '2026-07-24 11:31:11'),
(82, 6, 1, '2026-07-24 11:31:11'),
(83, 6, 14, '2026-07-24 11:31:11'),
(84, 6, 12, '2026-07-24 11:31:11'),
(85, 6, 20, '2026-07-24 11:31:11'),
(86, 6, 21, '2026-07-24 11:31:11'),
(87, 6, 9, '2026-07-24 11:31:11'),
(102, 7, 18, '2026-07-24 11:31:11'),
(103, 7, 4, '2026-07-24 11:31:11'),
(105, 7, 1, '2026-07-24 11:31:11'),
(106, 7, 12, '2026-07-24 11:31:11'),
(107, 7, 20, '2026-07-24 11:31:11'),
(108, 7, 21, '2026-07-24 11:31:11'),
(109, 3, 15, '2026-07-24 11:31:11'),
(110, 2, 15, '2026-07-24 11:31:11'),
(111, 3, 19, '2026-07-24 11:31:11'),
(112, 2, 19, '2026-07-24 11:31:11'),
(113, 3, 18, '2026-07-24 11:31:11'),
(114, 2, 18, '2026-07-24 11:31:11'),
(115, 3, 8, '2026-07-24 11:31:11'),
(116, 2, 8, '2026-07-24 11:31:11'),
(117, 3, 10, '2026-07-24 11:31:11'),
(118, 2, 10, '2026-07-24 11:31:11'),
(119, 3, 17, '2026-07-24 11:31:11'),
(120, 2, 17, '2026-07-24 11:31:11'),
(121, 3, 4, '2026-07-24 11:31:11'),
(122, 2, 4, '2026-07-24 11:31:11'),
(123, 3, 6, '2026-07-24 11:31:11'),
(124, 2, 6, '2026-07-24 11:31:11'),
(125, 3, 7, '2026-07-24 11:31:11'),
(126, 2, 7, '2026-07-24 11:31:11'),
(127, 3, 5, '2026-07-24 11:31:11'),
(128, 2, 5, '2026-07-24 11:31:11'),
(129, 3, 16, '2026-07-24 11:31:11'),
(130, 2, 16, '2026-07-24 11:31:11'),
(131, 3, 2, '2026-07-24 11:31:11'),
(132, 3, 12, '2026-07-24 11:31:11'),
(133, 2, 12, '2026-07-24 11:31:11'),
(134, 3, 13, '2026-07-24 11:31:11'),
(135, 2, 13, '2026-07-24 11:31:11'),
(136, 3, 20, '2026-07-24 11:31:11'),
(137, 2, 20, '2026-07-24 11:31:11'),
(138, 3, 21, '2026-07-24 11:31:11'),
(139, 2, 21, '2026-07-24 11:31:11'),
(140, 3, 9, '2026-07-24 11:31:11'),
(141, 2, 9, '2026-07-24 11:31:11');

-- --------------------------------------------------------

--
-- Estrutura da tabela `pedidos_ausencia`
--

CREATE TABLE `pedidos_ausencia` (
  `id` int(10) UNSIGNED NOT NULL,
  `utilizador_id` int(10) UNSIGNED NOT NULL,
  `funcionario_id` int(10) UNSIGNED DEFAULT NULL,
  `tipo_ausencia_id` int(10) UNSIGNED NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fim` time DEFAULT NULL,
  `total_dias` decimal(6,2) DEFAULT NULL,
  `total_horas` decimal(6,2) DEFAULT NULL,
  `motivo` text DEFAULT NULL,
  `ficheiro_justificativo` varchar(255) DEFAULT NULL,
  `estado` enum('pendente','aprovado','rejeitado','cancelado') NOT NULL DEFAULT 'pendente',
  `aprovado_por` int(10) UNSIGNED DEFAULT NULL,
  `aprovado_at` datetime DEFAULT NULL,
  `observacoes_aprovacao` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

--
-- Extraindo dados da tabela `pedidos_ausencia`
--

INSERT INTO `pedidos_ausencia` (`id`, `utilizador_id`, `funcionario_id`, `tipo_ausencia_id`, `data_inicio`, `data_fim`, `hora_inicio`, `hora_fim`, `total_dias`, `total_horas`, `motivo`, `ficheiro_justificativo`, `estado`, `aprovado_por`, `aprovado_at`, `observacoes_aprovacao`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 1, '2026-07-20', '2026-07-27', NULL, NULL, 8.00, NULL, 'férias', NULL, 'aprovado', 1, '2026-07-27 12:02:08', '', '2026-07-27 12:01:43', '2026-07-27 12:02:08');

-- --------------------------------------------------------

--
-- Estrutura da tabela `permissoes`
--

CREATE TABLE `permissoes` (
  `id` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(120) NOT NULL,
  `nome` varchar(160) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `permissoes`
--

INSERT INTO `permissoes` (`id`, `codigo`, `nome`, `descricao`, `created_at`) VALUES
(1, 'notificacoes.ver', 'Ver notificações', 'Ver notificações de aniversário e diuturnidade', '2026-07-24 11:31:11'),
(2, 'notificacoes.gerir', 'Gerir notificações', 'Configurar notificações e confirmar diuturnidades', '2026-07-24 11:31:11'),
(3, 'funcionarios.ver_idade', 'Ver idade dos funcionários', 'Ver idade dos funcionários nas notificações', '2026-07-24 11:31:11'),
(4, 'funcionarios.consultar', 'Consultar funcionários', 'Aceder a listagens e detalhe de funcionários', '2026-07-24 11:31:11'),
(5, 'funcionarios.editar', 'Editar funcionários', 'Criar e alterar dados de funcionários', '2026-07-24 11:31:11'),
(6, 'funcionarios.dados_sensiveis', 'Consultar dados pessoais sensíveis', 'Ver dados pessoais sensíveis dos funcionários', '2026-07-24 11:31:11'),
(7, 'funcionarios.desativar', 'Desativar funcionários', 'Desativar ou reativar funcionários', '2026-07-24 11:31:11'),
(8, 'equipas.gerir', 'Gerir equipas', 'Criar, editar e remover equipas', '2026-07-24 11:31:11'),
(9, 'turnos.gerir', 'Gerir turnos', 'Criar, editar, remover e associar turnos', '2026-07-24 11:31:11'),
(10, 'escalas.gerir', 'Gerir escalas', 'Consultar e editar escalas mensais', '2026-07-24 11:31:11'),
(11, 'escalas.importar', 'Importar escalas', 'Importar escalas externas', '2026-07-24 11:31:11'),
(12, 'ponto.consultar', 'Consultar ponto', 'Consultar registos de ponto', '2026-07-24 11:31:11'),
(13, 'ponto.corrigir', 'Corrigir ponto', 'Registar ou corrigir movimentos de ponto', '2026-07-24 11:31:11'),
(14, 'ocorrencias.validar', 'Validar ocorrências', 'Validar ocorrências operacionais', '2026-07-24 11:31:11'),
(15, 'ausencias.gerir', 'Gerir ausências', 'Criar e gerir pedidos de ausência', '2026-07-24 11:31:11'),
(16, 'justificacoes.validar', 'Validar justificações', 'Aprovar ou recusar justificações', '2026-07-24 11:31:11'),
(17, 'ferias.gerir', 'Gerir férias', 'Gerir pedidos e marcações de férias', '2026-07-24 11:31:11'),
(18, 'banco_horas.consultar', 'Consultar banco de horas', 'Consultar saldos e movimentos do banco de horas', '2026-07-24 11:31:11'),
(19, 'banco_horas.ajustar', 'Ajustar banco de horas', 'Criar ajustes manuais no banco de horas', '2026-07-24 11:31:11'),
(20, 'relatorios.consultar', 'Consultar relatórios', 'Consultar relatórios', '2026-07-24 11:31:11'),
(21, 'relatorios.exportar', 'Exportar relatórios', 'Exportar relatórios', '2026-07-24 11:31:11'),
(22, 'utilizadores.gerir', 'Gerir utilizadores', 'Criar, editar e remover utilizadores', '2026-07-24 11:31:11'),
(23, 'permissoes.gerir', 'Gerir permissões', 'Gerir permissões por utilizador', '2026-07-24 11:31:11'),
(27, 'ausencias.pedir', 'Pedir ausências', 'Criar pedidos de ausência próprios', '2026-07-27 20:58:13');

-- --------------------------------------------------------

--
-- Estrutura da tabela `registos_ponto`
--

CREATE TABLE `registos_ponto` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `utilizador_id` int(10) UNSIGNED DEFAULT NULL,
  `funcionario_id` int(10) UNSIGNED DEFAULT NULL,
  `dispositivo_id` int(10) UNSIGNED DEFAULT NULL,
  `escala_mensal_dia_id` bigint(20) UNSIGNED DEFAULT NULL,
  `turno_periodo_id` int(10) UNSIGNED DEFAULT NULL,
  `tipo` enum('entrada','saida','inicio_pausa','fim_pausa','entrada_segundo_turno','saida_segundo_turno') NOT NULL,
  `data_hora` datetime NOT NULL,
  `data_referencia` date DEFAULT NULL,
  `data_hora_prevista` datetime DEFAULT NULL,
  `dentro_tolerancia` tinyint(1) DEFAULT NULL,
  `minutos_desvio` int(11) DEFAULT NULL,
  `origem` enum('manual','dispositivo','importacao','api') NOT NULL DEFAULT 'manual',
  `registo_manual` tinyint(1) NOT NULL DEFAULT 0,
  `estado` enum('valido','pendente','corrigido','rejeitado','duplicado','anulado') NOT NULL DEFAULT 'valido',
  `observacoes` varchar(255) DEFAULT NULL,
  `motivo_correcao` varchar(255) DEFAULT NULL,
  `criado_por` int(10) UNSIGNED DEFAULT NULL,
  `atualizado_por` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `registos_ponto_logs`
--

CREATE TABLE `registos_ponto_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `registo_ponto_id` bigint(20) UNSIGNED NOT NULL,
  `operacao` enum('insercao','correcao','remocao','importacao') NOT NULL,
  `dados_antigos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_antigos`)),
  `dados_novos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_novos`)),
  `utilizador_id` int(10) UNSIGNED DEFAULT NULL,
  `motivo` varchar(1024) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `relatorios_mensais`
--

CREATE TABLE `relatorios_mensais` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ano` smallint(5) UNSIGNED NOT NULL,
  `mes` tinyint(3) UNSIGNED NOT NULL,
  `tipo` enum('funcionario','equipa','setor') NOT NULL,
  `funcionario_id` int(10) UNSIGNED DEFAULT NULL,
  `equipa_id` int(10) UNSIGNED DEFAULT NULL,
  `setor_id` int(10) UNSIGNED DEFAULT NULL,
  `estado` enum('rascunho','calculado','validado','fechado') NOT NULL DEFAULT 'rascunho',
  `dias_previstos` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `dias_trabalhados` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `dias_ferias` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `dias_ausencia` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `minutos_previstos` int(11) NOT NULL DEFAULT 0,
  `minutos_trabalhados` int(11) NOT NULL DEFAULT 0,
  `minutos_ausencia_justificada` int(11) NOT NULL DEFAULT 0,
  `minutos_atraso` int(11) NOT NULL DEFAULT 0,
  `minutos_saida_antecipada` int(11) NOT NULL DEFAULT 0,
  `minutos_extra` int(11) NOT NULL DEFAULT 0,
  `minutos_saldo` int(11) NOT NULL DEFAULT 0,
  `gerado_por` int(10) UNSIGNED DEFAULT NULL,
  `gerado_at` datetime DEFAULT NULL,
  `validado_por` int(10) UNSIGNED DEFAULT NULL,
  `validado_at` datetime DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estrutura da tabela `relatorio_mensal_linhas`
--

CREATE TABLE `relatorio_mensal_linhas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `relatorio_mensal_id` bigint(20) UNSIGNED NOT NULL,
  `resumo_diario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `funcionario_id` int(10) UNSIGNED DEFAULT NULL,
  `data` date NOT NULL,
  `estado` varchar(40) NOT NULL,
  `minutos_previstos` int(11) NOT NULL DEFAULT 0,
  `minutos_trabalhados` int(11) NOT NULL DEFAULT 0,
  `minutos_saldo` int(11) NOT NULL DEFAULT 0,
  `observacoes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `resumo_diario_assiduidade`
--

CREATE TABLE `resumo_diario_assiduidade` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `funcionario_id` int(10) UNSIGNED DEFAULT NULL,
  `utilizador_id` int(10) UNSIGNED DEFAULT NULL,
  `setor_id` int(10) UNSIGNED DEFAULT NULL,
  `equipa_id` int(10) UNSIGNED DEFAULT NULL,
  `data` date NOT NULL,
  `escala_mensal_dia_id` bigint(20) UNSIGNED DEFAULT NULL,
  `turno_id` int(10) UNSIGNED DEFAULT NULL,
  `minutos_previstos` int(11) NOT NULL DEFAULT 0,
  `minutos_trabalhados` int(11) NOT NULL DEFAULT 0,
  `horas_previstas` decimal(6,2) NOT NULL DEFAULT 0.00,
  `horas_realizadas` decimal(6,2) NOT NULL DEFAULT 0.00,
  `minutos_ausencia_justificada` int(11) NOT NULL DEFAULT 0,
  `minutos_atraso` int(11) NOT NULL DEFAULT 0,
  `minutos_saida_antecipada` int(11) NOT NULL DEFAULT 0,
  `minutos_extra` int(11) NOT NULL DEFAULT 0,
  `minutos_saldo` int(11) NOT NULL DEFAULT 0,
  `dentro_tolerancia` tinyint(1) NOT NULL DEFAULT 1,
  `estado` enum('previsto','presente','ausente','ferias','folga','feriado','incompleto','corrigido','sem_escala') NOT NULL DEFAULT 'previsto',
  `entrada_prevista` datetime DEFAULT NULL,
  `saida_prevista` datetime DEFAULT NULL,
  `entrada_real` datetime DEFAULT NULL,
  `saida_real` datetime DEFAULT NULL,
  `falta` tinyint(1) NOT NULL DEFAULT 0,
  `folga_trabalhada` tinyint(1) NOT NULL DEFAULT 0,
  `substituicao` tinyint(1) NOT NULL DEFAULT 0,
  `substitui_funcionario_id` int(10) UNSIGNED DEFAULT NULL,
  `licenca_amamentacao` tinyint(1) NOT NULL DEFAULT 0,
  `primeira_entrada` datetime DEFAULT NULL,
  `ultima_saida` datetime DEFAULT NULL,
  `observacoes` varchar(255) DEFAULT NULL,
  `calculado_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `setores`
--

CREATE TABLE `setores` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(140) NOT NULL,
  `codigo` varchar(40) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `responsavel_id` int(10) UNSIGNED DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `setores`
--

INSERT INTO `setores` (`id`, `nome`, `codigo`, `descricao`, `responsavel_id`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 'Acao Direta', 'ACAO_DIRETA', 'Prestacao direta de cuidados aos utentes', NULL, 1, '2026-07-24 11:31:10', NULL),
(2, 'Servicos Gerais', 'SERVICOS_GERAIS', 'Servicos gerais de apoio', NULL, 1, '2026-07-24 11:31:10', NULL),
(3, 'Refeitorio/Copa', 'REFEITORIO_COPA', 'Refeitorio, copa e apoio a refeicoes', NULL, 1, '2026-07-24 11:31:10', NULL),
(4, 'Cozinha', 'COZINHA', 'Preparacao e confeccao alimentar', NULL, 1, '2026-07-24 11:31:10', NULL),
(5, 'Lavandaria', 'LAVANDARIA', 'Tratamento de roupa e lavandaria', NULL, 1, '2026-07-24 11:31:10', NULL),
(6, 'Servicos Tecnicos e Administrativos', 'TECNICOS_ADMIN', 'Secretaria, direcao tecnica e servicos administrativos', NULL, 1, '2026-07-24 11:31:10', NULL),
(7, 'Motoristas', 'MOTORISTAS', 'Transporte de utentes, pessoal e servicos externos', NULL, 1, '2026-07-24 11:31:10', NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `tipos_ausencia`
--

CREATE TABLE `tipos_ausencia` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `remunerada` tinyint(1) NOT NULL DEFAULT 1,
  `desconta_ferias` tinyint(1) NOT NULL DEFAULT 0,
  `exige_justificativo` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `tipos_ausencia`
--

INSERT INTO `tipos_ausencia` (`id`, `nome`, `slug`, `descricao`, `remunerada`, `desconta_ferias`, `exige_justificativo`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 'Férias', 'ferias', 'Período de férias aprovado', 1, 1, 0, 1, '2026-07-24 11:31:10', NULL),
(2, 'Falta Justificada', 'falta-justificada', 'Ausência com justificação aceite', 1, 0, 1, 1, '2026-07-24 11:31:10', NULL),
(3, 'Falta Injustificada', 'falta-injustificada', 'Ausência sem justificação aceite', 0, 0, 0, 1, '2026-07-24 11:31:10', NULL),
(4, 'Baixa Médica', 'baixa-medica', 'Ausência por motivo de saúde', 1, 0, 1, 1, '2026-07-24 11:31:10', NULL),
(5, 'Licença', 'licenca', 'Licença autorizada', 1, 0, 1, 1, '2026-07-24 11:31:10', NULL),
(6, 'Formação', 'formacao', 'Ausência por formação', 1, 0, 0, 1, '2026-07-24 11:31:10', NULL),
(7, 'Folga', 'folga', 'Pedido de folga', 1, 0, 0, 1, '2026-07-24 13:01:50', NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `turnos`
--

CREATE TABLE `turnos` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `codigo` varchar(40) DEFAULT NULL,
  `equipa_id` int(10) UNSIGNED DEFAULT NULL,
  `funcionario_id` int(10) UNSIGNED DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `hora_entrada` time NOT NULL,
  `hora_saida` time NOT NULL,
  `inicio_pausa` time DEFAULT NULL,
  `fim_pausa` time DEFAULT NULL,
  `tolerancia_entrada_min` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `tolerancia_saida_min` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `tolerancia_antes_min` smallint(5) UNSIGNED NOT NULL DEFAULT 15,
  `tolerancia_depois_min` smallint(5) UNSIGNED NOT NULL DEFAULT 15,
  `carga_diaria` decimal(5,2) NOT NULL DEFAULT 8.00,
  `horas_previstas` decimal(5,2) NOT NULL DEFAULT 8.00,
  `total_periodos` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `permite_multiplos_periodos` tinyint(1) NOT NULL DEFAULT 0,
  `turno_noturno` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `turnos`
--

INSERT INTO `turnos` (`id`, `nome`, `codigo`, `equipa_id`, `funcionario_id`, `descricao`, `hora_entrada`, `hora_saida`, `inicio_pausa`, `fim_pausa`, `tolerancia_entrada_min`, `tolerancia_saida_min`, `tolerancia_antes_min`, `tolerancia_depois_min`, `carga_diaria`, `horas_previstas`, `total_periodos`, `permite_multiplos_periodos`, `turno_noturno`, `ativo`, `created_at`, `updated_at`) VALUES
(1, '00:00-08:00', 'T_0000_0800', NULL, NULL, 'Turno noturno/madrugada', '00:00:00', '08:00:00', NULL, NULL, 15, 15, 15, 15, 8.00, 8.00, 1, 0, 1, 1, '2026-07-24 11:31:10', NULL),
(2, '08:00-16:00', 'T_0800_1600', NULL, NULL, 'Turno da manha/tarde', '08:00:00', '16:00:00', NULL, NULL, 15, 15, 15, 15, 8.00, 8.00, 1, 0, 0, 1, '2026-07-24 11:31:10', NULL),
(3, '16:00-00:00', 'T_1600_0000', NULL, NULL, 'Turno da tarde/noite', '16:00:00', '00:00:00', NULL, NULL, 15, 15, 15, 15, 8.00, 8.00, 1, 0, 1, 1, '2026-07-24 11:31:10', NULL),
(4, '08:30-16:30', 'T_0830_1630', NULL, NULL, 'Turno administrativo', '08:30:00', '16:30:00', NULL, NULL, 15, 15, 15, 15, 8.00, 8.00, 1, 0, 0, 1, '2026-07-24 11:31:10', NULL),
(5, '08:00-13:00 e 17:00-20:00', 'T_0800_1300_1700_2000', NULL, NULL, 'Turno repartido', '08:00:00', '20:00:00', NULL, NULL, 15, 15, 15, 15, 8.00, 8.00, 2, 1, 0, 1, '2026-07-24 11:31:10', NULL),
(6, '08:00-14:00', 'T_0800_1400', NULL, NULL, 'Turno parcial de 6 horas', '08:00:00', '14:00:00', NULL, NULL, 15, 15, 15, 15, 8.00, 6.00, 1, 0, 0, 1, '2026-07-24 11:31:10', NULL),
(7, '08:00-12:00 e 18:00-20:00', 'T_0800_1200_1800_2000', NULL, NULL, 'Turno repartido parcial', '08:00:00', '20:00:00', NULL, NULL, 15, 15, 15, 15, 8.00, 6.00, 2, 1, 0, 1, '2026-07-24 11:31:10', NULL),
(8, '12:00-20:00', 'T_1200_2000', NULL, NULL, 'Turno tarde', '12:00:00', '20:00:00', NULL, NULL, 15, 15, 15, 15, 8.00, 8.00, 1, 0, 0, 1, '2026-07-24 11:31:10', NULL),
(9, '09:00-12:30 e 14:00-17:30', 'T_0900_1230_1400_1730', NULL, NULL, 'Turno administrativo repartido', '09:00:00', '17:30:00', NULL, NULL, 15, 15, 15, 15, 8.00, 7.00, 2, 1, 0, 1, '2026-07-24 11:31:10', NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `turno_periodos`
--

CREATE TABLE `turno_periodos` (
  `id` int(10) UNSIGNED NOT NULL,
  `turno_id` int(10) UNSIGNED NOT NULL,
  `sequencia` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `hora_inicio` time NOT NULL,
  `hora_fim` time NOT NULL,
  `cruza_dia` tinyint(1) NOT NULL DEFAULT 0,
  `tolerancia_antes_min` smallint(5) UNSIGNED NOT NULL DEFAULT 15,
  `tolerancia_depois_min` smallint(5) UNSIGNED NOT NULL DEFAULT 15,
  `minutos_previstos` smallint(5) UNSIGNED NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

--
-- Extraindo dados da tabela `turno_periodos`
--

INSERT INTO `turno_periodos` (`id`, `turno_id`, `sequencia`, `hora_inicio`, `hora_fim`, `cruza_dia`, `tolerancia_antes_min`, `tolerancia_depois_min`, `minutos_previstos`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '00:00:00', '08:00:00', 0, 15, 15, 480, 1, '2026-07-24 11:31:10', NULL),
(2, 2, 1, '08:00:00', '16:00:00', 0, 15, 15, 480, 1, '2026-07-24 11:31:10', NULL),
(3, 3, 1, '16:00:00', '00:00:00', 1, 15, 15, 480, 1, '2026-07-24 11:31:10', NULL),
(4, 4, 1, '08:30:00', '16:30:00', 0, 15, 15, 480, 1, '2026-07-24 11:31:10', NULL),
(5, 5, 1, '08:00:00', '13:00:00', 0, 15, 15, 300, 1, '2026-07-24 11:31:10', NULL),
(6, 5, 2, '17:00:00', '20:00:00', 0, 15, 15, 180, 1, '2026-07-24 11:31:10', NULL),
(7, 6, 1, '08:00:00', '14:00:00', 0, 15, 15, 360, 1, '2026-07-24 11:31:10', NULL),
(8, 7, 1, '08:00:00', '12:00:00', 0, 15, 15, 240, 1, '2026-07-24 11:31:10', NULL),
(9, 7, 2, '18:00:00', '20:00:00', 0, 15, 15, 120, 1, '2026-07-24 11:31:10', NULL),
(10, 8, 1, '12:00:00', '20:00:00', 0, 15, 15, 480, 1, '2026-07-24 11:31:10', NULL),
(11, 9, 1, '09:00:00', '12:30:00', 0, 15, 15, 210, 1, '2026-07-24 11:31:10', NULL),
(12, 9, 2, '14:00:00', '17:30:00', 0, 15, 15, 210, 1, '2026-07-24 11:31:10', NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `utilizadores`
--

CREATE TABLE `utilizadores` (
  `id` int(10) UNSIGNED NOT NULL,
  `departamento_id` int(10) UNSIGNED DEFAULT NULL,
  `setor_id` int(10) UNSIGNED DEFAULT NULL,
  `equipa_id` int(10) UNSIGNED DEFAULT NULL,
  `funcionario_id` int(10) UNSIGNED DEFAULT NULL,
  `numero_mecanografico` varchar(50) DEFAULT NULL,
  `nome` varchar(160) NOT NULL,
  `email` varchar(160) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `telefone` varchar(40) DEFAULT NULL,
  `cargo` varchar(120) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `data_admissao` date DEFAULT NULL,
  `tipo_contrato` varchar(80) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `pin_ponto` varchar(20) DEFAULT NULL,
  `codigo_cartao` varchar(80) DEFAULT NULL,
  `codigo_biometrico` varchar(80) DEFAULT NULL,
  `ultimo_login_at` datetime DEFAULT NULL,
  `estado` enum('ativo','suspenso','inativo') NOT NULL DEFAULT 'ativo',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `utilizadores`
--

INSERT INTO `utilizadores` (`id`, `departamento_id`, `setor_id`, `equipa_id`, `funcionario_id`, `numero_mecanografico`, `nome`, `email`, `password_hash`, `telefone`, `cargo`, `data_nascimento`, `data_admissao`, `tipo_contrato`, `foto`, `pin_ponto`, `codigo_cartao`, `codigo_biometrico`, `ultimo_login_at`, `estado`, `created_at`, `updated_at`) VALUES
(1, NULL, NULL, NULL, NULL, NULL, 'ibraima', 'admin@gmail.com', '$2y$10$EUK7cKeo2pFmaWoqidjQV.zzcGDgJpVa9URbJkMSAZVct/vxtWw4.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-28 13:12:31', 'ativo', '2026-07-24 12:51:54', '2026-07-28 13:12:31');

-- --------------------------------------------------------

--
-- Estrutura da tabela `utilizador_papeis`
--

CREATE TABLE `utilizador_papeis` (
  `id` int(10) UNSIGNED NOT NULL,
  `utilizador_id` int(10) UNSIGNED NOT NULL,
  `papel_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `utilizador_papeis`
--

INSERT INTO `utilizador_papeis` (`id`, `utilizador_id`, `papel_id`, `created_at`) VALUES
(1, 1, 1, '2026-07-24 12:51:54');

-- --------------------------------------------------------

--
-- Estrutura da tabela `utilizador_permissoes`
--

CREATE TABLE `utilizador_permissoes` (
  `id` int(10) UNSIGNED NOT NULL,
  `utilizador_id` int(10) UNSIGNED NOT NULL,
  `permissao_id` int(10) UNSIGNED NOT NULL,
  `efeito` enum('permitir','negar') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `utilizador_permissoes`
--

INSERT INTO `utilizador_permissoes` (`id`, `utilizador_id`, `permissao_id`, `efeito`, `created_at`, `updated_at`) VALUES
(1, 1, 15, 'permitir', '2026-07-24 12:51:54', NULL),
(2, 1, 19, 'permitir', '2026-07-24 12:51:54', NULL),
(3, 1, 18, 'permitir', '2026-07-24 12:51:54', NULL),
(4, 1, 8, 'permitir', '2026-07-24 12:51:54', NULL),
(5, 1, 10, 'permitir', '2026-07-24 12:51:54', NULL),
(6, 1, 11, 'permitir', '2026-07-24 12:51:54', NULL),
(7, 1, 17, 'permitir', '2026-07-24 12:51:54', NULL),
(8, 1, 4, 'permitir', '2026-07-24 12:51:54', NULL),
(9, 1, 6, 'permitir', '2026-07-24 12:51:54', NULL),
(10, 1, 7, 'permitir', '2026-07-24 12:51:54', NULL),
(11, 1, 5, 'permitir', '2026-07-24 12:51:54', NULL),
(12, 1, 3, 'permitir', '2026-07-24 12:51:54', NULL),
(13, 1, 16, 'permitir', '2026-07-24 12:51:54', NULL),
(15, 1, 2, 'permitir', '2026-07-24 12:51:54', NULL),
(16, 1, 1, 'permitir', '2026-07-24 12:51:54', NULL),
(17, 1, 14, 'permitir', '2026-07-24 12:51:54', NULL),
(18, 1, 23, 'permitir', '2026-07-24 12:51:54', NULL),
(19, 1, 12, 'permitir', '2026-07-24 12:51:54', NULL),
(20, 1, 13, 'permitir', '2026-07-24 12:51:54', NULL),
(21, 1, 20, 'permitir', '2026-07-24 12:51:54', NULL),
(22, 1, 21, 'permitir', '2026-07-24 12:51:54', NULL),
(23, 1, 9, 'permitir', '2026-07-24 12:51:54', NULL),
(24, 1, 22, 'permitir', '2026-07-24 12:51:54', NULL);

-- --------------------------------------------------------

--
-- Estrutura stand-in para vista `vw_funcionarios_contexto`
-- (Veja abaixo para a view atual)
--
CREATE TABLE `vw_funcionarios_contexto` (
`funcionario_id` int(10) unsigned
,`utilizador_id` int(10) unsigned
,`numero_mecanografico` varchar(50)
,`nome` varchar(160)
,`email` varchar(160)
,`funcao` varchar(120)
,`estado` enum('ativo','suspenso','inativo')
,`setor_id` int(10) unsigned
,`setor_nome` varchar(140)
,`equipa_id` int(10) unsigned
,`equipa_nome` varchar(140)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para vista `vw_relatorio_mensal_assiduidade`
-- (Veja abaixo para a view atual)
--
CREATE TABLE `vw_relatorio_mensal_assiduidade` (
`data` date
,`ano` int(5)
,`mes` int(3)
,`funcionario_id` int(10) unsigned
,`funcionario_nome` varchar(160)
,`setor_id` int(10) unsigned
,`setor_nome` varchar(140)
,`equipa_id` int(10) unsigned
,`equipa_nome` varchar(140)
,`estado` enum('previsto','presente','ausente','ferias','folga','feriado','incompleto','corrigido','sem_escala')
,`minutos_previstos` int(11)
,`minutos_trabalhados` int(11)
,`minutos_ausencia_justificada` int(11)
,`minutos_atraso` int(11)
,`minutos_saida_antecipada` int(11)
,`minutos_extra` int(11)
,`minutos_saldo` int(11)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para vista `vw_turnos_periodos`
-- (Veja abaixo para a view atual)
--
CREATE TABLE `vw_turnos_periodos` (
`turno_id` int(10) unsigned
,`turno_nome` varchar(120)
,`turno_codigo` varchar(40)
,`horas_previstas` decimal(5,2)
,`total_periodos` tinyint(3) unsigned
,`permite_multiplos_periodos` tinyint(1)
,`periodo_id` int(10) unsigned
,`sequencia` tinyint(3) unsigned
,`hora_inicio` time
,`hora_fim` time
,`cruza_dia` tinyint(1)
,`tolerancia_antes_min` smallint(5) unsigned
,`tolerancia_depois_min` smallint(5) unsigned
,`minutos_previstos` smallint(5) unsigned
);

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `banco_horas`
--
ALTER TABLE `banco_horas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_banco_horas_utilizador_data` (`utilizador_id`,`data_movimento`),
  ADD KEY `idx_banco_horas_tipo` (`tipo_movimento`),
  ADD KEY `idx_banco_horas_registo` (`registo_ponto_id`),
  ADD KEY `idx_banco_horas_pedido` (`pedido_ausencia_id`),
  ADD KEY `idx_banco_horas_criado_por` (`criado_por`),
  ADD KEY `idx_banco_horas_funcionario_data` (`funcionario_id`,`data_movimento`),
  ADD KEY `idx_banco_horas_resumo` (`resumo_diario_id`);

--
-- Índices para tabela `biometric_commands`
--
ALTER TABLE `biometric_commands`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_biometric_commands_queue` (`status`,`available_at`);

--
-- Índices para tabela `config_horas_extra`
--
ALTER TABLE `config_horas_extra`
  ADD PRIMARY KEY (`chave`);

--
-- Índices para tabela `departamentos`
--
ALTER TABLE `departamentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_departamentos_codigo` (`codigo`),
  ADD KEY `idx_departamentos_responsavel` (`responsavel_id`),
  ADD KEY `idx_departamentos_setor` (`setor_id`),
  ADD KEY `idx_departamentos_equipa` (`equipa_id`);

--
-- Índices para tabela `dispositivos`
--
ALTER TABLE `dispositivos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_dispositivos_numero_serie` (`numero_serie`),
  ADD KEY `idx_dispositivos_estado` (`estado`);

--
-- Índices para tabela `diuturnidades_atribuicoes`
--
ALTER TABLE `diuturnidades_atribuicoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_diuturnidade_atribuicao` (`funcionario_id`,`ciclo_numero`,`data_vencimento`),
  ADD KEY `idx_diuturnidades_funcionario` (`funcionario_id`,`data_vencimento`),
  ADD KEY `idx_diuturnidades_confirmado_por` (`confirmado_por`);

--
-- Índices para tabela `equipas`
--
ALTER TABLE `equipas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_equipas_codigo` (`codigo`),
  ADD KEY `idx_equipas_setor` (`setor_id`),
  ADD KEY `idx_equipas_responsavel` (`responsavel_id`),
  ADD KEY `idx_equipas_ativo` (`ativo`);

--
-- Índices para tabela `equipa_mudancas`
--
ALTER TABLE `equipa_mudancas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_equipa_mudancas_funcionario` (`funcionario_id`),
  ADD KEY `idx_equipa_mudancas_equipa_antiga` (`equipa_antiga_id`),
  ADD KEY `idx_equipa_mudancas_equipa_nova` (`equipa_nova_id`),
  ADD KEY `idx_equipa_mudancas_responsavel` (`utilizador_responsavel_id`);

--
-- Índices para tabela `escala_funcionarios`
--
ALTER TABLE `escala_funcionarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_escala_funcionario_dia` (`funcionario_id`,`data_escala`),
  ADD KEY `idx_escala_funcionarios_periodo` (`ano`,`mes`),
  ADD KEY `idx_escala_funcionarios_setor` (`setor_id`,`data_escala`),
  ADD KEY `idx_escala_funcionarios_equipa` (`equipa_id`,`data_escala`),
  ADD KEY `idx_escala_funcionarios_turno` (`turno_id`),
  ADD KEY `idx_escala_funcionarios_substitui` (`substitui_funcionario_id`),
  ADD KEY `fk_escala_funcionarios_utilizador` (`utilizador_id`),
  ADD KEY `idx_escala_funcionarios_origem` (`origem`);

--
-- Índices para tabela `escala_mensal`
--
ALTER TABLE `escala_mensal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_escala_mensal_contexto` (`ano`,`mes`,`setor_id`,`equipa_id`),
  ADD KEY `idx_escala_mensal_setor` (`setor_id`),
  ADD KEY `idx_escala_mensal_equipa` (`equipa_id`),
  ADD KEY `idx_escala_mensal_estado` (`estado`),
  ADD KEY `fk_escala_mensal_publicada_por` (`publicada_por`),
  ADD KEY `fk_escala_mensal_fechada_por` (`fechada_por`),
  ADD KEY `fk_escala_mensal_created_by` (`created_by`);

--
-- Índices para tabela `escala_mensal_dias`
--
ALTER TABLE `escala_mensal_dias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_escala_dia_funcionario` (`funcionario_id`,`data_escala`),
  ADD KEY `idx_escala_dias_escala` (`escala_mensal_id`),
  ADD KEY `idx_escala_dias_utilizador` (`utilizador_id`,`data_escala`),
  ADD KEY `idx_escala_dias_setor` (`setor_id`,`data_escala`),
  ADD KEY `idx_escala_dias_equipa` (`equipa_id`,`data_escala`),
  ADD KEY `idx_escala_dias_turno` (`turno_id`);

--
-- Índices para tabela `escala_periodos`
--
ALTER TABLE `escala_periodos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_escala_periodos_funcionario_data` (`funcionario_id`,`data_escala`),
  ADD KEY `fk_escala_periodos_turno` (`turno_id`);

--
-- Índices para tabela `ferias_ausencias`
--
ALTER TABLE `ferias_ausencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ferias_funcionario_periodo` (`funcionario_id`,`data_inicio`,`data_fim`),
  ADD KEY `idx_ferias_utilizador_periodo` (`utilizador_id`,`data_inicio`,`data_fim`),
  ADD KEY `idx_ferias_tipo` (`tipo_ausencia_id`),
  ADD KEY `idx_ferias_estado` (`estado`),
  ADD KEY `idx_ferias_pedido` (`pedido_ausencia_id`),
  ADD KEY `fk_ferias_aprovado_por` (`aprovado_por`);

--
-- Índices para tabela `funcionarios`
--
ALTER TABLE `funcionarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_funcionarios_utilizador` (`utilizador_id`),
  ADD UNIQUE KEY `uk_funcionarios_numero_mecanografico` (`numero_mecanografico`),
  ADD UNIQUE KEY `uk_funcionarios_pin_ponto` (`pin_ponto`),
  ADD UNIQUE KEY `uk_funcionarios_codigo_cartao` (`codigo_cartao`),
  ADD UNIQUE KEY `uk_funcionarios_codigo_biometrico` (`codigo_biometrico`),
  ADD UNIQUE KEY `uk_funcionarios_codigo_picagem` (`codigo_picagem`),
  ADD KEY `idx_funcionarios_setor` (`setor_id`),
  ADD KEY `idx_funcionarios_equipa` (`equipa_id`),
  ADD KEY `idx_funcionarios_estado` (`estado`),
  ADD KEY `idx_funcionarios_tipo_contrato` (`tipo_contrato_id`),
  ADD KEY `idx_funcionarios_data_nascimento` (`data_nascimento`),
  ADD KEY `idx_funcionarios_diuturnidade_base` (`diuturnidade_data_base`);

--
-- Índices para tabela `funcionario_tipos_contrato`
--
ALTER TABLE `funcionario_tipos_contrato`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_funcionario_tipos_contrato_nome` (`nome`);

--
-- Índices para tabela `horarios_turno`
--
ALTER TABLE `horarios_turno`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_horarios_utilizador` (`utilizador_id`),
  ADD KEY `idx_horarios_turno` (`turno_id`),
  ADD KEY `idx_horarios_periodo` (`data_inicio`,`data_fim`),
  ADD KEY `idx_horarios_funcionario` (`funcionario_id`);

--
-- Índices para tabela `horas_extra_regras`
--
ALTER TABLE `horas_extra_regras`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_horas_extra_regras_codigo` (`codigo`);

--
-- Índices para tabela `logs_sistema`
--
ALTER TABLE `logs_sistema`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_utilizador` (`utilizador_id`),
  ADD KEY `idx_logs_acao` (`acao`),
  ADD KEY `idx_logs_modulo` (`modulo`),
  ADD KEY `idx_logs_created_at` (`created_at`);

--
-- Índices para tabela `notificacao_destinatarios`
--
ALTER TABLE `notificacao_destinatarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_notificacao_destinatario` (`notificacao_id`,`utilizador_id`),
  ADD KEY `idx_notificacao_destinatarios_utilizador` (`utilizador_id`,`lida_at`);

--
-- Índices para tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_notificacao_evento` (`tipo`,`funcionario_id`,`referencia_ano`,`ciclo_numero`),
  ADD KEY `idx_notificacoes_tipo_data` (`tipo`,`data_evento`),
  ADD KEY `idx_notificacoes_estado` (`estado`),
  ADD KEY `idx_notificacoes_funcionario` (`funcionario_id`);

--
-- Índices para tabela `notificacoes_config`
--
ALTER TABLE `notificacoes_config`
  ADD PRIMARY KEY (`chave`);

--
-- Índices para tabela `papeis`
--
ALTER TABLE `papeis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_papeis_slug` (`slug`);

--
-- Índices para tabela `papel_permissoes`
--
ALTER TABLE `papel_permissoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_papel_permissoes` (`papel_id`,`permissao_id`),
  ADD KEY `idx_papel_permissoes_permissao` (`permissao_id`);

--
-- Índices para tabela `pedidos_ausencia`
--
ALTER TABLE `pedidos_ausencia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pedidos_utilizador` (`utilizador_id`),
  ADD KEY `idx_pedidos_tipo` (`tipo_ausencia_id`),
  ADD KEY `idx_pedidos_estado` (`estado`),
  ADD KEY `idx_pedidos_periodo` (`data_inicio`,`data_fim`),
  ADD KEY `idx_pedidos_aprovado_por` (`aprovado_por`),
  ADD KEY `idx_pedidos_funcionario` (`funcionario_id`);

--
-- Índices para tabela `permissoes`
--
ALTER TABLE `permissoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_permissoes_codigo` (`codigo`);

--
-- Índices para tabela `registos_ponto`
--
ALTER TABLE `registos_ponto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_registos_utilizador_data` (`utilizador_id`,`data_hora`),
  ADD KEY `idx_registos_dispositivo` (`dispositivo_id`),
  ADD KEY `idx_registos_tipo` (`tipo`),
  ADD KEY `idx_registos_estado` (`estado`),
  ADD KEY `idx_registos_criado_por` (`criado_por`),
  ADD KEY `idx_registos_atualizado_por` (`atualizado_por`),
  ADD KEY `idx_registos_funcionario_data` (`funcionario_id`,`data_hora`),
  ADD KEY `idx_registos_data_referencia` (`data_referencia`),
  ADD KEY `idx_registos_escala_dia` (`escala_mensal_dia_id`),
  ADD KEY `idx_registos_turno_periodo` (`turno_periodo_id`);

--
-- Índices para tabela `registos_ponto_logs`
--
ALTER TABLE `registos_ponto_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_registos_ponto_logs_registo` (`registo_ponto_id`);

--
-- Índices para tabela `relatorios_mensais`
--
ALTER TABLE `relatorios_mensais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_relatorio_mensal_contexto` (`ano`,`mes`,`tipo`,`funcionario_id`,`equipa_id`,`setor_id`),
  ADD KEY `idx_relatorios_funcionario` (`funcionario_id`),
  ADD KEY `idx_relatorios_equipa` (`equipa_id`),
  ADD KEY `idx_relatorios_setor` (`setor_id`),
  ADD KEY `idx_relatorios_estado` (`estado`),
  ADD KEY `fk_relatorios_gerado_por` (`gerado_por`),
  ADD KEY `fk_relatorios_validado_por` (`validado_por`);

--
-- Índices para tabela `relatorio_mensal_linhas`
--
ALTER TABLE `relatorio_mensal_linhas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_relatorio_linha_dia` (`relatorio_mensal_id`,`funcionario_id`,`data`),
  ADD KEY `idx_relatorio_linhas_resumo` (`resumo_diario_id`),
  ADD KEY `idx_relatorio_linhas_funcionario` (`funcionario_id`,`data`);

--
-- Índices para tabela `resumo_diario_assiduidade`
--
ALTER TABLE `resumo_diario_assiduidade`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_resumo_diario_funcionario` (`funcionario_id`,`data`),
  ADD KEY `idx_resumo_diario_utilizador` (`utilizador_id`,`data`),
  ADD KEY `idx_resumo_diario_setor` (`setor_id`,`data`),
  ADD KEY `idx_resumo_diario_equipa` (`equipa_id`,`data`),
  ADD KEY `idx_resumo_diario_estado` (`estado`),
  ADD KEY `idx_resumo_substitui_funcionario` (`substitui_funcionario_id`),
  ADD KEY `fk_resumo_escala_dia` (`escala_mensal_dia_id`),
  ADD KEY `fk_resumo_turno` (`turno_id`);

--
-- Índices para tabela `setores`
--
ALTER TABLE `setores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_setores_codigo` (`codigo`),
  ADD KEY `idx_setores_responsavel` (`responsavel_id`),
  ADD KEY `idx_setores_ativo` (`ativo`);

--
-- Índices para tabela `tipos_ausencia`
--
ALTER TABLE `tipos_ausencia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tipos_ausencia_slug` (`slug`);

--
-- Índices para tabela `turnos`
--
ALTER TABLE `turnos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_turnos_codigo` (`codigo`),
  ADD KEY `idx_turnos_ativo` (`ativo`),
  ADD KEY `idx_turnos_equipa` (`equipa_id`),
  ADD KEY `idx_turnos_funcionario` (`funcionario_id`);

--
-- Índices para tabela `turno_periodos`
--
ALTER TABLE `turno_periodos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_turno_periodos_seq` (`turno_id`,`sequencia`),
  ADD KEY `idx_turno_periodos_turno` (`turno_id`);

--
-- Índices para tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_utilizadores_email` (`email`),
  ADD UNIQUE KEY `uk_utilizadores_numero_mecanografico` (`numero_mecanografico`),
  ADD UNIQUE KEY `uk_utilizadores_pin_ponto` (`pin_ponto`),
  ADD UNIQUE KEY `uk_utilizadores_codigo_cartao` (`codigo_cartao`),
  ADD UNIQUE KEY `uk_utilizadores_codigo_biometrico` (`codigo_biometrico`),
  ADD KEY `idx_utilizadores_departamento` (`departamento_id`),
  ADD KEY `idx_utilizadores_estado` (`estado`),
  ADD KEY `idx_utilizadores_setor` (`setor_id`),
  ADD KEY `idx_utilizadores_equipa` (`equipa_id`),
  ADD KEY `idx_utilizadores_funcionario` (`funcionario_id`);

--
-- Índices para tabela `utilizador_papeis`
--
ALTER TABLE `utilizador_papeis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_utilizador_papel` (`utilizador_id`,`papel_id`),
  ADD KEY `idx_utilizador_papeis_papel` (`papel_id`);

--
-- Índices para tabela `utilizador_permissoes`
--
ALTER TABLE `utilizador_permissoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_utilizador_permissoes` (`utilizador_id`,`permissao_id`),
  ADD KEY `idx_utilizador_permissoes_permissao` (`permissao_id`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `banco_horas`
--
ALTER TABLE `banco_horas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `biometric_commands`
--
ALTER TABLE `biometric_commands`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `departamentos`
--
ALTER TABLE `departamentos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `dispositivos`
--
ALTER TABLE `dispositivos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `diuturnidades_atribuicoes`
--
ALTER TABLE `diuturnidades_atribuicoes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `equipas`
--
ALTER TABLE `equipas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `equipa_mudancas`
--
ALTER TABLE `equipa_mudancas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `escala_funcionarios`
--
ALTER TABLE `escala_funcionarios`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `escala_mensal`
--
ALTER TABLE `escala_mensal`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `escala_mensal_dias`
--
ALTER TABLE `escala_mensal_dias`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `escala_periodos`
--
ALTER TABLE `escala_periodos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ferias_ausencias`
--
ALTER TABLE `ferias_ausencias`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `funcionarios`
--
ALTER TABLE `funcionarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `funcionario_tipos_contrato`
--
ALTER TABLE `funcionario_tipos_contrato`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT de tabela `horarios_turno`
--
ALTER TABLE `horarios_turno`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `horas_extra_regras`
--
ALTER TABLE `horas_extra_regras`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `logs_sistema`
--
ALTER TABLE `logs_sistema`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `notificacao_destinatarios`
--
ALTER TABLE `notificacao_destinatarios`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `papeis`
--
ALTER TABLE `papeis`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `papel_permissoes`
--
ALTER TABLE `papel_permissoes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=172;

--
-- AUTO_INCREMENT de tabela `pedidos_ausencia`
--
ALTER TABLE `pedidos_ausencia`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `permissoes`
--
ALTER TABLE `permissoes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de tabela `registos_ponto`
--
ALTER TABLE `registos_ponto`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `registos_ponto_logs`
--
ALTER TABLE `registos_ponto_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `relatorios_mensais`
--
ALTER TABLE `relatorios_mensais`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `relatorio_mensal_linhas`
--
ALTER TABLE `relatorio_mensal_linhas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `resumo_diario_assiduidade`
--
ALTER TABLE `resumo_diario_assiduidade`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `setores`
--
ALTER TABLE `setores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `tipos_ausencia`
--
ALTER TABLE `tipos_ausencia`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `turnos`
--
ALTER TABLE `turnos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `turno_periodos`
--
ALTER TABLE `turno_periodos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `utilizador_papeis`
--
ALTER TABLE `utilizador_papeis`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `utilizador_permissoes`
--
ALTER TABLE `utilizador_permissoes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

-- --------------------------------------------------------

--
-- Estrutura para vista `vw_funcionarios_contexto`
--
DROP TABLE IF EXISTS `vw_funcionarios_contexto`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u130245564_csnsa`@`127.0.0.1` SQL SECURITY DEFINER VIEW `vw_funcionarios_contexto`  AS SELECT `f`.`id` AS `funcionario_id`, `f`.`utilizador_id` AS `utilizador_id`, coalesce(`f`.`numero_mecanografico`,`u`.`numero_mecanografico`) AS `numero_mecanografico`, coalesce(`f`.`nome`,`u`.`nome`) AS `nome`, coalesce(`f`.`email`,`u`.`email`) AS `email`, `f`.`funcao` AS `funcao`, `f`.`estado` AS `estado`, `f`.`setor_id` AS `setor_id`, `s`.`nome` AS `setor_nome`, `f`.`equipa_id` AS `equipa_id`, `e`.`nome` AS `equipa_nome` FROM (((`funcionarios` `f` left join `utilizadores` `u` on(`u`.`id` = `f`.`utilizador_id`)) left join `setores` `s` on(`s`.`id` = `f`.`setor_id`)) left join `equipas` `e` on(`e`.`id` = `f`.`equipa_id`)) ;

-- --------------------------------------------------------

--
-- Estrutura para vista `vw_relatorio_mensal_assiduidade`
--
DROP TABLE IF EXISTS `vw_relatorio_mensal_assiduidade`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u130245564_csnsa`@`127.0.0.1` SQL SECURITY DEFINER VIEW `vw_relatorio_mensal_assiduidade`  AS SELECT `rda`.`data` AS `data`, year(`rda`.`data`) AS `ano`, month(`rda`.`data`) AS `mes`, `rda`.`funcionario_id` AS `funcionario_id`, `f`.`nome` AS `funcionario_nome`, `rda`.`setor_id` AS `setor_id`, `s`.`nome` AS `setor_nome`, `rda`.`equipa_id` AS `equipa_id`, `e`.`nome` AS `equipa_nome`, `rda`.`estado` AS `estado`, `rda`.`minutos_previstos` AS `minutos_previstos`, `rda`.`minutos_trabalhados` AS `minutos_trabalhados`, `rda`.`minutos_ausencia_justificada` AS `minutos_ausencia_justificada`, `rda`.`minutos_atraso` AS `minutos_atraso`, `rda`.`minutos_saida_antecipada` AS `minutos_saida_antecipada`, `rda`.`minutos_extra` AS `minutos_extra`, `rda`.`minutos_saldo` AS `minutos_saldo` FROM (((`resumo_diario_assiduidade` `rda` left join `funcionarios` `f` on(`f`.`id` = `rda`.`funcionario_id`)) left join `setores` `s` on(`s`.`id` = `rda`.`setor_id`)) left join `equipas` `e` on(`e`.`id` = `rda`.`equipa_id`)) ;

-- --------------------------------------------------------

--
-- Estrutura para vista `vw_turnos_periodos`
--
DROP TABLE IF EXISTS `vw_turnos_periodos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u130245564_csnsa`@`127.0.0.1` SQL SECURITY DEFINER VIEW `vw_turnos_periodos`  AS SELECT `t`.`id` AS `turno_id`, `t`.`nome` AS `turno_nome`, `t`.`codigo` AS `turno_codigo`, `t`.`horas_previstas` AS `horas_previstas`, `t`.`total_periodos` AS `total_periodos`, `t`.`permite_multiplos_periodos` AS `permite_multiplos_periodos`, `tp`.`id` AS `periodo_id`, `tp`.`sequencia` AS `sequencia`, `tp`.`hora_inicio` AS `hora_inicio`, `tp`.`hora_fim` AS `hora_fim`, `tp`.`cruza_dia` AS `cruza_dia`, `tp`.`tolerancia_antes_min` AS `tolerancia_antes_min`, `tp`.`tolerancia_depois_min` AS `tolerancia_depois_min`, `tp`.`minutos_previstos` AS `minutos_previstos` FROM (`turnos` `t` join `turno_periodos` `tp` on(`tp`.`turno_id` = `t`.`id`)) WHERE `t`.`ativo` = 1 AND `tp`.`ativo` = 1 ;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `banco_horas`
--
ALTER TABLE `banco_horas`
  ADD CONSTRAINT `fk_banco_horas_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_banco_horas_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_banco_horas_pedido_ausencia` FOREIGN KEY (`pedido_ausencia_id`) REFERENCES `pedidos_ausencia` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_banco_horas_registo_ponto` FOREIGN KEY (`registo_ponto_id`) REFERENCES `registos_ponto` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_banco_horas_resumo` FOREIGN KEY (`resumo_diario_id`) REFERENCES `resumo_diario_assiduidade` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_banco_horas_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `departamentos`
--
ALTER TABLE `departamentos`
  ADD CONSTRAINT `fk_departamentos_equipa` FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_departamentos_responsavel` FOREIGN KEY (`responsavel_id`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_departamentos_setor` FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `diuturnidades_atribuicoes`
--
ALTER TABLE `diuturnidades_atribuicoes`
  ADD CONSTRAINT `fk_diuturnidades_confirmado_por` FOREIGN KEY (`confirmado_por`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_diuturnidades_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limitadores para a tabela `equipas`
--
ALTER TABLE `equipas`
  ADD CONSTRAINT `fk_equipas_responsavel` FOREIGN KEY (`responsavel_id`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_equipas_setor` FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `equipa_mudancas`
--
ALTER TABLE `equipa_mudancas`
  ADD CONSTRAINT `fk_equipa_mudancas_equipa_antiga` FOREIGN KEY (`equipa_antiga_id`) REFERENCES `equipas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_equipa_mudancas_equipa_nova` FOREIGN KEY (`equipa_nova_id`) REFERENCES `equipas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_equipa_mudancas_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_equipa_mudancas_responsavel` FOREIGN KEY (`utilizador_responsavel_id`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `escala_funcionarios`
--
ALTER TABLE `escala_funcionarios`
  ADD CONSTRAINT `fk_escala_funcionarios_equipa` FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_escala_funcionarios_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_escala_funcionarios_setor` FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_escala_funcionarios_substitui` FOREIGN KEY (`substitui_funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_escala_funcionarios_turno` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_escala_funcionarios_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `escala_mensal`
--
ALTER TABLE `escala_mensal`
  ADD CONSTRAINT `fk_escala_mensal_created_by` FOREIGN KEY (`created_by`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_escala_mensal_equipa` FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_escala_mensal_fechada_por` FOREIGN KEY (`fechada_por`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_escala_mensal_publicada_por` FOREIGN KEY (`publicada_por`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_escala_mensal_setor` FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `escala_mensal_dias`
--
ALTER TABLE `escala_mensal_dias`
  ADD CONSTRAINT `fk_escala_dias_equipa` FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_escala_dias_escala` FOREIGN KEY (`escala_mensal_id`) REFERENCES `escala_mensal` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_escala_dias_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_escala_dias_setor` FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_escala_dias_turno` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_escala_dias_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `escala_periodos`
--
ALTER TABLE `escala_periodos`
  ADD CONSTRAINT `fk_escala_periodos_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_escala_periodos_turno` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `ferias_ausencias`
--
ALTER TABLE `ferias_ausencias`
  ADD CONSTRAINT `fk_ferias_aprovado_por` FOREIGN KEY (`aprovado_por`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ferias_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ferias_pedido` FOREIGN KEY (`pedido_ausencia_id`) REFERENCES `pedidos_ausencia` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ferias_tipo` FOREIGN KEY (`tipo_ausencia_id`) REFERENCES `tipos_ausencia` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ferias_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `funcionarios`
--
ALTER TABLE `funcionarios`
  ADD CONSTRAINT `fk_funcionarios_equipa` FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_funcionarios_setor` FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_funcionarios_tipo_contrato` FOREIGN KEY (`tipo_contrato_id`) REFERENCES `funcionario_tipos_contrato` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_funcionarios_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `horarios_turno`
--
ALTER TABLE `horarios_turno`
  ADD CONSTRAINT `fk_horarios_turno_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_horarios_turno_turno` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_horarios_turno_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limitadores para a tabela `logs_sistema`
--
ALTER TABLE `logs_sistema`
  ADD CONSTRAINT `fk_logs_sistema_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `notificacao_destinatarios`
--
ALTER TABLE `notificacao_destinatarios`
  ADD CONSTRAINT `fk_notificacao_destinatarios_notificacao` FOREIGN KEY (`notificacao_id`) REFERENCES `notificacoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notificacao_destinatarios_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limitadores para a tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD CONSTRAINT `fk_notificacoes_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limitadores para a tabela `papel_permissoes`
--
ALTER TABLE `papel_permissoes`
  ADD CONSTRAINT `fk_papel_permissoes_papel` FOREIGN KEY (`papel_id`) REFERENCES `papeis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_papel_permissoes_permissao` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limitadores para a tabela `pedidos_ausencia`
--
ALTER TABLE `pedidos_ausencia`
  ADD CONSTRAINT `fk_pedidos_ausencia_aprovado_por` FOREIGN KEY (`aprovado_por`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedidos_ausencia_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedidos_ausencia_tipo` FOREIGN KEY (`tipo_ausencia_id`) REFERENCES `tipos_ausencia` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedidos_ausencia_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `registos_ponto`
--
ALTER TABLE `registos_ponto`
  ADD CONSTRAINT `fk_registos_ponto_atualizado_por` FOREIGN KEY (`atualizado_por`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_registos_ponto_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_registos_ponto_dispositivo` FOREIGN KEY (`dispositivo_id`) REFERENCES `dispositivos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_registos_ponto_escala_dia` FOREIGN KEY (`escala_mensal_dia_id`) REFERENCES `escala_mensal_dias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_registos_ponto_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_registos_ponto_turno_periodo` FOREIGN KEY (`turno_periodo_id`) REFERENCES `turno_periodos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_registos_ponto_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `registos_ponto_logs`
--
ALTER TABLE `registos_ponto_logs`
  ADD CONSTRAINT `fk_registos_ponto_logs_registo` FOREIGN KEY (`registo_ponto_id`) REFERENCES `registos_ponto` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limitadores para a tabela `relatorios_mensais`
--
ALTER TABLE `relatorios_mensais`
  ADD CONSTRAINT `fk_relatorios_equipa` FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_relatorios_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_relatorios_gerado_por` FOREIGN KEY (`gerado_por`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_relatorios_setor` FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_relatorios_validado_por` FOREIGN KEY (`validado_por`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `relatorio_mensal_linhas`
--
ALTER TABLE `relatorio_mensal_linhas`
  ADD CONSTRAINT `fk_relatorio_linhas_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_relatorio_linhas_relatorio` FOREIGN KEY (`relatorio_mensal_id`) REFERENCES `relatorios_mensais` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_relatorio_linhas_resumo` FOREIGN KEY (`resumo_diario_id`) REFERENCES `resumo_diario_assiduidade` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `resumo_diario_assiduidade`
--
ALTER TABLE `resumo_diario_assiduidade`
  ADD CONSTRAINT `fk_resumo_equipa` FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_resumo_escala_dia` FOREIGN KEY (`escala_mensal_dia_id`) REFERENCES `escala_mensal_dias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_resumo_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_resumo_setor` FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_resumo_substitui_funcionario` FOREIGN KEY (`substitui_funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_resumo_turno` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_resumo_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `setores`
--
ALTER TABLE `setores`
  ADD CONSTRAINT `fk_setores_responsavel` FOREIGN KEY (`responsavel_id`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `turnos`
--
ALTER TABLE `turnos`
  ADD CONSTRAINT `fk_turnos_equipa` FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_turnos_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `turno_periodos`
--
ALTER TABLE `turno_periodos`
  ADD CONSTRAINT `fk_turno_periodos_turno` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limitadores para a tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  ADD CONSTRAINT `fk_utilizadores_departamento` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_utilizadores_equipa` FOREIGN KEY (`equipa_id`) REFERENCES `equipas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_utilizadores_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_utilizadores_setor` FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `utilizador_papeis`
--
ALTER TABLE `utilizador_papeis`
  ADD CONSTRAINT `fk_utilizador_papeis_papel` FOREIGN KEY (`papel_id`) REFERENCES `papeis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_utilizador_papeis_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limitadores para a tabela `utilizador_permissoes`
--
ALTER TABLE `utilizador_permissoes`
  ADD CONSTRAINT `fk_utilizador_permissoes_permissao` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_utilizador_permissoes_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
