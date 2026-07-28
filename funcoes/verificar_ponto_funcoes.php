<?php

if (!function_exists('e')) {
    function e($v)
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

function verificar_ponto_recalcular_resumos($conn, $funcionarioId, $dataOriginal, $dataNova)
{
    if (!function_exists('calcular_resumo_diario_assiduidade') || (int) $funcionarioId <= 0) {
        return false;
    }

    $datas = array_values(array_unique(array_filter([$dataOriginal, $dataNova])));
    $ok = true;

    foreach ($datas as $data) {
        try {
            $resultado = calcular_resumo_diario_assiduidade($conn, $data, (int) $funcionarioId);
            if (!empty($resultado['erros'])) {
                $ok = false;
            }
        } catch (Throwable $e) {
            $ok = false;
        }
    }

    return $ok;
}
