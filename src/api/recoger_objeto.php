<?php
session_start();
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id_evento = $data['id_evento'] ?? null;
    $tipo_objeto = $data['tipo_objeto'] ?? null;
    $id_objeto = $data['id_objeto'] ?? null;
    $id_partida = $_SESSION['id_partida'] ?? 1;

    if ($id_evento) {
        try {
            if ($tipo_objeto && $id_objeto) {
                // Buscar el primer slot libre (0-7) en la DB
                $stmt_slot = $pdo->prepare("
                    SELECT s.n 
                    FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7) s
                    WHERE s.n NOT IN (SELECT posicion_slot FROM inventario WHERE id_partida = ?)
                    LIMIT 1
                ");
                $stmt_slot->execute([$id_partida]);
                $posicion_slot = $stmt_slot->fetchColumn();

                if ($posicion_slot === false) {
                    echo json_encode(['success' => false, 'error' => 'Inventario lleno. No puedes recoger más objetos.']);
                    exit;
                }

                // Insertar en la DB
                $cantidad_inicial = ($tipo_objeto === 'arma') ? 6 : 1;
                $stmt_inv = $pdo->prepare("INSERT INTO inventario (id_partida, tipo_objeto, id_objeto, cantidad, posicion_slot) VALUES (?, ?, ?, ?, ?)");
                $stmt_inv->execute([$id_partida, $tipo_objeto, $id_objeto, $cantidad_inicial, $posicion_slot]);

                // Registrar en eventos completados para que no vuelva a aparecer
                $stmt_comp = $pdo->prepare("INSERT OR IGNORE INTO eventos_completados (id_partida, id_evento) VALUES (?, ?)");
                $stmt_comp->execute([$id_partida, $id_evento]);
            }

            echo json_encode(['success' => true, 'message' => 'Objeto recogido en sesión']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Faltan datos']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
?>
