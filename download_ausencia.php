<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/funcoes/ausencias_funcoes.php';

$utilizador = require_login($conn);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Pedido inválido.';
    exit;
}

$funcionarioSelect = ausencias_column_exists($conn, 'pedidos_ausencia', 'funcionario_id') ? 'funcionario_id,' : 'NULL AS funcionario_id,';
$stmt = mysqli_prepare($conn, "SELECT utilizador_id, $funcionarioSelect ficheiro_justificativo FROM pedidos_ausencia WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$pedido = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$pedido || empty($pedido['ficheiro_justificativo'])) {
    http_response_code(404);
    echo 'Ficheiro não encontrado.';
    exit;
}

$ownerId = (int) $pedido['utilizador_id'];
$funcionarioPedidoId = (int) ($pedido['funcionario_id'] ?? 0);
$funcionarioAtualId = (int) (get_funcionario_id_from_utilizador($conn, (int) $utilizador['id']) ?? 0);
if ($ownerId !== (int) $utilizador['id']
    && ($funcionarioPedidoId <= 0 || $funcionarioPedidoId !== $funcionarioAtualId)
    && !ac_can($conn, (int) $utilizador['id'], 'ausencias.gerir')
    && !ac_can($conn, (int) $utilizador['id'], 'justificacoes.validar')
    && !ac_can($conn, (int) $utilizador['id'], 'ferias.gerir')) {
    http_response_code(403);
    echo 'Sem autorizacao para aceder a este ficheiro.';
    exit;
}

$storedName = (string) $pedido['ficheiro_justificativo'];
$candidatePaths = [];

if (basename($storedName) === $storedName) {
    $candidatePaths[] = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'ausencias' . DIRECTORY_SEPARATOR . $storedName;
} else {
    $candidatePaths[] = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storedName);
}

$allowedRoots = array_filter([
    realpath(__DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'ausencias'),
    realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ausencias'),
]);

$path = null;
foreach ($candidatePaths as $candidatePath) {
    $realCandidate = realpath($candidatePath);
    if (!$realCandidate || !is_file($realCandidate)) {
        continue;
    }

    foreach ($allowedRoots as $root) {
        if (strpos($realCandidate, $root . DIRECTORY_SEPARATOR) === 0) {
            $path = $realCandidate;
            break 2;
        }
    }
}

if (!$path) {
    http_response_code(404);
    echo 'Ficheiro não encontrado.';
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path) ?: 'application/octet-stream';
$basename = 'anexo_ausencia_' . $id . '.' . pathinfo($path, PATHINFO_EXTENSION);

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $basename . '"; filename*=UTF-8\'\'' . rawurlencode($basename));
header('Content-Transfer-Encoding: binary');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;

