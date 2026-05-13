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
                $stmt_ya = $pdo->prepare("SELECT COUNT(*) FROM eventos_completados WHERE id_partida = ? AND id_evento = ?");
                $stmt_ya->execute([$id_partida, $id_evento]);
                if ($stmt_ya->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'error' => 'Este objeto ya fue recogido.']);
                    exit;
                }

                if ($tipo_objeto === 'arma') {
                    $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM inventario WHERE id_partida = ? AND tipo_objeto = 'arma' AND id_objeto = ?");
                    $stmt_chk->execute([$id_partida, $id_objeto]);
                    if ($stmt_chk->fetchColumn() > 0) {
                        $pdo->prepare("INSERT OR IGNORE INTO eventos_completados (id_partida, id_evento) VALUES (?, ?)")->execute([$id_partida, $id_evento]);
                        echo json_encode(['success' => false, 'error' => 'Ya tienes esta arma en el inventario.']);
                        exit;
                    }
                }

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

                $cantidad_inicial = ($tipo_objeto === 'arma') ? 6 : 1;
                $stmt_inv = $pdo->prepare("INSERT INTO inventario (id_partida, tipo_objeto, id_objeto, cantidad, posicion_slot) VALUES (?, ?, ?, ?, ?)");
                $stmt_inv->execute([$id_partida, $tipo_objeto, $id_objeto, $cantidad_inicial, $posicion_slot]);
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