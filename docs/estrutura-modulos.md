# Estrutura de Módulos - Assiduidade e RH

Este projeto deve manter uma base simples em PHP procedural com MySQLi, usando os includes já existentes:

- `includes/head.php`
- `includes/header.php`
- `includes/sidebar.php`
- `includes/footer.php`
- `includes/scripts.php`
- `config.php`

A ideia é organizar a aplicação por módulos funcionais, cada um com páginas próprias, ficheiros de ações e consultas SQL simples.

## Estrutura Recomendada

```text
/
├── index.php
├── principal.php
├── config.php
├── includes/
│   ├── head.php
│   ├── header.php
│   ├── sidebar.php
│   ├── footer.php
│   ├── scripts.php
│   ├── auth.php
│   ├── permissions.php
│   └── helpers.php
├── modules/
│   ├── dashboard/
│   │   ├── index.php
│   │   └── widgets.php
│   ├── colaboradores/
│   │   ├── index.php
│   │   ├── criar.php
│   │   ├── editar.php
│   │   ├── ver.php
│   │   └── acoes.php
│   ├── departamentos/
│   │   ├── index.php
│   │   ├── criar.php
│   │   ├── editar.php
│   │   └── acoes.php
│   ├── horarios/
│   │   ├── index.php
│   │   ├── turnos.php
│   │   ├── escalas.php
│   │   ├── atribuir.php
│   │   └── acoes.php
│   ├── ponto/
│   │   ├── index.php
│   │   ├── registos.php
│   │   ├── manual.php
│   │   ├── importar.php
│   │   └── acoes.php
│   ├── ausencias/
│   │   ├── index.php
│   │   ├── ferias.php
│   │   ├── faltas.php
│   │   ├── pedidos.php
│   │   ├── aprovar.php
│   │   └── acoes.php
│   ├── banco_horas/
│   │   ├── index.php
│   │   ├── movimentos.php
│   │   ├── ajustes.php
│   │   └── acoes.php
│   ├── relatorios/
│   │   ├── index.php
│   │   ├── assiduidade.php
│   │   ├── horas.php
│   │   ├── ausencias.php
│   │   └── exportar.php
│   ├── dispositivos/
│   │   ├── index.php
│   │   ├── criar.php
│   │   ├── sincronizar.php
│   │   ├── logs.php
│   │   └── acoes.php
│   └── permissoes/
│       ├── utilizadores.php
│       ├── permissoes.php
│       └── acoes.php
├── uploads/
│   ├── colaboradores/
│   └── importacoes/
└── docs/
    └── estrutura-modulos.md
```

## Convenção de Cada Módulo

Cada módulo deve seguir uma organização previsível:

- `index.php`: listagem principal do módulo.
- `criar.php`: formulário de criação, quando aplicável.
- `editar.php`: formulário de edição, quando aplicável.
- `ver.php`: detalhe de um registo, quando aplicável.
- `acoes.php`: tratamento de `POST`, criação, atualização, remoção, ativação e outras operações.
- ficheiros específicos: páginas próprias do domínio, como `turnos.php`, `ferias.php`, `sincronizar.php` ou `exportar.php`.

As páginas visuais devem incluir o layout base:

```php
include '../../config.php';
include '../../includes/head.php';
include '../../includes/sidebar.php';
include '../../includes/header.php';
include '../../includes/footer.php';
include '../../includes/scripts.php';
```

O caminho pode variar conforme a localização do ficheiro.

## Módulos

### Dashboard e Relatórios

Objetivo: dar uma visão rápida da assiduidade, atrasos, ausências, horas extra e estado dos dispositivos.

Páginas principais:

- `modules/dashboard/index.php`
- `modules/dashboard/widgets.php`
- `modules/relatorios/index.php`
- `modules/relatorios/assiduidade.php`
- `modules/relatorios/horas.php`
- `modules/relatorios/ausencias.php`
- `modules/relatorios/exportar.php`

Indicadores úteis:

- colaboradores presentes hoje
- colaboradores ausentes hoje
- atrasos do dia
- picagens incompletas
- saldo total de banco de horas
- pedidos de férias pendentes
- dispositivos offline

### Colaboradores e Utilizadores

Objetivo: gerir dados pessoais, profissionais e acesso ao sistema.

Páginas principais:

- `modules/colaboradores/index.php`
- `modules/colaboradores/criar.php`
- `modules/colaboradores/editar.php`
- `modules/colaboradores/ver.php`
- `modules/colaboradores/acoes.php`

Dados principais:

- nome
- email
- telefone
- número mecanográfico
- departamento
- cargo
- tipo de contrato
- data de entrada
- estado: ativo, suspenso, inativo
- fotografia
- utilizador associado
- identificador biométrico ou cartão RFID

Tabelas sugeridas:

- `colaboradores`
- `utilizadores`
- `colaborador_documentos`

### Departamentos

Objetivo: organizar colaboradores por áreas, equipas ou centros de custo.

Páginas principais:

- `modules/departamentos/index.php`
- `modules/departamentos/criar.php`
- `modules/departamentos/editar.php`
- `modules/departamentos/acoes.php`

Dados principais:

- nome
- código
- responsável
- estado

Tabelas sugeridas:

- `departamentos`

### Horários, Turnos e Escalas

Objetivo: definir regras de trabalho e associar horários aos colaboradores.

Páginas principais:

- `modules/horarios/index.php`
- `modules/horarios/turnos.php`
- `modules/horarios/escalas.php`
- `modules/horarios/atribuir.php`
- `modules/horarios/acoes.php`

Funcionalidades:

- horário fixo
- horário flexível
- turnos rotativos
- tolerância de entrada e saída
- pausa de almoço
- horas previstas por dia
- atribuição por colaborador, departamento ou período

Tabelas sugeridas:

- `horarios`
- `turnos`
- `turno_dias`
- `colaborador_horarios`
- `escalas`

### Registos de Ponto

Objetivo: guardar entradas, saídas, pausas e correção manual de picagens.

Páginas principais:

- `modules/ponto/index.php`
- `modules/ponto/registos.php`
- `modules/ponto/manual.php`
- `modules/ponto/importar.php`
- `modules/ponto/acoes.php`

Origem dos registos:

- manual
- dispositivo biométrico
- importação CSV/Excel
- API futura

Estados importantes:

- válido
- pendente
- corrigido
- rejeitado
- duplicado

Tabelas sugeridas:

- `registos_ponto`
- `registos_ponto_logs`
- `correcoes_ponto`

### Férias, Faltas e Ausências

Objetivo: gerir pedidos, aprovações e justificações.

Páginas principais:

- `modules/ausencias/index.php`
- `modules/ausencias/ferias.php`
- `modules/ausencias/faltas.php`
- `modules/ausencias/pedidos.php`
- `modules/ausencias/aprovar.php`
- `modules/ausencias/acoes.php`

Tipos de ausência:

- férias
- falta justificada
- falta injustificada
- baixa médica
- licença
- formação
- teletrabalho

Estados:

- pendente
- aprovado
- rejeitado
- cancelado

Tabelas sugeridas:

- `tipos_ausencia`
- `ausencias`
- `ausencia_aprovacoes`

### Banco de Horas

Objetivo: controlar crédito e débito de horas por colaborador.

Páginas principais:

- `modules/banco_horas/index.php`
- `modules/banco_horas/movimentos.php`
- `modules/banco_horas/ajustes.php`
- `modules/banco_horas/acoes.php`

Tipos de movimento:

- crédito por hora extra
- débito por saída antecipada
- ajuste manual
- compensação aprovada
- regularização mensal

Tabelas sugeridas:

- `banco_horas`
- `banco_horas_movimentos`

### Dispositivos Biométricos

Objetivo: preparar a integração com relógios de ponto, terminais biométricos, RFID ou reconhecimento facial.

Páginas principais:

- `modules/dispositivos/index.php`
- `modules/dispositivos/criar.php`
- `modules/dispositivos/sincronizar.php`
- `modules/dispositivos/logs.php`
- `modules/dispositivos/acoes.php`

Dados principais:

- nome
- marca
- modelo
- ip
- porta
- localização
- estado
- última sincronização

Tabelas sugeridas:

- `dispositivos`
- `dispositivo_logs`
- `dispositivo_colaboradores`

Notas:

- Numa primeira fase, guardar apenas a configuração e simular sincronizações.
- Depois, criar importação por CSV.
- Só numa fase posterior integrar SDK/API específica do equipamento.

### Permissões por Utilizador

Objetivo: controlar diretamente o que cada utilizador pode ver e fazer, sem depender do nome de papéis como “Administrador”, “Chefia” ou “Recursos Humanos”.

Páginas principais:

- `modules/permissoes/utilizadores.php`
- `modules/permissoes/permissoes.php`
- `modules/permissoes/acoes.php`

Modelo atual:

- A criação e a edição de utilizadores apresentam uma lista de permissões individuais.
- Cada permissão marcada é gravada em `utilizador_permissoes` com o efeito `permitir`.
- Cada permissão não marcada é gravada em `utilizador_permissoes` com o efeito `negar`, para que o utilizador fique explicitamente configurado.
- A função central `ac_can()` valida primeiro as permissões diretas do utilizador.
- As tabelas de papéis podem continuar na base de dados apenas para compatibilidade com instalações antigas, mas já não devem ser usadas na interface principal.
- O primeiro utilizador criado no sistema recebe todas as permissões diretas.
- O sistema não deve permitir que todos os utilizadores ativos percam, ao mesmo tempo, as permissões `utilizadores.gerir` e `permissoes.gerir`.

Permissões sugeridas:

- `dashboard.ver`
- `colaboradores.ver`
- `colaboradores.criar`
- `colaboradores.editar`
- `departamentos.gerir`
- `horarios.gerir`
- `ponto.ver`
- `ponto.corrigir`
- `ausencias.pedir`
- `ausencias.aprovar`
- `banco_horas.ver`
- `banco_horas.ajustar`
- `relatorios.ver`
- `relatorios.exportar`
- `dispositivos.gerir`
- `permissoes.gerir`

Tabelas sugeridas:

- `permissoes`
- `utilizador_permissoes`
- `logs_sistema`

Tabelas mantidas apenas por compatibilidade:

- `papeis`
- `papel_permissoes`
- `utilizador_papeis`

## Ordem Recomendada de Implementação

1. Base do projeto: `config.php`, sessão, login e includes comuns.
2. Permissões diretas por utilizador.
3. Departamentos.
4. Colaboradores/utilizadores.
5. Horários e turnos.
6. Registos de ponto manuais.
7. Férias, faltas e ausências.
8. Banco de horas.
9. Dashboard e relatórios.
10. Dispositivos biométricos e importações.

## Menu Lateral Sugerido

```text
Dashboard
Colaboradores
Departamentos
Horários e Turnos
Registos de Ponto
Férias e Ausências
Banco de Horas
Relatórios
Dispositivos
Permissões
Configurações
```

## Regras Gerais Para PHP Procedural

- Centralizar a ligação MySQLi em `config.php`.
- Usar `mysqli_prepare` nas queries com dados do utilizador.
- Manter `acoes.php` apenas para processar formulários e redirecionar.
- Evitar HTML dentro de funções grandes.
- Criar funções pequenas em `includes/helpers.php`.
- Validar permissões antes de mostrar páginas sensíveis.
- Guardar logs para operações importantes: correção de ponto, aprovações, ajustes e sincronizações.
- Nunca apagar registos críticos de assiduidade; usar estado `inativo`, `cancelado` ou `anulado`.

## Nomenclatura Recomendada

- Pastas em minúsculas e sem acentos.
- Tabelas no plural: `colaboradores`, `departamentos`, `registos_ponto`.
- Chaves primárias como `id`.
- Chaves estrangeiras como `colaborador_id`, `departamento_id`, `utilizador_id`.
- Datas como `data_criacao`, `data_atualizacao`, `criado_por`, `atualizado_por`.

