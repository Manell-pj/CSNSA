<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';
require_once __DIR__ . '/includes/listagem_verificacao.php';
require_once __DIR__ . '/funcoes/turnos_funcoes.php';

$utilizadorSessao = require_login($conn);
ac_require_permission($conn, $utilizadorSessao, 'turnos.gerir');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$temTabelaTurnoPeriodos = fe_table_exists($conn, 'turno_periodos');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ac_require_permission($conn, $utilizadorSessao, 'turnos.gerir');
    $acao = $_POST['acao'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        redirect_with_message('danger', 'Token CSRF inválido.');
    }

    if ($acao === 'criar') {
        $nome = get_post_value('nome');
        $codigo = get_post_value('codigo') ?: null;
        $inicioPausa = null;
        $fimPausa = null;
        $horaEntrada = nullable_time($_POST['hora_entrada'] ?? '');
        $horaSaida = nullable_time($_POST['hora_saida'] ?? '');
        $toleranciaAtraso = (int) ($_POST['tolerancia_entrada_min'] ?? 0);
        $toleranciaSaida = (int) ($_POST['tolerancia_saida_min'] ?? 0);
        $horasPrevistas = (float) ($_POST['horas_previstas'] ?? 8);
        $turnoNoturno = 0;
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($nome === '') {
            redirect_with_message('danger', 'Preencha o nome do turno.');
        }
        if ($horaEntrada === null || $horaSaida === null) {
            redirect_with_message('danger', 'Preencha a hora de entrada e a hora de saida.');
        }

        $periodos = [['inicio' => $horaEntrada, 'fim' => $horaSaida]];

        try {
            $stmt = mysqli_prepare($conn, 'INSERT INTO turnos (nome, codigo, hora_entrada, hora_saida, inicio_pausa, fim_pausa, tolerancia_entrada_min, tolerancia_saida_min, horas_previstas, turno_noturno, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'ssssssiidii', $nome, $codigo, $horaEntrada, $horaSaida, $inicioPausa, $fimPausa, $toleranciaAtraso, $toleranciaSaida, $horasPrevistas, $turnoNoturno, $ativo);
            mysqli_stmt_execute($stmt);
            $turnoId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            if ($temTabelaTurnoPeriodos) {
                excluir_periodos_turno($conn, $turnoId);
                salvar_periodos_turno($conn, $turnoId, $periodos, $toleranciaAtraso, $toleranciaSaida, $horasPrevistas);
            }

            redirect_with_message('success', 'Turno criado com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível criar o turno. Verifique se o código já existe.');
        }
    }

    if ($acao === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = get_post_value('nome');
        $codigo = get_post_value('codigo') ?: null;
        $inicioPausa = null;
        $fimPausa = null;
        $horaEntrada = nullable_time($_POST['hora_entrada'] ?? '');
        $horaSaida = nullable_time($_POST['hora_saida'] ?? '');
        $toleranciaAtraso = (int) ($_POST['tolerancia_entrada_min'] ?? 0);
        $toleranciaSaida = (int) ($_POST['tolerancia_saida_min'] ?? 0);
        $horasPrevistas = (float) ($_POST['horas_previstas'] ?? 8);
        $turnoNoturno = 0;
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($id <= 0 || $nome === '') {
            redirect_with_message('danger', 'Preencha os campos obrigatórios.');
        }
        if ($horaEntrada === null || $horaSaida === null) {
            redirect_with_message('danger', 'Preencha a hora de entrada e a hora de saida.');
        }

        $periodos = [['inicio' => $horaEntrada, 'fim' => $horaSaida]];

        try {
            $stmt = mysqli_prepare($conn, 'UPDATE turnos SET nome = ?, codigo = ?, hora_entrada = ?, hora_saida = ?, inicio_pausa = ?, fim_pausa = ?, tolerancia_entrada_min = ?, tolerancia_saida_min = ?, horas_previstas = ?, turno_noturno = ?, ativo = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'ssssssiidiii', $nome, $codigo, $horaEntrada, $horaSaida, $inicioPausa, $fimPausa, $toleranciaAtraso, $toleranciaSaida, $horasPrevistas, $turnoNoturno, $ativo, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($temTabelaTurnoPeriodos) {
                excluir_periodos_turno($conn, $id);
                salvar_periodos_turno($conn, $id, $periodos, $toleranciaAtraso, $toleranciaSaida, $horasPrevistas);
            }

            redirect_with_message('success', 'Turno atualizado com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível atualizar o turno. Verifique se o código já existe.');
        }
    }

    if ($acao === 'remover') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            redirect_with_message('danger', 'Turno inválido.');
        }

        $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM horarios_turno WHERE turno_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ((int) $row['total'] > 0) {
            redirect_with_message('danger', 'Não é possível remover este turno porque existem funcionários associados.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'DELETE FROM turnos WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Turno removido com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível remover o turno.');
        }
    }

}

$turnos = [];
$sql = "SELECT
            t.id, t.nome, t.codigo, t.hora_entrada, t.hora_saida,
            t.tolerancia_entrada_min, t.tolerancia_saida_min, t.horas_previstas,
            t.ativo, t.created_at, t.updated_at
        FROM turnos t
        ORDER BY t.nome ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $turnos[] = $row;
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
                        <h3 class="fw-bold mb-3">Turnos</h3>
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
                                <a href="turnos.php">Turnos</a>
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
                                <h4 class="card-title">Lista de turnos</h4>
                                <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#modalCriarTurno">
                                    <i class="fa fa-plus"></i>
                                    Adicionar turno
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-turnos" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Nome</th>
                                            <th>Horário</th>
                                            <th>Tolerância</th>
                                            <th>Estado</th>
                                            <th style="width: 160px">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($turnos as $turno): ?>
                                            <tr>
                                                <td><?php echo e($turno['codigo'] ?: '-'); ?></td>
                                                <td><?php echo e($turno['nome']); ?></td>
                                                <td><?php echo e(sprintf('%s-%s', substr($turno['hora_entrada'], 0, 5), substr($turno['hora_saida'], 0, 5))); ?></td>
                                                <td><?php echo (int) $turno['tolerancia_entrada_min']; ?> min</td>
                                                <td>
                                                    <?php if ((int) $turno['ativo'] === 1): ?>
                                                        <span class="badge badge-success">Ativo</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Inativo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="form-button-action">
                                                        <button type="button" class="btn btn-link btn-info btn-lg" data-bs-toggle="modal" data-bs-target="#modalVerificarTurno<?php echo (int) $turno['id']; ?>" title="Verificar campos">
                                                            <i class="fa fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-link btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#modalEditarTurno<?php echo (int) $turno['id']; ?>" title="Editar">
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

    <div class="modal fade" id="modalCriarTurno" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="post" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="acao" value="criar">
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Adicionar turno</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <?php $turno = []; include __DIR__ . '/turnos_form_campos.php'; ?>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($turnos as $turno): ?>
        <?php lv_render_verification_modal('modalVerificarTurno' . (int) $turno['id'], 'Verificar turno - ' . ($turno['nome'] ?? ''), $turno); ?>

        <div class="modal fade" id="modalEditarTurno<?php echo (int) $turno['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form method="post" class="modal-content needs-validation" novalidate>
                    <input type="hidden" name="id" value="<?php echo (int) $turno['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Editar turno</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php include __DIR__ . '/turnos_form_campos.php'; ?>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" name="acao" value="remover" class="btn btn-danger me-auto" formnovalidate onclick="return confirm('Tem a certeza que pretende remover este turno?');">Remover</button>
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
            $('#tabela-turnos').DataTable({
                pageLength: 10,
                language: {
                    search: 'Pesquisar:',
                    lengthMenu: 'Mostrar _MENU_ registos',
                    info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
                    infoEmpty: 'Sem registos',
                    zeroRecords: 'Nenhum turno encontrado',
                    paginate: {
                        first: 'Primeiro',
                        last: 'Último',
                        next: 'Seguinte',
                        previous: 'Anterior'
                    }
                }
            });

            function timeToMinutes(value) {
                var parts = String(value || '').split(':');
                if (parts.length < 2) {
                    return null;
                }

                var hours = parseInt(parts[0], 10);
                var minutes = parseInt(parts[1], 10);
                if (isNaN(hours) || isNaN(minutes)) {
                    return null;
                }

                return (hours * 60) + minutes;
            }

            function updateHorasPrevistas($form) {
                var entrada = timeToMinutes($form.find('.js-turno-hora-entrada').val());
                var saida = timeToMinutes($form.find('.js-turno-hora-saida').val());
                var $horas = $form.find('.js-turno-horas-previstas');

                if (entrada === null || saida === null) {
                    return;
                }

                var minutos = saida - entrada;
                if (minutos <= 0) {
                    minutos += 24 * 60;
                }

                $horas.val((minutos / 60).toFixed(2));
            }

            $('.js-turno-hora-entrada, .js-turno-hora-saida').on('change input', function () {
                updateHorasPrevistas($(this).closest('form'));
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
