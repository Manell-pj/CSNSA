# Adaptação SQL para lar de idosos

Ficheiro principal: `database/schema_completo.sql`.

## Diagnóstico da estrutura atual

O projeto já tem uma base funcional para assiduidade, mas a separação correta passa a ser:

- `funcionarios` guarda as pessoas que trabalham na instituição e que vão picar ponto.
- `utilizadores` fica reservado apenas para quem acede ao site, como secretaria, direção ou RH.
- `equipas` organiza os funcionários por função operacional.
- `turnos` define horários simples com uma entrada e uma saída.
- `horarios_turno` associa turnos a funcionários por período e dia da semana.
- `registos_ponto` guarda as picagens, sempre ligadas a `funcionario_id`.
- `tipos_ausencia` e `pedidos_ausencia` tratam férias/faltas.
- `banco_horas` guarda créditos/débitos.

Para um lar de idosos faltavam duas camadas:

- equipas operacionais, porque a secretaria precisa de filtrar por função real;
- turnos com vários períodos no mesmo dia, porque há horários repartidos;
- tabelas de apuramento, para fechar dias e meses sem recalcular tudo em cada relatório.

## Alterações propostas

A migration cria sem apagar dados:

- `equipas`
- `funcionarios`
- `turno_periodos`
- `escala_mensal`
- `escala_mensal_dias`
- `ferias_ausencias`
- `resumo_diario_assiduidade`
- `relatorios_mensais`
- `relatorio_mensal_linhas`

Também acrescenta colunas a tabelas existentes:

- `utilizadores`: `equipa_id`, `funcionario_id`
- `turnos`: `descricao`, `total_periodos`, `permite_multiplos_periodos`, `tolerancia_antes_min`, `tolerancia_depois_min`
- `horarios_turno`: `funcionario_id`
- `registos_ponto`: ligação a funcionário, escala, período do turno e campos de tolerância/desvio
- `banco_horas`: ligação a funcionário e resumo diário

## Dados iniciais

São inseridas equipas operacionais iniciais:

- Ação Direta
- Serviços Gerais
- Refeitório/Copa
- Cozinha
- Lavandaria
- Serviços Técnicos e Administrativos
- Motoristas

E os turnos indicados pela instituição, com tolerância de 15 minutos antes e 15 minutos depois:

- 00:00-08:00
- 08:00-16:00
- 16:00-00:00
- 08:30-16:30
- 08:00-13:00 e 17:00-20:00
- 08:00-14:00
- 08:00-12:00 e 18:00-20:00
- 12:00-20:00
- 09:00-12:30 e 14:00-17:30

## Compatibilidade PHP/MySQLi

O código operacional deve consultar `funcionarios`, `equipas`, `turnos`, `pedidos_ausencia`, `registos_ponto` e `banco_horas`.
`utilizadores` deve ser usado apenas para login e auditoria de quem fez alterações no site.

## Permissões de Acesso

As permissões são atribuídas diretamente a cada utilizador. Ao criar ou editar uma conta em `utilizadores.php`, a aplicação apresenta a lista de permissões disponíveis e grava a configuração em `utilizador_permissoes`.

Regras atuais:

- `utilizador_permissoes.efeito = 'permitir'` dá acesso à funcionalidade.
- `utilizador_permissoes.efeito = 'negar'` remove o acesso à funcionalidade.
- `includes/permissoes.php` valida o acesso através de `ac_can()`, `ac_can_any()` e `ac_require_permission()`.
- As tabelas `papeis`, `papel_permissoes` e `utilizador_papeis` podem existir por compatibilidade, mas não devem ser usadas para definir novos acessos.
- Deve existir sempre pelo menos um utilizador ativo com `utilizadores.gerir` e `permissoes.gerir`, para evitar bloqueio administrativo.

Para novos ecrãs, usar sempre `mysqli_prepare`, por exemplo:

```php
$stmt = mysqli_prepare($conn, 'SELECT funcionario_id, nome, equipa_nome FROM vw_funcionarios_contexto WHERE estado = ? ORDER BY nome');
$estado = 'ativo';
mysqli_stmt_bind_param($stmt, 's', $estado);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
```

Views criadas para simplificar listagens:

- `vw_funcionarios_contexto`
- `vw_turnos_periodos`
- `vw_relatorio_mensal_assiduidade`

## Fluxo recomendado

1. A secretaria cria/atualiza funcionários e equipas.
2. O leitor biométrico identifica o funcionário por `codigo_biometrico` e cria `registos_ponto` com origem `dispositivo`.
3. Os funcionários picam apenas `entrada` e `saida`.
4. A secretaria regista ou corrige pausas, faltas e ajustes manuais quando necessário.
5. A escala mensal é criada em `escala_mensal` e preenchida em `escala_mensal_dias`.
6. Um processo PHP de apuramento diário grava `resumo_diario_assiduidade`.
7. Os relatórios mensais fechados são guardados em `relatorios_mensais` e `relatorio_mensal_linhas`.

Esta abordagem evita apagar registos críticos: ausências ficam canceladas/rejeitadas, registos de ponto ficam anulados/corrigidos, e relatórios fechados podem ser preservados como histórico.
