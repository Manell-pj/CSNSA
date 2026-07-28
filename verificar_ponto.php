<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';
require_once __DIR__ . '/funcoes/ponto_funcoes.php';
require_once __DIR__ . '/funcoes/verificar_ponto_funcoes.php';
require_once __DIR__ . '/funcoes/calcular_resumo_diario_assiduidade.php';

$utilizadorSessao = require_login($conn);
ac_require_permission($conn, $utilizadorSessao, 'ponto.consultar');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));

$date = $_GET['date'] ?? date('Y-m-d');
$from = $date . ' 00:00:00';
$to = $date . ' 23:59:59';

// gather registos for the day
$issues = [];
$stmt = mysqli_prepare($conn, 'SELECT rp.*, f.nome AS funcionario_nome, f.numero_mecanografico FROM registos_ponto rp LEFT JOIN funcionarios f ON f.id = rp.funcionario_id WHERE rp.data_hora BETWEEN ? AND ? ORDER BY rp.funcionario_id ASC, rp.data_hora ASC');
mysqli_stmt_bind_param($stmt, 'ss', $from, $to);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$byFuncionario = [];
while ($r = mysqli_fetch_assoc($res)) {
    $byFuncionario[(int)$r['funcionario_id']][] = $r;
}
mysqli_stmt_close($stmt);

foreach ($byFuncionario as $fid => $rows) {
    $last = null;
    foreach ($rows as $r) {
        if ($last === null) {
            if (in_array($r['tipo'], ['saida','saida_segundo_turno'])) {
                $issues[] = ['tipo'=>'saida_sem_entrada','registo'=>$r,'mensagem'=>'Saída sem entrada anterior'];
            }
            $last = $r;
            continue;
        }
        // duplicate
        if ($r['tipo'] === $last['tipo'] && $r['data_hora'] === $last['data_hora']) {
            $issues[] = ['tipo'=>'duplicado','registo'=>$r,'mensagem'=>'Registo duplicado (mesmo tipo e hora)'];
        }
        // consecutive same
        if ($r['tipo'] === $last['tipo'] && in_array($r['tipo'], ['entrada','saida','entrada_segundo_turno','saida_segundo_turno'])) {
            $issues[] = ['tipo'=>'consecutivo','registo'=>$r,'mensagem'=>'Movimentos consecutivos iguais'];
        }
        // Duração impossível: entrada seguida de nova entrada em menos de 1 minuto, ou intervalo superior a 24h.
        $t0 = strtotime($last['data_hora']);
        $t1 = strtotime($r['data_hora']);
        if ($t1 - $t0 > 60*60*24) {
            $issues[] = ['tipo'=>'duracao_impossivel','registo'=>$r,'mensagem'=>'Período demasiado longo (>24h) entre movimentos consecutivos'];
        }
        $last = $r;
    }
}

// handle correction POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'corrigir') {
    ac_require_permission($conn, $utilizadorSessao, 'ponto.corrigir');
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        header('HTTP/1.1 403 Forbidden'); exit;
    }
    $registoId = (int) ($_POST['registo_id'] ?? 0);
    $novoTipo = $_POST['novo_tipo'] ?? '';
    $novaData = $_POST['nova_data_hora'] ?? '';
    $motivo = trim($_POST['motivo'] ?? '');
    if ($registoId <= 0 || $motivo === '' || $novoTipo === '' || $novaData === '') {
        header('Location: verificar_ponto.php?date='.urlencode($date).'&type=danger&message='.urlencode('Campos obrigatórios em falta'));
        exit;
    }
    if (!in_array($novoTipo, ['entrada', 'saida', 'entrada_segundo_turno', 'saida_segundo_turno'], true)) {
        header('Location: verificar_ponto.php?date='.urlencode($date).'&type=danger&message='.urlencode('Tipo de movimento invalido'));
        exit;
    }

    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $novaData);
    if (!$dt) {
        header('Location: verificar_ponto.php?date='.urlencode($date).'&type=danger&message='.urlencode('Data/hora inválida'));
        exit;
    }
    $novaSql = $dt->format('Y-m-d H:i:s');
    $novaDataReferencia = $dt->format('Y-m-d');

    // load original
    $s = mysqli_prepare($conn, 'SELECT * FROM registos_ponto WHERE id = ?');
    mysqli_stmt_bind_param($s, 'i', $registoId);
    mysqli_stmt_execute($s);
    $r = mysqli_stmt_get_result($s);
    $orig = mysqli_fetch_assoc($r);
    mysqli_stmt_close($s);
    if (!$orig) {
        header('Location: verificar_ponto.php?date='.urlencode($date).'&type=danger&message='.urlencode('Registo não existe'));
        exit;
    }

    $dataReferenciaOriginal = $orig['data_referencia'] ?? date('Y-m-d', strtotime($orig['data_hora']));
    $funcionarioIdOriginal = (int) ($orig['funcionario_id'] ?? 0);

    if (ponto_existe_movimento_mesma_hora($conn, $funcionarioIdOriginal, $novaSql, $registoId)) {
        header('Location: verificar_ponto.php?date='.urlencode($date).'&type=danger&message='.urlencode('Já existe outro movimento registado para este funcionário à mesma hora'));
        exit;
    }

    // insert log
    if (fe_table_exists($conn, 'registos_ponto_logs')) {
        $dados_antigos = json_encode($orig);
        $dados_novos = json_encode(['tipo'=>$novoTipo,'data_hora'=>$novaSql,'data_referencia'=>$novaDataReferencia]);
        $uid = $utilizadorSessao['id'];
        if (fe_column_exists($conn, 'registos_ponto_logs', 'motivo')) {
            $l = mysqli_prepare($conn, 'INSERT INTO registos_ponto_logs (registo_ponto_id, operacao, dados_antigos, dados_novos, utilizador_id, motivo) VALUES (?, "correcao", ?, ?, ?, ?)');
            if ($l) {
                mysqli_stmt_bind_param($l, 'issis', $registoId, $dados_antigos, $dados_novos, $uid, $motivo);
            }
        } else {
            $l = mysqli_prepare($conn, 'INSERT INTO registos_ponto_logs (registo_ponto_id, operacao, dados_antigos, dados_novos, utilizador_id) VALUES (?, "correcao", ?, ?, ?)');
            if ($l) {
                mysqli_stmt_bind_param($l, 'issi', $registoId, $dados_antigos, $dados_novos, $uid);
            }
        }

        if ($l) {
            mysqli_stmt_execute($l);
            mysqli_stmt_close($l);
        }
    }

    // update original record but keep audit fields
    if (fe_column_exists($conn, 'registos_ponto', 'data_referencia')) {
        $u = mysqli_prepare($conn, 'UPDATE registos_ponto SET tipo = ?, data_hora = ?, data_referencia = ?, motivo_correcao = ?, atualizado_por = ?, registo_manual = 1 WHERE id = ?');
        mysqli_stmt_bind_param($u, 'ssssii', $novoTipo, $novaSql, $novaDataReferencia, $motivo, $utilizadorSessao['id'], $registoId);
    } else {
        $u = mysqli_prepare($conn, 'UPDATE registos_ponto SET tipo = ?, data_hora = ?, motivo_correcao = ?, atualizado_por = ?, registo_manual = 1 WHERE id = ?');
        mysqli_stmt_bind_param($u, 'ssisi', $novoTipo, $novaSql, $motivo, $utilizadorSessao['id'], $registoId);
    }
    mysqli_stmt_execute($u);
    mysqli_stmt_close($u);

    $resumoAtualizado = verificar_ponto_recalcular_resumos($conn, $funcionarioIdOriginal, $dataReferenciaOriginal, $novaDataReferencia);
    $mensagem = $resumoAtualizado
        ? 'Registo corrigido e auditado'
        : 'Registo corrigido e auditado. O resumo de assiduidade será atualizado posteriormente.';

    header('Location: verificar_ponto.php?date='.urlencode($date).'&type=success&message='.urlencode($mensagem));
    exit;
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
            <div class="main-header"><?php include 'includes/header.php'; ?></div>
            <div class="container"><div class="page-inner">
            <div class="page-header"><h3 class="fw-bold mb-3">Verificação de Ponto</h3></div>
            <?php if ($alertMessage !== ''): ?>
                <div class="alert alert-<?php echo e($alertType ?: 'info'); ?> alert-dismissible fade show" role="alert"><?php echo e($alertMessage); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button></div>
            <?php endif; ?>

            <div class="card"><div class="card-body">
                <form method="get" class="row g-2 align-items-end mb-3"><div class="col-md-3"><label class="form-label">Data</label><input type="date" name="date" class="form-control" value="<?php echo e($date); ?>"></div><div class="col-md-2"><button class="btn btn-primary">Actualizar</button></div></form>

                <?php if (empty($issues)): ?>
                    <div class="alert alert-success">Nenhuma ocorrência detectada para a data selecionada.</div>
                <?php else: ?>
                    <table class="table table-striped">
                        <thead><tr><th>Funcionário</th><th>Tipo</th><th>Hora</th><th>Ocorrência</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($issues as $it): $r = $it['registo']; ?>
                            <tr>
                                <td><?php echo e($r['funcionario_nome'] ?? ''); ?> (<?php echo e($r['numero_mecanografico'] ?? '-'); ?>)</td>
                                <td><?php echo e(movimento_label($r['tipo'])); ?></td>
                                <td><?php echo e(date('d/m/Y H:i', strtotime($r['data_hora']))); ?></td>
                                <td><?php echo e($it['mensagem']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCorrigir<?php echo (int)$r['id']; ?>">Corrigir / Justificar</button>
                                </td>
                            </tr>

                            <!-- Modal corrigir -->
                            <div class="modal fade" id="modalCorrigir<?php echo (int)$r['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <form method="post" class="modal-content needs-validation" novalidate>
                                        <input type="hidden" name="acao" value="corrigir">
                                        <input type="hidden" name="registo_id" value="<?php echo (int)$r['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                        <div class="modal-header border-0"><h5 class="modal-title">Corrigir / Justificar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <p>Valor original: <strong><?php echo e($r['tipo']); ?> @ <?php echo e(date('d/m/Y H:i', strtotime($r['data_hora']))); ?></strong></p>
                                            <div class="mb-3"><label class="form-label">Novo tipo</label><select name="novo_tipo" class="form-select" required>
                                                <option value="entrada">Entrada</option>
                                                <option value="saida">Saída</option>
                                                <option value="entrada_segundo_turno">Entrada (2.º turno)</option>
                                                <option value="saida_segundo_turno">Saída (2.º turno)</option>
                                            </select></div>
                                            <div class="mb-3"><label class="form-label">Nova data/hora</label><input type="datetime-local" name="nova_data_hora" class="form-control" value="<?php echo e(date('Y-m-d\TH:i', strtotime($r['data_hora']))); ?>" required></div>
                                            <div class="mb-3"><label class="form-label">Motivo (obrigatório)</label><textarea name="motivo" class="form-control" rows="3" required></textarea></div>
                                        </div>
                                        <div class="modal-footer border-0"><button type="submit" class="btn btn-primary">Guardar correção</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button></div>
                                    </form>
                                </div>
                            </div>

                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            </div></div>

            </div></div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/scripts.php'; ?>
    <script>$(function(){$('.needs-validation').on('submit',function(e){if(!this.checkValidity()){e.preventDefault();e.stopPropagation();}$(this).addClass('was-validated');});});</script>
</body>
</html>
