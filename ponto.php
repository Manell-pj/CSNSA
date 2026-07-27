<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';
require_once __DIR__ . '/funcoes/ponto_funcoes.php';

$utilizadorSessao = require_login($conn);
ac_require_permission($conn, $utilizadorSessao, 'ponto.consultar');

$missingTables = [];

foreach (['funcionarios', 'registos_ponto'] as $table) {
    if (!fe_table_exists($conn, $table)) {
        $missingTables[] = $table;
    }
}

$temFuncionarioRegisto = empty($missingTables) && fe_column_exists($conn, 'registos_ponto', 'funcionario_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['acao'] ?? ''), ['registar_ponto','registar_por_codigo'], true)) {
    ac_require_permission($conn, $utilizadorSessao, 'ponto.corrigir');
    if (!$temFuncionarioRegisto) {
        redirect_with_message('danger', 'Execute a migração antes de registar ponto por funcionário.');
    }
    $acao = $_POST['acao'] ?? '';

    // suporte a registo por codigo de picagem
    if ($acao === 'registar_por_codigo') {
        $codigo = trim($_POST['codigo'] ?? '');
        $tipo = $_POST['tipo'] ?? '';
        $dataHora = trim($_POST['data_hora'] ?? '');
        $observacoes = trim($_POST['observacoes'] ?? '');

        if ($codigo === '' || $dataHora === '' || $tipo === '') {
            redirect_with_message('danger', 'Código, movimento e data/hora são obrigatórios.');
        }

        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $dataHora);
        if (!$dt) {
            redirect_with_message('danger', 'Data/hora inválida.');
        }

        $dataHoraSql = $dt->format('Y-m-d H:i:s');
        $dataReferencia = $dt->format('Y-m-d');
        $observacoes = $observacoes === '' ? null : $observacoes;

        // Localizar funcionário por código (hash se disponível)
        $stmt = mysqli_prepare($conn, 'SELECT id, codigo_picagem_hash, codigo_picagem_tentativas, codigo_picagem_bloqueado_ate FROM funcionarios WHERE estado = "ativo" AND codigo_picagem = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $codigo);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        $funcionarioId = 0;
        if ($row) {
            if (!empty($row['codigo_picagem_bloqueado_ate']) && strtotime($row['codigo_picagem_bloqueado_ate']) > time()) {
                redirect_with_message('danger', 'Código temporariamente bloqueado.');
            }

            // if hash exists, verify; else fallback to plaintext match
            if (!empty($row['codigo_picagem_hash'])) {
                if (password_verify($codigo, $row['codigo_picagem_hash'])) {
                    $funcionarioId = (int)$row['id'];
                    // reset attempts
                    $s2 = mysqli_prepare($conn, 'UPDATE funcionarios SET codigo_picagem_tentativas = 0, codigo_picagem_bloqueado_ate = NULL WHERE id = ?');
                    mysqli_stmt_bind_param($s2, 'i', $funcionarioId);
                    mysqli_stmt_execute($s2);
                    mysqli_stmt_close($s2);
                } else {
                    // increment attempts
                    $fid = (int)$row['id'];
                    $attempts = (int)$row['codigo_picagem_tentativas'] + 1;
                    $lockedUntil = null;
                    if ($attempts >= 5) {
                        $lockedUntil = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');
                    }
                    $s3 = mysqli_prepare($conn, 'UPDATE funcionarios SET codigo_picagem_tentativas = ?, codigo_picagem_bloqueado_ate = ? WHERE id = ?');
                    mysqli_stmt_bind_param($s3, 'isi', $attempts, $lockedUntil, $fid);
                    mysqli_stmt_execute($s3);
                    mysqli_stmt_close($s3);
                    redirect_with_message('danger', 'Código inválido. Tentativa registada.');
                }
            } else {
                // no hash - fallback: compare plaintext codigo_picagem field
                $stmt2 = mysqli_prepare($conn, 'SELECT id FROM funcionarios WHERE codigo_picagem = ? AND estado = "ativo" LIMIT 1');
                mysqli_stmt_bind_param($stmt2, 's', $codigo);
                mysqli_stmt_execute($stmt2);
                $r2 = mysqli_stmt_get_result($stmt2);
                $f2 = mysqli_fetch_assoc($r2);
                mysqli_stmt_close($stmt2);
                if ($f2) {
                    $funcionarioId = (int)$f2['id'];
                } else {
                    redirect_with_message('danger', 'Código inválido.');
                }
            }
        } else {
            $stmtHash = mysqli_prepare($conn, 'SELECT id, codigo_picagem_hash, codigo_picagem_bloqueado_ate FROM funcionarios WHERE estado = "ativo" AND codigo_picagem_hash IS NOT NULL');
            mysqli_stmt_execute($stmtHash);
            $resHash = mysqli_stmt_get_result($stmtHash);
            while ($hashRow = mysqli_fetch_assoc($resHash)) {
                if (!password_verify($codigo, $hashRow['codigo_picagem_hash'])) {
                    continue;
                }

                if (!empty($hashRow['codigo_picagem_bloqueado_ate']) && strtotime($hashRow['codigo_picagem_bloqueado_ate']) > time()) {
                    mysqli_stmt_close($stmtHash);
                    redirect_with_message('danger', 'Código temporariamente bloqueado.');
                }

                $funcionarioId = (int) $hashRow['id'];
                $s2 = mysqli_prepare($conn, 'UPDATE funcionarios SET codigo_picagem_tentativas = 0, codigo_picagem_bloqueado_ate = NULL WHERE id = ?');
                mysqli_stmt_bind_param($s2, 'i', $funcionarioId);
                mysqli_stmt_execute($s2);
                mysqli_stmt_close($s2);
                break;
            }
            mysqli_stmt_close($stmtHash);

            if ($funcionarioId <= 0)
            redirect_with_message('danger', 'Código inválido.');
        }
    } else {
        $funcionarioId = (int) ($_POST['funcionario_id'] ?? 0);
        $tipo = $_POST['tipo'] ?? '';
        $dataHora = trim($_POST['data_hora'] ?? '');
        $observacoes = trim($_POST['observacoes'] ?? '');
        $dataHoraSql = null;
        $dataReferencia = null;

        $tiposPermitidos = ['entrada', 'saida', 'inicio_pausa', 'fim_pausa', 'entrada_segundo_turno', 'saida_segundo_turno'];

        if ($funcionarioId <= 0 || !in_array($tipo, $tiposPermitidos, true) || $dataHora === '') {
            redirect_with_message('danger', 'Preencha funcionário, movimento e data/hora.');
        }

        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $dataHora);
        if (!$dt) {
            redirect_with_message('danger', 'Data/hora inválida.');
        }

        $dataHoraSql = $dt->format('Y-m-d H:i:s');
        $dataReferencia = $dt->format('Y-m-d');
        $observacoes = $observacoes === '' ? null : $observacoes;
    }

    $stmt = mysqli_prepare($conn, "SELECT id FROM funcionarios WHERE id = ? AND estado = 'ativo' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $funcionarioId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funcionarioExiste = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$funcionarioExiste) {
        redirect_with_message('danger', 'Funcionário inválido ou inativo.');
    }

    $temDataReferencia = fe_column_exists($conn, 'registos_ponto', 'data_referencia');
    // Basic validations: prevent consecutive same movement types and exit without entry
    $stmtLast = mysqli_prepare($conn, 'SELECT tipo FROM registos_ponto WHERE funcionario_id = ? ORDER BY data_hora DESC, id DESC LIMIT 1');
    mysqli_stmt_bind_param($stmtLast, 'i', $funcionarioId);
    mysqli_stmt_execute($stmtLast);
    $resLast = mysqli_stmt_get_result($stmtLast);
    $last = mysqli_fetch_assoc($resLast);
    mysqli_stmt_close($stmtLast);

    if ($last) {
        $lastTipo = $last['tipo'];
        // disallow two consecutive identical movement types of entry/exit
        $consecutiveDisallowed = [
            ['entrada','entrada'], ['saida','saida'],
            ['entrada_segundo_turno','entrada_segundo_turno'], ['saida_segundo_turno','saida_segundo_turno']
        ];
        foreach ($consecutiveDisallowed as $pair) {
            if ($lastTipo === $pair[0] && $tipo === $pair[1]) {
                redirect_with_message('danger', 'Movimento inválido: movimento igual ao anterior.');
            }
        }

        // prevent exit without prior entry
        $exitTypes = ['saida','saida_segundo_turno'];
        $entryTypes = ['entrada','entrada_segundo_turno'];
        if (in_array($tipo, $exitTypes, true) && !in_array($lastTipo, $entryTypes, true)) {
            redirect_with_message('danger', 'Saída inválida: não existe entrada anterior registada.');
        }
    } else {
        // no previous record; prevent exit as first movement
        if (in_array($tipo, ['saida','saida_segundo_turno'], true)) {
            redirect_with_message('danger', 'Saída inválida: não existe entrada anterior registada.');
        }
    }

    // insert record and preserve format; origem: dispositivo when via codigo, else manual
    $origem = ($acao === 'registar_por_codigo') ? 'dispositivo' : 'manual';
    $registoManual = ($acao === 'registar_por_codigo') ? 0 : 1;

    if ($temDataReferencia) {
        $stmt = mysqli_prepare($conn, "INSERT INTO registos_ponto
            (funcionario_id, tipo, data_hora, data_referencia, origem, estado, observacoes, registo_manual)
            VALUES (?, ?, ?, ?, ?, 'valido', ?, ?)");
        mysqli_stmt_bind_param($stmt, 'isssssi', $funcionarioId, $tipo, $dataHoraSql, $dataReferencia, $origem, $observacoes, $registoManual);
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO registos_ponto
            (funcionario_id, tipo, data_hora, origem, estado, observacoes, registo_manual)
            VALUES (?, ?, ?, ?, 'valido', ?, ?)");
        mysqli_stmt_bind_param($stmt, 'issssi', $funcionarioId, $tipo, $dataHoraSql, $origem, $observacoes, $registoManual);
    }

    mysqli_stmt_execute($stmt);
    $insertId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // write audit log
    if (fe_table_exists($conn, 'registos_ponto_logs')) {
        $old = null;
        $new = json_encode(['id' => $insertId, 'funcionario_id' => $funcionarioId, 'tipo' => $tipo, 'data_hora' => $dataHoraSql]);
        $l = mysqli_prepare($conn, 'INSERT INTO registos_ponto_logs (registo_ponto_id, operacao, dados_antigos, dados_novos, utilizador_id) VALUES (?, "insercao", NULL, ?, NULL)');
        mysqli_stmt_bind_param($l, 'is', $insertId, $new);
        mysqli_stmt_execute($l);
        mysqli_stmt_close($l);
    }

    redirect_with_message('success', movimento_label($tipo) . ' registada com sucesso.');
}

$funcionarios = [];
$registos = [];

if (empty($missingTables)) {
    $stmt = mysqli_prepare($conn, "SELECT id, numero_mecanografico, nome, funcao, codigo_biometrico
        FROM funcionarios
        WHERE estado = 'ativo'
        ORDER BY nome ASC");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $funcionarios[] = $row;
    }
    mysqli_stmt_close($stmt);
}

if ($temFuncionarioRegisto) {
    $stmt = mysqli_prepare($conn, "SELECT rp.id, rp.tipo, rp.data_hora, rp.origem, rp.estado, rp.observacoes,
               f.nome AS funcionario_nome, f.numero_mecanografico
        FROM registos_ponto rp
        INNER JOIN funcionarios f ON f.id = rp.funcionario_id
        WHERE DATE(rp.data_hora) = CURDATE()
        ORDER BY rp.data_hora DESC, rp.id DESC");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $registos[] = $row;
    }
    mysqli_stmt_close($stmt);
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
                        <h3 class="fw-bold mb-3">Registo de Ponto</h3>
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
                                <a href="ponto.php">Ponto</a>
                            </li>
                        </ul>
                    </div>

                    <?php if ($alertMessage !== ''): ?>
                        <div class="alert alert-<?php echo e($alertType ?: 'info'); ?> alert-dismissible fade show" role="alert">
                            <?php echo e($alertMessage); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($missingTables) || !$temFuncionarioRegisto): ?>
                        <div class="alert alert-warning" role="alert">
                            Execute o schema <code>database/schema_completo.sql</code> para ativar registos por funcionário.
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Correção manual</h4>
                                </div>
                                <div class="card-body">
                                    <form method="post" class="needs-validation" novalidate>
                                        <input type="hidden" name="acao" value="registar_ponto">
                                        <div class="mb-3">
                                            <label class="form-label">Funcionário *</label>
                                            <select name="funcionario_id" class="form-select" required <?php echo !$temFuncionarioRegisto ? 'disabled' : ''; ?>>
                                                <option value="">Selecionar funcionário</option>
                                                <?php foreach ($funcionarios as $funcionario): ?>
                                                    <option value="<?php echo (int) $funcionario['id']; ?>">
                                                        <?php echo e($funcionario['nome']); ?>
                                                        <?php if ($funcionario['numero_mecanografico']): ?>
                                                            (<?php echo e($funcionario['numero_mecanografico']); ?>)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">Selecione um funcionário.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Movimento *</label>
                                            <select name="tipo" class="form-select" required <?php echo !$temFuncionarioRegisto ? 'disabled' : ''; ?>>
                                                <option value="">Selecionar movimento</option>
                                                <option value="entrada">Entrada</option>
                                                <option value="saida">Saída</option>
                                                <option value="inicio_pausa">Início de pausa</option>
                                                <option value="fim_pausa">Fim de pausa</option>
                                            </select>
                                            <div class="invalid-feedback">Selecione o movimento.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Data/hora *</label>
                                            <input type="datetime-local" name="data_hora" class="form-control" value="<?php echo e(date('Y-m-d\TH:i')); ?>" required <?php echo !$temFuncionarioRegisto ? 'disabled' : ''; ?>>
                                            <div class="invalid-feedback">Indique a data/hora.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Observações</label>
                                            <textarea name="observacoes" class="form-control" rows="3" <?php echo !$temFuncionarioRegisto ? 'disabled' : ''; ?>></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100" <?php echo !$temFuncionarioRegisto ? 'disabled' : ''; ?>>
                                            <i class="fa fa-save"></i>
                                            Guardar movimento
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Registos de hoje</h4>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="tabela-ponto" class="display table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Funcionário</th>
                                                    <th>N.º mec.</th>
                                                    <th>Hora</th>
                                                    <th>Movimento</th>
                                                    <th>Origem</th>
                                                    <th>Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($registos as $registo): ?>
                                                    <tr>
                                                        <td><?php echo e($registo['funcionario_nome']); ?></td>
                                                        <td><?php echo e($registo['numero_mecanografico'] ?: '-'); ?></td>
                                                        <td><?php echo e(date('H:i:s', strtotime($registo['data_hora']))); ?></td>
                                                        <td>
                                                            <span class="badge badge-<?php echo e(movimento_badge($registo['tipo'])); ?>">
                                                                <?php echo e(movimento_label($registo['tipo'])); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo e(ucfirst($registo['origem'])); ?></td>
                                                        <td><?php echo e(ucfirst($registo['estado'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <?php include 'includes/scripts.php'; ?>
    <script>
        $(document).ready(function () {
            $('#tabela-ponto').DataTable({
                pageLength: 25,
                order: [[2, 'desc']],
                language: {
                    search: 'Pesquisar:',
                    lengthMenu: 'Mostrar _MENU_ registos',
                    info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
                    infoEmpty: 'Sem registos',
                    zeroRecords: 'Nenhum registo encontrado',
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


