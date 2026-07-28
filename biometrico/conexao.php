<?php
try {

    $pdo= new PDO(
        "mysql:host=localhost;dbname=gestor_assiduidade;",
        "root",
        ""
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

}catch (PDOException $e){
    $mensagem = "[" . date("Y-m-d H:i:s") . "]"
    . $e->getMessage() . PHP_EOL;

    file_put_contents(
        __DIR__ ."/logs/error_conexao.log",
        $mensagem,
        FILE_APPEND
    );
    header("location: /presenca/404.php");
}

