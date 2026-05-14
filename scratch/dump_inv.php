<?php
require_once 'includes/conexion.php';
$stmt = $pdo->query("SELECT * FROM inventario");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT);

?>