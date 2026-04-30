<?php
session_start();
require_once '../includes/conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id_registro = $data['id_registro'] ?? null;
    $nuevo_slot = $data['nuevo_slot'] ?? null;
    $id_partida = $_SESSION['id_partida'] ?? 1;

    if ($id_registro && $nuevo_slot !== null) {
        try {
            $pdo->beginTransaction();

            // Verificar si el nuevo slot ya está ocupado por otro objeto
            $stmt_check = $pdo->prepare("SELECT id_registro, posicion_slot FROM inventario WHERE id_partida = ? AND posicion_slot = ?");
            $stmt_check->execute([$id_partida, $nuevo_slot]);
            $objeto_existente = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($objeto_existente) {
                // Hay un objeto en el slot de destino, necesitamos hacer un swap
                // Obtenemos la posición actual del objeto que estamos moviendo
                $stmt_actual = $pdo->prepare("SELECT posicion_slot FROM inventario WHERE id_registro = ?");
                $stmt_actual->execute([$id_registro]);
                $posicion_actual = $stmt_actual->fetchColumn();

                // Mover el objeto existente al slot antiguo
                $stmt_swap = $pdo->prepare("UPDATE inventario SET posicion_slot = ? WHERE id_registro = ?");
                $stmt_swap->execute([$posicion_actual, $objeto_existente['id_registro']]);
            }

            // Mover el objeto arrastrado al nuevo slot
            $stmt_move = $pdo->prepare("UPDATE inventario SET posicion_slot = ? WHERE id_registro = ?");
            $stmt_move->execute([$nuevo_slot, $id_registro]);

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
