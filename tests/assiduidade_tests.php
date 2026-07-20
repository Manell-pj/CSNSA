<?php
require_once __DIR__ . '/../funcoes/assiduidade_service.php';

function assert_eq($a,$b,$msg){
    if ($a === $b) {
        echo "PASS: $msg\n";
    } else {
        echo "FAIL: $msg\nExpected: "; var_export($b); echo "\nGot: "; var_export($a); echo "\n\n";
    }
}

// helper to build registo
function r($tipo,$dt){ return ['tipo'=>$tipo,'data_hora'=>$dt]; }

// 1. Turno normal: 09:00-17:00, entrada 09:00, saida 17:00
$periodos = [['inicio'=>'2026-07-14 09:00','fim'=>'2026-07-14 17:00']];
$registos = [r('entrada','2026-07-14 09:00'), r('saida','2026-07-14 17:00')];
$res = calcular_assiduidade_diaria(['id'=>1],'2026-07-14', $periodos, $periodos, $registos, [], ['entrada'=>0,'saida'=>0], 480, null, [], false, []);
assert_eq($res['minutos_previstos'],480,'Turno normal - minutos previstos');
assert_eq($res['minutos_trabalhados'],480,'Turno normal - minutos trabalhados');
assert_eq($res['horas_extra'],0,'Turno normal - horas extra');

// 2. Turno partido: 09:00-12:00 & 13:00-17:00 (registos com saidas/interrupcoes claras)
$periodos = [['inicio'=>'2026-07-14 09:00','fim'=>'2026-07-14 12:00'],['inicio'=>'2026-07-14 13:00','fim'=>'2026-07-14 17:00']];
$registos = [r('entrada','2026-07-14 09:00'), r('saida','2026-07-14 12:00'), r('entrada','2026-07-14 13:00'), r('saida','2026-07-14 17:00')];
$res = calcular_assiduidade_diaria(['id'=>1],'2026-07-14', $periodos, $periodos, $registos, [], ['entrada'=>0,'saida'=>0], null, null, [], false, []);
assert_eq($res['minutos_previstos'], 7*60, 'Turno partido - minutos previstos');
assert_eq($res['minutos_trabalhados'], 7*60, 'Turno partido - minutos trabalhados');

// 3. Turno noturno: 22:00 -> 06:00 next day
$periodos = [['inicio'=>'2026-07-14 22:00','fim'=>'2026-07-15 06:00']];
$registos = [r('entrada','2026-07-14 22:00'), r('saida','2026-07-15 06:00')];
$res = calcular_assiduidade_diaria(['id'=>1],'2026-07-14', $periodos, $periodos, $registos, [], [], null, null, [], false, []);
assert_eq($res['minutos_previstos'], 8*60, 'Turno noturno - minutos previstos');
assert_eq($res['minutos_trabalhados'], 8*60, 'Turno noturno - minutos trabalhados');
assert_eq($res['trabalho_noturno']>0, true, 'Turno noturno - trabalho noturno detetado');

// 4. Falta de saída
$periodos = [['inicio'=>'2026-07-14 09:00','fim'=>'2026-07-14 17:00']];
$registos = [r('entrada','2026-07-14 09:00')];
$res = calcular_assiduidade_diaria(['id'=>1],'2026-07-14', $periodos, $periodos, $registos, [], [], null, null, [], false, []);
assert_eq($res['minutos_trabalhados'], 0, 'Falta de saída - minutos trabalhados 0 (incomplete pair)');
assert_eq(in_array('entrada_sem_saida at 2026-07-14 09:00', $res['incidencias']), true, 'Falta de saída - incidência reportada');

// 5. Atraso
$periodos = [['inicio'=>'2026-07-14 09:00','fim'=>'2026-07-14 17:00']];
$registos = [r('entrada','2026-07-14 09:15'), r('saida','2026-07-14 17:00')];
$res = calcular_assiduidade_diaria(['id'=>1],'2026-07-14', $periodos, $periodos, $registos, [], ['entrada'=>5,'saida'=>0], null, null, [], false, []);
assert_eq($res['atraso'], 10, 'Atraso com tolerância 5 -> 10 minutos');

// 6. Folga trabalhada
$periodos = [];
$registos = [r('entrada','2026-07-14 10:00'), r('saida','2026-07-14 14:00')];
$res = calcular_assiduidade_diaria(['id'=>1],'2026-07-14', $periodos, $periodos, $registos, [], [], null, null, [], true, []);
assert_eq($res['folga_trabalhada'], true, 'Folga trabalhada detetada');

// 7. Férias (ausência)
$periodos = [['inicio'=>'2026-07-14 09:00','fim'=>'2026-07-14 17:00']];
$registos = [];
$ausencias = [['tipo'=>'ferias','inicio'=>'2026-07-14','fim'=>'2026-07-14']];
$res = calcular_assiduidade_diaria(['id'=>1],'2026-07-14', $periodos, $periodos, $registos, $ausencias, [], null, null, [], false, []);
assert_eq($res['minutos_previstos'], 0, 'Férias aprovadas -> minutos previstos 0');

// 8. Dois turnos consecutivos com horas extra no segundo turno (primeira hora 150%, restantes 175%)
$periodos = [['inicio'=>'2026-07-14 08:00','fim'=>'2026-07-14 12:00'],['inicio'=>'2026-07-14 13:00','fim'=>'2026-07-14 17:00']];
$registos = [r('entrada','2026-07-14 08:00'), r('saida','2026-07-14 12:00'), r('entrada_segundo_turno','2026-07-14 13:00'), r('saida_segundo_turno','2026-07-14 18:30')];
$regras = [
    ['codigo'=>'noturno','porcentagem'=>200,'prioridade'=>200,'data_inicio'=>'2020-01-01','data_fim'=>null],
    ['codigo'=>'segundo_turno_primeira_hora','porcentagem'=>150,'prioridade'=>100,'data_inicio'=>'2020-01-01','data_fim'=>null],
    ['codigo'=>'segundo_turno_subsequente','porcentagem'=>175,'prioridade'=>90,'data_inicio'=>'2020-01-01','data_fim'=>null],
];
$res = calcular_assiduidade_diaria(['id'=>1],'2026-07-14', $periodos, $periodos, $registos, [], [], null, null, [], false, [], $regras);
assert_eq($res['segundo_turno'], true, 'Deteção de segundo turno');
assert_eq($res['minutos_trabalhados'], 570, 'Dois turnos consecutivos - minutos trabalhados (inclui 90 min extra)');
assert_eq($res['minutos_150'], 60, 'Primeira hora extra segundo turno a 150%');
assert_eq($res['minutos_175'], 30, 'Horas extra subsequentes a 175%');
assert_eq($res['minutos_200'], 0, 'Nenhuma hora extra noturna neste exemplo');

echo "Tests complete.\n";


