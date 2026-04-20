<?php
$host = 'localhost';
$usuario = 'root';
$contrasena = '';
$basedatos = 'resident_evil_db';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$basedatos", $usuario, $contrasena);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8'");
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>