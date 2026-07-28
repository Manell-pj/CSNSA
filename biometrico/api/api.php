<?php

$url = "http://localhost/CSNSA01/api/criar_funcionario.php";

$dados = [
    "uid" => 10003,
    "nome" => "Ibraima Camara",
    "password" => "1234"
];

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));

$resposta = curl_exec($ch);

curl_close($ch);

echo $resposta;