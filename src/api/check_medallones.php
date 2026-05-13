<?php
session_start();
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

$id_partida = $_SESSION['id_partida'] ?? null;
if (!$id_partida) {
    echo json_encode(['success' => false, 'error' => 'Sesión no válida']);
    exit;
}

$medallones_ids = [6, 7, 8];
$tiene = [];

try {
    foreach ($medallones_ids as $id) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM inventario 
            WHERE id_partida = ? AND id_objeto = ? AND tipo_objeto = 'item'
        ");
        $stmt->execute([$id_partida, $id]);
        if ($stmt->fetchColumn() > 0) {
            $tiene[] = $id;
        }
    }

    foreach ($_SESSION['inventario_sesion'] ?? [] as $s_item) {
        if (
            $s_item['tipo_objeto'] === 'item' &&
            in_array((int) $s_item['id_objeto'], $medallones_ids) &&
            !in_array((int) $s_item['id_objeto'], $tiene)
        ) {
            $tiene[] = (int) $s_item['id_objeto'];
        }
    }

    echo json_encode([
        'success' => true,
        'medallones_disponibles' => $tiene
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>