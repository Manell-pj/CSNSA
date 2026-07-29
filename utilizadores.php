<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/listagem_verificacao.php';
require_once __DIR__ . '/funcoes/utilizadores_funcoes.php';

$utilizadorSessao = require_login($conn);
ac_require_permission($conn, $utilizadorSessao, 'utilizadores.gerir');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$permissoesDisponiveis = [];
$resPermissoes = mysqli_query($conn, 'SELECT id, codigo, nome FROM permissoes ORDER BY codigo ASC');
if ($resPermissoes) {
    while ($row = mysqli_fetch_assoc($resPermissoes)) {
        $permissoesDisponiveis[] = $row;
    }
}

$permissoesIdsPorCodigo = permissoes_ids_por_codigo($permissoesDisponiveis);
$fotoUtilizadorReady = utilizadores_foto_column_ready($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    ac_require_permission($conn, $utilizadorSessao, 'utilizadores.gerir');

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        redirect_with_message('danger', 'Token CSRF inválido.');
    }

    if ($acao === 'criar') {
        $nome = get_post_value('nome');
        $email = get_post_value('email');
        $password = $_POST['password'] ?? '';
        $estado = get_post_value('estado') ?: 'ativo';
        $permissoes = permissoes_postadas();
        ac_require_permission($conn, $utilizadorSessao, 'permissoes.gerir');

        if ($nome === '' || $email === '' || $password === '') {
            redirect_with_message('danger', 'Preencha nome, email e palavra-passe.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect_with_message('danger', 'Introduza um email válido.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $foto = $fotoUtilizadorReady ? utilizadores_guardar_foto($_FILES['foto'] ?? null) : null;
        } catch (RuntimeException $e) {
            redirect_with_message('danger', $e->getMessage());
        }

        mysqli_begin_transaction($conn);

        try {
            if ($fotoUtilizadorReady) {
                $stmt = mysqli_prepare($conn, 'INSERT INTO utilizadores (nome, email, password_hash, estado, foto) VALUES (?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'sssss', $nome, $email, $passwordHash, $estado, $foto);
            } else {
                $stmt = mysqli_prepare($conn, 'INSERT INTO utilizadores (nome, email, password_hash, estado) VALUES (?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'ssss', $nome, $email, $passwordHash, $estado);
            }
            mysqli_stmt_execute($stmt);
            $novoUtilizadorId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            guardar_permissoes_utilizador($conn, $novoUtilizadorId, $permissoes, $permissoesDisponiveis);

            mysqli_commit($conn);
            redirect_with_message('success', 'Utilizador criado com sucesso.');
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            if ($fotoUtilizadorReady) {
                utilizadores_apagar_foto($foto ?? '');
            }
            redirect_with_message('danger', 'Não foi possível criar o utilizador. Verifique se o email já existe.');
        }
    }

    if ($acao === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = get_post_value('nome');
        $email = get_post_value('email');
        $password = $_POST['password'] ?? '';
        $estado = get_post_value('estado') ?: 'ativo';
        $permissoes = permissoes_postadas();
        ac_require_permission($conn, $utilizadorSessao, 'permissoes.gerir');

        if ($id <= 0 || $nome === '' || $email === '') {
            redirect_with_message('danger', 'Preencha nome e email.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect_with_message('danger', 'Introduza um email válido.');
        }

        $mantemGestao = $estado === 'ativo'
            && tem_permissao_id($permissoes, $permissoesIdsPorCodigo, 'utilizadores.gerir')
            && tem_permissao_id($permissoes, $permissoesIdsPorCodigo, 'permissoes.gerir');

        if (!$mantemGestao && ac_active_admin_count($conn, $id) === 0) {
            redirect_with_message('danger', 'Não é possível deixar o sistema sem utilizador ativo com permissões de gestão.');
        }

        if ((int) $utilizadorSessao['id'] === $id
            && (!$mantemGestao || !tem_permissao_id($permissoes, $permissoesIdsPorCodigo, 'permissoes.gerir'))
            && ($_POST['confirmar_retirar_admin'] ?? '') !== '1') {
            redirect_with_message('danger', 'Confirme explicitamente que pretende retirar as suas permissões administrativas.');
        }

        mysqli_begin_transaction($conn);

        try {
            $foto = null;
            if ($fotoUtilizadorReady) {
                $stmtFoto = mysqli_prepare($conn, 'SELECT foto FROM utilizadores WHERE id = ? LIMIT 1');
                mysqli_stmt_bind_param($stmtFoto, 'i', $id);
                mysqli_stmt_execute($stmtFoto);
                $resFoto = mysqli_stmt_get_result($stmtFoto);
                $rowFoto = mysqli_fetch_assoc($resFoto);
                mysqli_stmt_close($stmtFoto);
                $foto = utilizadores_guardar_foto($_FILES['foto'] ?? null, $rowFoto['foto'] ?? '');
            }

            if ($password !== '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                if ($fotoUtilizadorReady) {
                    $stmt = mysqli_prepare($conn, 'UPDATE utilizadores SET nome = ?, email = ?, password_hash = ?, estado = ?, foto = ? WHERE id = ?');
                    mysqli_stmt_bind_param($stmt, 'sssssi', $nome, $email, $passwordHash, $estado, $foto, $id);
                } else {
                    $stmt = mysqli_prepare($conn, 'UPDATE utilizadores SET nome = ?, email = ?, password_hash = ?, estado = ? WHERE id = ?');
                    mysqli_stmt_bind_param($stmt, 'ssssi', $nome, $email, $passwordHash, $estado, $id);
                }
            } else {
                if ($fotoUtilizadorReady) {
                    $stmt = mysqli_prepare($conn, 'UPDATE utilizadores SET nome = ?, email = ?, estado = ?, foto = ? WHERE id = ?');
                    mysqli_stmt_bind_param($stmt, 'ssssi', $nome, $email, $estado, $foto, $id);
                } else {
                    $stmt = mysqli_prepare($conn, 'UPDATE utilizadores SET nome = ?, email = ?, estado = ? WHERE id = ?');
                    mysqli_stmt_bind_param($stmt, 'sssi', $nome, $email, $estado, $id);
                }
            }

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            guardar_permissoes_utilizador($conn, $id, $permissoes, $permissoesDisponiveis);

            mysqli_commit($conn);
            redirect_with_message('success', 'Utilizador atualizado com sucesso.');
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $mensagem = $e instanceof RuntimeException ? $e->getMessage() : 'Nao foi possivel atualizar o utilizador.';
            redirect_with_message('danger', $mensagem);
        }
    }

    if ($acao === 'remover') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            redirect_with_message('danger', 'Utilizador inválido.');
        }

        if ($id === (int) $utilizadorSessao['id']) {
            redirect_with_message('danger', 'Não pode remover o utilizador com sessão iniciada.');
        }

        try {
            $rowFoto = [];
            if ($fotoUtilizadorReady) {
                $stmtFoto = mysqli_prepare($conn, 'SELECT foto FROM utilizadores WHERE id = ? LIMIT 1');
                mysqli_stmt_bind_param($stmtFoto, 'i', $id);
                mysqli_stmt_execute($stmtFoto);
                $resFoto = mysqli_stmt_get_result($stmtFoto);
                $rowFoto = mysqli_fetch_assoc($resFoto) ?: [];
                mysqli_stmt_close($stmtFoto);
            }

            $stmt = mysqli_prepare($conn, 'DELETE FROM utilizadores WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($fotoUtilizadorReady) {
                utilizadores_apagar_foto($rowFoto['foto'] ?? '');
            }

            redirect_with_message('success', 'Utilizador removido com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível remover este utilizador.');
        }
    }
}

$utilizadores = [];
$fotoSelect = $fotoUtilizadorReady ? 'u.foto,' : 'NULL AS foto,';
$fotoGroup = $fotoUtilizadorReady ? ', u.foto' : '';
$sql = "SELECT u.id, u.nome, u.email, u.estado, u.ultimo_login_at, $fotoSelect
               COUNT(CASE WHEN upm.efeito = 'permitir' THEN 1 END) AS total_permissoes
        FROM utilizadores u
        LEFT JOIN utilizador_permissoes upm ON upm.utilizador_id = u.id
        GROUP BY u.id, u.nome, u.email, u.estado, u.ultimo_login_at $fotoGroup
        ORDER BY u.nome ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $utilizadores[] = $row;
}
mysqli_stmt_close($stmt);

$permissoesPorUtilizador = [];
$efeitosPorUtilizador = [];
$contagemPermissoesDiretas = [];
$totalPermissoesDisponiveis = count($permissoesDisponiveis);
$res = mysqli_query($conn, 'SELECT utilizador_id, permissao_id, efeito FROM utilizador_permissoes');
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $uid = (int) $row['utilizador_id'];
        $pid = (int) $row['permissao_id'];
        $efeitosPorUtilizador[$uid][$pid] = $row['efeito'];
        $contagemPermissoesDiretas[$uid][$pid] = true;
        if ($row['efeito'] === 'permitir') {
            $permissoesPorUtilizador[$uid][$pid] = true;
        }
    }
}

$res = mysqli_query($conn, 'SELECT up.utilizador_id, pp.permissao_id FROM utilizador_papeis up INNER JOIN papel_permissoes pp ON pp.papel_id = up.papel_id');
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $uid = (int) $row['utilizador_id'];
        $pid = (int) $row['permissao_id'];

        if (count($contagemPermissoesDiretas[$uid] ?? []) >= $totalPermissoesDisponiveis) {
            continue;
        }

        if (($efeitosPorUtilizador[$uid][$pid] ?? '') === 'negar') {
            unset($permissoesPorUtilizador[$uid][$pid]);
            continue;
        }

        if (!isset($efeitosPorUtilizador[$uid][$pid])) {
            $permissoesPorUtilizador[$uid][$pid] = true;
        }
    }
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
                        <h3 class="fw-bold mb-3">Utilizadores</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="principal.php"><i class="icon-home"></i></a>
                            </li>
                            <li class="separator"><i class="icon-arrow-right"></i></li>
                            <li class="nav-item"><a href="utilizadores.php">Acessos</a></li>
                        </ul>
                    </div>

                    <?php if ($alertMessage !== ''): ?>
                        <div class="alert alert-<?php echo e($alertType ?: 'info'); ?> alert-dismissible fade show" role="alert">
                            <?php echo e($alertMessage); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!$fotoUtilizadorReady): ?>
                        <div class="alert alert-warning" role="alert">
                            A coluna foto ainda nao existe na tabela utilizadores. A gestao de fotografias fica disponivel depois de atualizar a base de dados.
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <h4 class="card-title">Contas de acesso</h4>
                                <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#modalCriarUtilizador">
                                    <i class="fa fa-plus"></i>
                                    Novo utilizador
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-utilizadores" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Foto</th>
                                            <th>Nome</th>
                                            <th>Email</th>
                                            <th>Permissões</th>
                                            <th>Último acesso</th>
                                            <th>Estado</th>
                                            <th style="width: 120px">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($utilizadores as $utilizador): ?>
                                            <tr>
                                                <td><?php render_utilizador_avatar($utilizador); ?></td>
                                                <td><?php echo e($utilizador['nome']); ?></td>
                                                <td><?php echo e($utilizador['email']); ?></td>
                                                <td><?php echo (int) $utilizador['total_permissoes']; ?></td>
                                                <td><?php echo $utilizador['ultimo_login_at'] ? e(date('d/m/Y H:i', strtotime($utilizador['ultimo_login_at']))) : '-'; ?></td>
                                                <td>
                                                    <?php $badgeClass = $utilizador['estado'] === 'ativo' ? 'success' : ($utilizador['estado'] === 'suspenso' ? 'warning' : 'secondary'); ?>
                                                    <span class="badge badge-<?php echo e($badgeClass); ?>"><?php echo e(ucfirst($utilizador['estado'])); ?></span>
                                                </td>
                                                <td>
                                                    <div class="form-button-action">
                                                        <button type="button" class="btn btn-link btn-info btn-lg" data-bs-toggle="modal" data-bs-target="#modalVerificarUtilizador<?php echo (int) $utilizador['id']; ?>" title="Verificar campos">
                                                            <i class="fa fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-link btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#modalEditarUtilizador<?php echo (int) $utilizador['id']; ?>" title="Editar">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
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

    <div class="modal fade" id="modalCriarUtilizador" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="post" enctype="multipart/form-data" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="acao" value="criar">
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Novo utilizador</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <?php if ($fotoUtilizadorReady): ?>
                        <div class="mb-3">
                            <label class="form-label">Fotografia</label>
                            <input type="file" name="foto" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <small class="form-text text-muted">JPG, PNG ou WebP ate 3 MB.</small>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Palavra-passe *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Permissões</label>
                        <div class="row">
                            <?php render_permissoes_checkboxes($permissoesDisponiveis); ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="ativo">Ativo</option>
                            <option value="suspenso">Suspenso</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($utilizadores as $utilizador): ?>
        <?php lv_render_verification_modal('modalVerificarUtilizador' . (int) $utilizador['id'], 'Verificar utilizador - ' . ($utilizador['nome'] ?? ''), $utilizador, [], ['password_hash']); ?>

        <div class="modal fade" id="modalEditarUtilizador<?php echo (int) $utilizador['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form method="post" enctype="multipart/form-data" class="modal-content needs-validation" novalidate>
                    <input type="hidden" name="id" value="<?php echo (int) $utilizador['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Editar utilizador</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nome *</label>
                            <input type="text" name="nome" class="form-control" value="<?php echo e($utilizador['nome']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" value="<?php echo e($utilizador['email']); ?>" required>
                        </div>
                        <?php if ($fotoUtilizadorReady): ?>
                            <div class="mb-3">
                                <label class="form-label">Fotografia</label>
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <?php render_utilizador_avatar($utilizador, 'lg'); ?>
                                    <span class="text-muted">Carregue uma nova imagem para substituir a atual.</span>
                                </div>
                                <input type="file" name="foto" class="form-control" accept="image/jpeg,image/png,image/webp">
                                <small class="form-text text-muted">JPG, PNG ou WebP ate 3 MB.</small>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Nova palavra-passe</label>
                            <input type="password" name="password" class="form-control" placeholder="Manter atual se ficar vazio">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Permissões</label>
                            <div class="row">
                                <?php render_permissoes_checkboxes($permissoesDisponiveis, $permissoesPorUtilizador[(int) $utilizador['id']] ?? []); ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <option value="ativo" <?php echo $utilizador['estado'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="suspenso" <?php echo $utilizador['estado'] === 'suspenso' ? 'selected' : ''; ?>>Suspenso</option>
                                <option value="inativo" <?php echo $utilizador['estado'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                            </select>
                        </div>
                        <?php if ((int) $utilizador['id'] === (int) $utilizadorSessao['id']): ?>
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" name="confirmar_retirar_admin" value="1">
                                <span class="form-check-label">Confirmo alterações que possam retirar o meu acesso administrativo</span>
                            </label>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer border-0">
                        <?php if ((int) $utilizador['id'] !== (int) $utilizadorSessao['id']): ?>
                            <button type="submit" name="acao" value="remover" class="btn btn-danger me-auto" formnovalidate onclick="return confirm('Tem a certeza que pretende remover este utilizador?');">Remover</button>
                        <?php endif; ?>
                        <button type="submit" name="acao" value="editar" class="btn btn-primary">Guardar alterações</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

    <?php endforeach; ?>

    <?php include 'includes/scripts.php'; ?>
    <script>
        $(document).ready(function () {
            $('#tabela-utilizadores').DataTable({
                pageLength: 10,
                language: {
                    search: 'Pesquisar:',
                    lengthMenu: 'Mostrar _MENU_ registos',
                    info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
                    infoEmpty: 'Sem registos',
                    zeroRecords: 'Nenhum utilizador encontrado',
                    paginate: {
                        first: 'Primeiro',
                        last: 'Último',
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
        });
    </script>
</body>

</html>
