<?php
require __DIR__ . '/vendor/autoload.php';
require_once 'conexao.php';
use Rats\Zkteco\Lib\ZKTeco;

set_time_limit(120); // Aumentado ligeiramente para segurança

$zk = new ZKTeco('10.42.190.177', 4370);

if (!$zk->connect()) {
    die("Erro: não foi possível conectar ao terminal.");
}

$picagens_aparelho = $zk->getAttendance();
$zk->disconnect(); // Podemos fechar a ligação assim que guardamos os dados em memória

if (empty($picagens_aparelho)) {
    echo "Não existem picagens no terminal.\n";
    exit;
}

// 1. Ordenar as picagens do terminal por tempo (da mais antiga para a mais recente)
usort($picagens_aparelho, function ($a, $b) {
    return strtotime($a['timestamp']) <=> strtotime($b['timestamp']);
});

// 2. Procurar a última picagem que já temos guardada na Base de Dados para otimização
// Isso evita ler e validar milhares de registos antigos do aparelho
$stmt_ultima_geral = $pdo->query("SELECT data_hora FROM registos_ponto ORDER BY data_hora DESC LIMIT 1");
$ultima_data_na_bd = $stmt_ultima_geral->fetchColumn();

// Prepared Statements do Banco de Dados
$sql_existe = "SELECT 1 FROM registos_ponto WHERE funcionario_id = :funcionarioId AND data_hora = :data_hora LIMIT 1";
$stmt_existe = $pdo->prepare($sql_existe);

$sql_check = "SELECT tipo FROM registos_ponto WHERE funcionario_id = :funcionarioId ORDER BY data_hora DESC, id DESC LIMIT 1";
$stmt_check = $pdo->prepare($sql_check);

$sql_insert = "INSERT INTO registos_ponto (funcionario_id, tipo, data_hora, origem, estado, data_referencia) VALUES (:funcionarioId, :tipo, :data_hora, :origem, :estado, :data_referencia)";
$stmt_insert = $pdo->prepare($sql_insert);

$inseridos = 0;

// Mapeamento dos tipos do terminal (ajuste conforme o seu aparelho se necessário)
$tipos = [
    0 => 'Entrada',
    1 => 'Saida',
    4 => 'Entrada', // Alguns aparelhos usam 4 para entrada de horas extras
    5 => 'Saida'    // e 5 para saída de horas extras
];

foreach ($picagens_aparelho as $item) {
    $funcionarioId = $item['id'];
    $data_hora = $item['timestamp'];
    $origem = 'dispositivo';
    $data_referencia= date('Y-m-d');
    // Otimização: Se a picagem for mais antiga do que o nosso último registo geral na BD, ignora logo
    if ($ultima_data_na_bd && strtotime($data_hora) <= strtotime($ultima_data_na_bd)) {
        continue;
    }

    // Ignora tipos de picagem desconhecidos (corrigido o erro de digitação de 'tipe' para 'type')
    if (!isset($tipos[$item['type']])) {
        continue;
    }

    $tipo_proposto = $tipos[$item['type']];

    // Verifica se este registo exato já existe para evitar duplicações
    $stmt_existe->execute([
        ':funcionarioId' => $funcionarioId,
        ':data_hora' => $data_hora
    ]);
    if ($stmt_existe->fetch()) {
        continue; // Já inserido anteriormente, avança para o próximo
    }

    // --- LÓGICA DE VALIDAÇÃO DE ENTRADA/SAÍDA ---

    // Procura o último estado real deste funcionário na Base de Dados
    $stmt_check->execute([':funcionarioId' => $funcionarioId]);
    $ultimo_registo = $stmt_check->fetch(PDO::FETCH_ASSOC);

    $estado = 1; // Por padrão o registo é válido

    if ($ultimo_registo) {
        $ultimo_tipo = $ultimo_registo['tipo'];

        // Regra: Se o último foi Entrada, o próximo DEVE ser Saída. Se o último foi Saída, o próximo DEVE ser Entrada.
        if ($ultimo_tipo === 'Entrada' && $tipo_proposto === 'Entrada') {
            // O funcionário esqueceu-se de dar saída. Marcar este novo como INVÁLIDO (ou duplicado de entrada)
            $estado = 0;
        }
        unset($ultimo_tipo);
    } else {
        // Se o funcionário não tem nenhum registo no sistema e a primeira picagem for uma "Saída", é irregular.
        if ($tipo_proposto === 'Saida') {
            $estado = 0;
        }
    }

    // Só inserimos na base de dados se for um movimento válido para não corromper o histórico do ponto
    // Se preferir guardar mesmo os inválidos com 'estado = 0' para auditoria, basta retirar o 'if ($estado == 1)'
    if ($estado === 1) {
        $stmt_insert->execute([
            ':funcionarioId' => $funcionarioId,
            ':tipo' => $tipo_proposto,
            ':data_hora' => $data_hora,
            ':origem' => $origem,
            ':estado' => $estado,
            ':data_referencia' => $data_referencia
        ]);
        $inseridos++;
    }
}

echo "Processo concluído. Novas picagens inseridas: $inseridos\n";
?>