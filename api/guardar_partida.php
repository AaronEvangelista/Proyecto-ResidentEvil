<?php
session_start();
require_once '../includes/conexion.php';

header('Content-Type: application/json');

$id_partida = $_SESSION['id_partida'] ?? null;

if (!$id_partida) {
    echo json_encode(['success' => false, 'error' => 'No hay partida activa']);
    exit;
}

try {
    $pdo->beginTransaction();

    $eventos = $_SESSION['eventos_recogidos_sesion'] ?? [];
    foreach ($eventos as $id_evento) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO eventos_completados (id_partida, id_evento) VALUES (?, ?)");
        $stmt->execute([$id_partida, $id_evento]);
    }

    $stmt_slots = $pdo->prepare("SELECT posicion_slot FROM inventario WHERE id_partida = ?");
    $stmt_slots->execute([$id_partida]);
    $ocupados = $stmt_slots->fetchAll(PDO::FETCH_COLUMN);

    $items_sesion = $_SESSION['inventario_sesion'] ?? [];
    foreach ($items_sesion as $item) {
        $slot_db = 0;
        for ($i = 0; $i < 8; $i++) {
            if (!in_array($i, $ocupados)) {
                $slot_db = $i;
                $ocupados[] = $i;
                break;
            }
        }

        $stmt_inv = $pdo->prepare("INSERT INTO inventario (id_partida, tipo_objeto, id_objeto, cantidad, posicion_slot) VALUES (?, ?, ?, ?, ?)");
        $stmt_inv->execute([
            $id_partida,
            $item['tipo_objeto'],
            $item['id_objeto'],
            $item['cantidad'],
            $slot_db
        ]);
    }

    $_SESSION['eventos_recogidos_sesion'] = [];
    $_SESSION['inventario_sesion'] = [];

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Partida guardada correctamente']);
} catch (PDOException $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>