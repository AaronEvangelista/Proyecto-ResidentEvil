<?php
session_start();
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

$id_partida = $_SESSION['id_partida'] ?? null;
if (!$id_partida) {
    echo json_encode(['success' => false, 'error' => 'Sesión no iniciada.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$accion = $data['accion'] ?? '';

if ($accion === 'completar') {
    try {
        $stmt = $pdo->prepare("
            SELECT id_evento FROM eventos_interactivos 
            WHERE id_sala = 'sala_electrica' AND nombre_objeto = 'PUZZLE FUSIBLES'
            LIMIT 1
        ");
        $stmt->execute();
        $id_evento = $stmt->fetchColumn();

        if ($id_evento) {
            $pdo->prepare("INSERT OR IGNORE INTO eventos_completados (id_partida, id_evento) VALUES (?, ?)")
                ->execute([$id_partida, $id_evento]);
        }

        $stmtChk = $pdo->prepare("
            SELECT COUNT(*) FROM inventario 
            WHERE id_partida = ? AND tipo_objeto = 'item' AND id_objeto = 13
        ");
        $stmtChk->execute([$id_partida]);
        $ya_tiene = $stmtChk->fetchColumn() > 0;

        $recompensa = '';
        if (!$ya_tiene) {
            $stSlot = $pdo->prepare("
                SELECT s.n FROM 
                (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 
                 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7) s
                WHERE s.n NOT IN (SELECT posicion_slot FROM inventario WHERE id_partida = ?)
                LIMIT 1
            ");
            $stSlot->execute([$id_partida]);
            $slot = $stSlot->fetchColumn();

            if ($slot !== false) {
                $pdo->prepare("INSERT INTO inventario (id_partida, tipo_objeto, id_objeto, cantidad, posicion_slot) VALUES (?, 'item', 13, 1, ?)")
                    ->execute([$id_partida, $slot]);
                $recompensa = ' Has obtenido: Cortacadenas.';
            }
        }

        echo json_encode(['success' => true, 'message' => 'Panel eléctrico restaurado.' . $recompensa]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Acción no reconocida.']);
}
?>