<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';
require_once __DIR__ . '/funcoes/departamentos_funcoes.php';

$utilizadorSessao = require_login($conn);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$temEquipas = fe_table_exists($conn, 'equipas');
$temSetorEquipa = $temEquipas && fe_column_exists($conn, 'equipas', 'setor_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // CSRF for modifying actions
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!in_array($acao, ['criar','editar'])) {
        // allow other actions too below
    }

    if (in_array($acao, ['criar', 'editar', 'desativar', 'reativar', 'mudar_equipa'])) {
        if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            redirect_with_message('danger', 'Token CSRF inválido.');
        }
    }

    if ($acao === 'criar') {
        $nome = get_post_value('nome');
        $codigo = get_post_value('codigo') ?: gerar_codigo_equipa();
        $descricao = nullable_text($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 1;

        if ($nome === '') {
            redirect_with_message('danger', 'Preencha o nome da equipa.');
        }

        try {
            if ($temSetorEquipa) {
                $setorPadraoId = equipa_setor_padrao($conn);
                $stmt = mysqli_prepare($conn, 'INSERT INTO equipas (setor_id, nome, codigo, descricao, ativo) VALUES (?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'isssi', $setorPadraoId, $nome, $codigo, $descricao, $ativo);
            } else {
                $stmt = mysqli_prepare($conn, 'INSERT INTO equipas (nome, codigo, descricao, ativo) VALUES (?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'sssi', $nome, $codigo, $descricao, $ativo);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Equipa criada com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível criar a equipa. Verifique se o código já existe.');
        }
    }

    if ($acao === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = get_post_value('nome');
        $codigo = get_post_value('codigo');
        $descricao = nullable_text($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($id <= 0 || $nome === '') {
            redirect_with_message('danger', 'Preencha os campos obrigatórios.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'UPDATE equipas SET nome = ?, codigo = ?, descricao = ?, ativo = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'sssii', $nome, $codigo, $descricao, $ativo, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Equipa atualizada com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível atualizar a equipa. Verifique se o código já existe.');
        }
    }
    if ($acao === 'desativar') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            redirect_with_message('danger', 'Equipa inválida.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'UPDATE equipas SET ativo = 0 WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Equipa desativada com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível desativar a equipa.');
        }
    }

    if ($acao === 'reativar') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            redirect_with_message('danger', 'Equipa inválida.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'UPDATE equipas SET ativo = 1 WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Equipa reativada com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível reativar a equipa.');
        }
    }

    if ($acao === 'mudar_equipa') {
        // mass move employees between teams, preserves history and uses transaction
        $ids = $_POST['funcionario_ids'] ?? [];
        $targetEquipa = (int) ($_POST['target_equipa_id'] ?? 0);
        $dataEfeito = nullable_text($_POST['data_efeito'] ?? date('Y-m-d'));
        $motivo = nullable_text($_POST['motivo'] ?? '');

        if (!is_array($ids) || $targetEquipa <= 0 || empty($ids)) {
            redirect_with_message('danger', 'Parâmetros inválidos para mudança de equipa.');
        }

        // begin transaction
        mysqli_begin_transaction($conn);
        try {
            $insertStmt = mysqli_prepare($conn, 'INSERT INTO equipa_mudancas (funcionario_id, equipa_antiga_id, equipa_nova_id, data_efeito, motivo, utilizador_responsavel_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
            $updateStmt = mysqli_prepare($conn, 'UPDATE funcionarios SET equipa_id = ? WHERE id = ?');

            foreach ($ids as $fid) {
                $fid = (int) $fid;
                if ($fid <= 0) continue;

                // get current equipa
                $s = mysqli_prepare($conn, 'SELECT equipa_id FROM funcionarios WHERE id = ?');
                mysqli_stmt_bind_param($s, 'i', $fid);
                mysqli_stmt_execute($s);
                $r = mysqli_stmt_get_result($s);
                $row = mysqli_fetch_assoc($r);
                mysqli_stmt_close($s);

                $antiga = isset($row['equipa_id']) ? (int) $row['equipa_id'] : null;

                $fidVar = $fid;
                $antigaVar = $antiga;
                $targetVar = $targetEquipa;
                $dataEfeitoVar = $dataEfeito ?: date('Y-m-d');
                $motivoVar = $motivo;
                $userVar = $utilizadorSessao['id'];
                mysqli_stmt_bind_param($insertStmt, 'iiissi', $fidVar, $antigaVar, $targetVar, $dataEfeitoVar, $motivoVar, $userVar);
                mysqli_stmt_execute($insertStmt);

                // if effect date <= today then update current equipa
                if ($dataEfeito <= date('Y-m-d')) {
                    mysqli_stmt_bind_param($updateStmt, 'ii', $targetEquipa, $fid);
                    mysqli_stmt_execute($updateStmt);
                }
            }

            mysqli_stmt_close($insertStmt);
            mysqli_stmt_close($updateStmt);
            mysqli_commit($conn);

            redirect_with_message('success', 'Mudança de equipa aplicada com sucesso.');
        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($conn);
            redirect_with_message('danger', 'Falha ao executar mudança de equipa.');
        }
    }
}

$departamentos = [];
$sql = "SELECT
            d.id,
            d.nome,
            d.codigo,
            d.descricao,
            d.ativo,
            d.created_at,
            SUM(CASE WHEN u.estado = 'ativo' THEN 1 ELSE 0 END) AS total_ativos
        FROM equipas d
        LEFT JOIN funcionarios u ON u.equipa_id = d.id
        GROUP BY d.id, d.nome, d.codigo, d.descricao, d.ativo, d.created_at
        ORDER BY d.nome ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $departamentos[] = $row;
}
mysqli_stmt_close($stmt);

// lista de equipas ativas para dropdowns
$equipasList = [];
$stmt = mysqli_prepare($conn, 'SELECT id, nome FROM equipas WHERE ativo = 1 ORDER BY nome ASC');
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($r = mysqli_fetch_assoc($res)) {
    $equipasList[] = $r;
}
mysqli_stmt_close($stmt);

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
                        <h3 class="fw-bold mb-3">Equipas</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="principal.php">
                                    <i class="icon-home"></i>
                                </a>
                            </li>
                            <li class="separator">
                                <i class="icon-arrow-right"></i>
                            </li>
                            <li class="nav-item">
                                <a href="departamentos.php">Equipas</a>
                            </li>
                        </ul>
                    </div>

                    <?php if ($alertMessage !== ''): ?>
                        <div class="alert alert-<?php echo e($alertType ?: 'info'); ?> alert-dismissible fade show" role="alert">
                            <?php echo e($alertMessage); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <h4 class="card-title">Lista de equipas</h4>
                                <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#modalCriarEquipa">
                                    <i class="fa fa-plus"></i>
                                    Adicionar equipa
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-equipas" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Nome</th>
                                            <th>Descrição</th>
                                            <th>Funcionários ativos</th>
                                            <th style="width: 200px">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($departamentos as $departamento): ?>
                                            <tr>
                                                <td><?php echo e($departamento['codigo'] ?: '-'); ?></td>
                                                <td><?php echo e($departamento['nome']); ?></td>
                                                <td><?php echo e($departamento['descricao'] ?: '-'); ?></td>
                                                <td><?php echo (int) $departamento['total_ativos']; ?></td>
                                                <td>
                                                    <div class="form-button-action d-flex gap-1">
                                                        <button type="button" class="btn btn-link btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#modalEditarEquipa<?php echo (int) $departamento['id']; ?>" title="Editar">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-link btn-info btn-lg" data-bs-toggle="modal" data-bs-target="#modalGerirMembros<?php echo (int) $departamento['id']; ?>" title="Gerir membros">
                                                            <i class="fa fa-users"></i>
                                                        </button>
                                                        <?php if ((int)$departamento['ativo'] === 1): ?>
                                                            <button type="button" class="btn btn-link btn-warning btn-lg" data-bs-toggle="modal" data-bs-target="#modalDesativarEquipa<?php echo (int) $departamento['id']; ?>" title="Desativar">
                                                                <i class="fa fa-user-times"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <form method="post" style="display:inline">
                                                                <input type="hidden" name="acao" value="reativar">
                                                                <input type="hidden" name="id" value="<?php echo (int) $departamento['id']; ?>">
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

    <div class="modal fade" id="modalCriarEquipa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="acao" value="criar">
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Adicionar equipa</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Código (opcional)</label>
                        <input type="text" name="codigo" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-control" required>
                        <div class="invalid-feedback">Indique o nome da equipa.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($departamentos as $departamento): ?>
        <div class="modal fade" id="modalEditarEquipa<?php echo (int) $departamento['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="post" class="modal-content needs-validation" novalidate>
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" value="<?php echo (int) $departamento['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Editar equipa</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Código *</label>
                            <input type="text" name="codigo" class="form-control" value="<?php echo e($departamento['codigo']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nome *</label>
                            <input type="text" name="nome" class="form-control" value="<?php echo e($departamento['nome']); ?>" required>
                            <div class="invalid-feedback">Indique o nome da equipa.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" class="form-control" rows="3"><?php echo e($departamento['descricao']); ?></textarea>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="ativo" id="ativo<?php echo (int)$departamento['id']; ?>" <?php echo (int)$departamento['ativo'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ativo<?php echo (int)$departamento['id']; ?>">Ativa</label>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-primary">Guardar alterações</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="modalDesativarEquipa<?php echo (int) $departamento['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="post" class="modal-content" onsubmit="return confirm('Tem a certeza que pretende desativar esta equipa?');">
                    <input type="hidden" name="acao" value="desativar">
                    <input type="hidden" name="id" value="<?php echo (int) $departamento['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Desativar equipa</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Vai desativar a equipa <strong><?php echo e($departamento['nome']); ?></strong>. Os registos históricos não serão alterados.</p>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-warning">Desativar</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="modalGerirMembros<?php echo (int) $departamento['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <?php
                // carregar funcionarios ativos desta equipa
                $funcs = [];
                $s = mysqli_prepare($conn, 'SELECT id, nome, numero_mecanografico FROM funcionarios WHERE equipa_id = ? AND estado = "ativo" ORDER BY nome ASC');
                mysqli_stmt_bind_param($s, 'i', $departamento['id']);
                mysqli_stmt_execute($s);
                $resf = mysqli_stmt_get_result($s);
                while ($fr = mysqli_fetch_assoc($resf)) {
                    $funcs[] = $fr;
                }
                mysqli_stmt_close($s);
                ?>
                <form method="post" class="modal-content">
                    <input type="hidden" name="acao" value="mudar_equipa">
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Gerir membros - <?php echo e($departamento['nome']); ?></h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Selecionar funcionários</label>
                            <div class="mb-2"><input type="checkbox" id="select_all_<?php echo (int)$departamento['id']; ?>"> Selecionar todos</div>
                            <div style="max-height:300px; overflow:auto;">
                                <?php foreach ($funcs as $f): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="funcionario_ids[]" value="<?php echo (int)$f['id']; ?>" id="f_<?php echo (int)$f['id']; ?>">
                                        <label class="form-check-label" for="f_<?php echo (int)$f['id']; ?>"><?php echo e($f['nome'] . ' (' . ($f['numero_mecanografico'] ?: '-').')'); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Equipa destino *</label>
                            <select name="target_equipa_id" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($equipasList as $eq): ?>
                                    <?php if ((int)$eq['id'] !== (int)$departamento['id']): ?>
                                        <option value="<?php echo (int)$eq['id']; ?>"><?php echo e($eq['nome']); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Data de efeito</label>
                            <input type="date" name="data_efeito" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Motivo (opcional)</label>
                            <textarea name="motivo" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-primary">Aplicar mudança</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <?php include 'includes/scripts.php'; ?>
    <script>
        $(document).ready(function () {
            $('#tabela-equipas').DataTable({
                pageLength: 10,
                language: {
                    search: 'Pesquisar:',
                    lengthMenu: 'Mostrar _MENU_ registos',
                    info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
                    infoEmpty: 'Sem registos',
                    zeroRecords: 'Nenhuma equipa encontrada',
                    "paginate": {
                        "first": "Primeiro",
                        "last": "Último",
                        next: 'Seguinte',
                        previous: 'Anterior'
                    }
                }
            });

            $('.needs-validation').on('submit', function (event) {
                if (!this.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                $(this).addClass('was-validated');
            });
            // select all handling for member management modals
            <?php foreach ($departamentos as $departamento): ?>
            $('#select_all_<?php echo (int)$departamento['id']; ?>').on('change', function () {
                var checked = $(this).is(':checked');
                $('#modalGerirMembros<?php echo (int)$departamento['id']; ?>').find('input[name="funcionario_ids[]"]').prop('checked', checked);
            });
            <?php endforeach; ?>
   