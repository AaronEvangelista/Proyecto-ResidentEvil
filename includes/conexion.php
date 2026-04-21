<?php
$host = 'localhost';
$usuario = 'root';
$contrasena = '';
$basedatos = __DIR__ . '/../database/resident_evil.sqlite3';

try {
    $pdo = new PDO("sqlite:" . $basedatos);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA encoding = 'utf8'");
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>