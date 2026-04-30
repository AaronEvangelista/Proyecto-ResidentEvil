<?php
session_start();
require_once '../includes/conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id_evento = $data['id_evento'] ?? null;
    $tipo_objeto = $data['tipo_objeto'] ?? null;
    $id_objeto = $data['id_objeto'] ?? null;
    $id_partida = $_SESSION['id_partida'] ?? 1;

    if ($id_evento) {
        try {
            $pdo->beginTransaction();

            // 1. Registrar que el evento ha sido completado
            $stmt = $pdo->prepare("INSERT OR IGNORE INTO eventos_completados (id_partida, id_evento) VALUES (?, ?)");
            $stmt->execute([$id_partida, $id_evento]);

            // 2. Añadir al inventario
            if ($tipo_objeto && $id_objeto) {
                // Encontrar el primer slot libre (0-7)
                $stmt_slots = $pdo->prepare("SELECT posicion_slot FROM inventario WHERE id_partida = ? AND posicion_slot IS NOT NULL");
                $stmt_slots->execute([$id_partida]);
                $slots_ocupados = $stmt_slots->fetchAll(PDO::FETCH_COLUMN);
                
                $slot_libre = 0;
                for ($i = 0; $i < 8; $i++) {
                    if (!in_array($i, $slots_ocupados)) {
                        $slot_libre = $i;
                        break;
                    }
                }

                $stmt_inv = $pdo->prepare("INSERT INTO inventario (id_partida, tipo_objeto, id_objeto, cantidad, posicion_slot) VALUES (?, ?, ?, 1, ?)");
                $stmt_inv->execute([$id_partida, $tipo_objeto, $id_objeto, $slot_libre]);
            }
            
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Faltan datos']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
?>