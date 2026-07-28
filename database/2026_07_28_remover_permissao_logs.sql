-- Remove a permissao da pagina Logs, que foi retirada da aplicacao.
-- Pode ser executado no phpMyAdmin.

START TRANSACTION;

DELETE pp
FROM papel_permissoes pp
INNER JOIN permissoes p ON p.id = pp.permissao_id
WHERE p.codigo = 'logs.consultar';

DELETE FROM permissoes
WHERE codigo = 'logs.consultar';

COMMIT;
