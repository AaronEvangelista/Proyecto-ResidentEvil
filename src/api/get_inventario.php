<?php
session_start();
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

// Limpiar datos de sesión obsoletos que causaban duplicados en inventario
unset($_SESSION['inventario_sesion']);

$id_partida = $_SESSION['id_partida'] ?? 1;

try {
    $query_items = $pdo->prepare("
        SELECT i.*, c.nombre, c.descripcion, c.imagen_url, c.tipo
        FROM inventario i
        JOIN catalogo_items c ON i.id_objeto = c.id_item
        WHERE i.id_partida = ? AND i.tipo_objeto = 'item'
        ORDER BY i.posicion_slot ASC
    ");
    $query_items->execute([$id_partida]);
    $items = $query_items->fetchAll(PDO::FETCH_ASSOC);

    $query_armas = $pdo->prepare("
        SELECT i.*, c.nombre, c.descripcion, c.imagen_url, 'arma' as tipo
        FROM inventario i
        JOIN catalogo_armas c ON i.id_objeto = c.id_arma
        WHERE i.id_partida = ? AND i.tipo_objeto = 'arma'
        ORDER BY i.posicion_slot ASC
    ");
    $query_armas->execute([$id_partida]);
    $armas = $query_armas->fetchAll(PDO::FETCH_ASSOC);

    $inventario = array_merge($items, $armas);

    usort($inventario, function ($a, $b) {
        return ($a['posicion_slot'] ?? 99) <=> ($b['posicion_slot'] ?? 99);
    });

    echo json_encode(['success' => true, 'inventario' => $inventario]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
