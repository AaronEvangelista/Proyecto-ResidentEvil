<?php
session_start();
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$id_partida = $_SESSION['id_partida'] ?? null;
if (!$id_partida) {
    echo json_encode(['success' => false, 'error' => 'Sesión no válida']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$combinacion = $data['combinacion'] ?? '';

try {
    // Buscar el evento de la caja fuerte
    $stmt_evento = $pdo->prepare("
        SELECT id_evento FROM eventos_interactivos 
        WHERE id_sala = 'oficina_capitan' AND tipo_accion = 'puzzle' AND contenido_accion = 'caja_fuerte'
        LIMIT 1
    ");
    $stmt_evento->execute();
    $evento = $stmt_evento->fetch(PDO::FETCH_ASSOC);

    if (!$evento) {
        echo json_encode(['success' => false, 'error' => 'Evento no encontrado']);
        exit;
    }

    $id_evento = $evento['id_evento'];

    $stmt_ya = $pdo->prepare("SELECT COUNT(*) FROM eventos_completados WHERE id_partida = ? AND id_evento = ?");
    $stmt_ya->execute([$id_partida, $id_evento]);
    if ($stmt_ya->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'La caja fuerte ya fue abierta anteriormente.']);
        exit;
    }

    if ($combinacion !== '911') {
        echo json_encode(['success' => false, 'error' => 'Combinación incorrecta']);
        exit;
    }

    $pdo->prepare("INSERT OR IGNORE INTO eventos_completados (id_partida, id_evento) VALUES (?, ?)")
        ->execute([$id_partida, $id_evento]);

    if (!isset($_SESSION['eventos_recogidos_sesion'])) {
        $_SESSION['eventos_recogidos_sesion'] = [];
    }
    if (!in_array($id_evento, $_SESSION['eventos_recogidos_sesion'])) {
        $_SESSION['eventos_recogidos_sesion'][] = $id_evento;
    }

    $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM inventario WHERE id_partida = ? AND tipo_objeto = 'item' AND id_objeto = 12");
    $stmt_chk->execute([$id_partida]);
    if ($stmt_chk->fetchColumn() > 0) {
        echo json_encode(['success' => true, 'message' => 'Caja fuerte abierta. Ya tienes un Cortacadenas.', 'nombre_objeto' => 'Cortacadenas']);
        exit;
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
        echo json_encode(['success' => false, 'error' => 'Inventario lleno. Libera espacio para obtener la recompensa.']);
        exit;
    }

    $pdo->prepare("INSERT INTO inventario (id_partida, tipo_objeto, id_objeto, cantidad, posicion_slot) VALUES (?, 'item', 12, 1, ?)")
        ->execute([$id_partida, $posicion_slot]);

    echo json_encode([
        'success' => true,
        'message' => 'Caja fuerte abierta. Has obtenido: Cortacadenas.',
        'nombre_objeto' => 'Cortacadenas'
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>