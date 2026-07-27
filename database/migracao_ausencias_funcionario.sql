-- Migracao: ausencias atribuidas a funcionario e permissao para pedir ausencias.
-- Aplicar depois do schema base, numa base que ja tenha utilizadores, funcionarios e permissoes.

DELIMITER $$

DROP PROCEDURE IF EXISTS ausencias_add_column_if_missing $$
CREATE PROCEDURE ausencias_add_column_if_missing(
  IN p_table VARCHAR(64),
  IN p_column VARCHAR(64),
  IN p_definition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND COLUMN_NAME = p_column
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DROP PROCEDURE IF EXISTS ausencias_add_index_if_missing $$
CREATE PROCEDURE ausencias_add_index_if_missing(
  IN p_table VARCHAR(64),
  IN p_index VARCHAR(64),
  IN p_definition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND INDEX_NAME = p_index
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD ', p_definition);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DROP PROCEDURE IF EXISTS ausencias_add_fk_if_missing $$
CREATE PROCEDURE ausencias_add_fk_if_missing(
  IN p_table VARCHAR(64),
  IN p_constraint VARCHAR(64),
  IN p_definition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND CONSTRAINT_NAME = p_constraint
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD CONSTRAINT `', p_constraint, '` ', p_definition);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DELIMITER ;

CALL ausencias_add_column_if_missing('pedidos_ausencia', 'funcionario_id', 'INT UNSIGNED DEFAULT NULL AFTER `utilizador_id`');
CALL ausencias_add_index_if_missing('pedidos_ausencia', 'idx_pedidos_funcionario', 'INDEX `idx_pedidos_funcionario` (`funcionario_id`)');
CALL ausencias_add_fk_if_missing(
  'pedidos_ausencia',
  'fk_pedidos_ausencia_funcionario',
  'FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON UPDATE CASCADE ON DELETE SET NULL'
);

UPDATE pedidos_ausencia pa
INNER JOIN funcionarios f ON f.utilizador_id = pa.utilizador_id
SET pa.funcionario_id = f.id
WHERE pa.funcionario_id IS NULL;

INSERT INTO permissoes (`codigo`, `nome`, `descricao`) VALUES
('ausencias.pedir', 'Pedir ausências', 'Criar pedidos de ausência próprios')
ON DUPLICATE KEY UPDATE
  `nome` = VALUES(`nome`),
  `descricao` = VALUES(`descricao`);

DROP PROCEDURE IF EXISTS ausencias_add_column_if_missing;
DROP PROCEDURE IF EXISTS ausencias_add_index_if_missing;
DROP PROCEDURE IF EXISTS ausencias_add_fk_if_missing;
