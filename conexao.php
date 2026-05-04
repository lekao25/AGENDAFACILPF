<?php
// conexao.php
$host = 'localhost';
$dbname = 'rede_municipal';
$username = 'root'; // Altere se necessário
$password = '';     // Altere se necessário

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}
?>
