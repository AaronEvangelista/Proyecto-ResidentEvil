<?php
require 'includes/conexion.php';
$stmt = $pdo->query("SELECT id_item, nombre FROM catalogo_items");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($items, JSON_PRETTY_PRINT);
?>
