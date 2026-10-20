<?php

$dbHost = "localhost";
$dbName = "wert";
$dbUser = "root";
$dbPass = "";

$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";

try {

    $pdo = new PDO($dsn, $dbUser, $dbPass);

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

} catch (PDOException $e) {

    // Loga o erro real em arquivo, nunca mostra pro usuário
    error_log("Erro de conexão com o banco: " . $e->getMessage());
    die("Não foi possível conectar ao sistema. Tente novamente mais tarde.");

}