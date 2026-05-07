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

if ($combinacion !== '911') {
    echo json_encode(['success' => false, 'error' => 'Combinación incorrecta']);
    exit;
}

try {
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

    $stmt_comp = $pdo->prepare("INSERT OR IGNORE INTO eventos_completados (id_partida, id_evento) VALUES (?, ?)");
    $stmt_comp->execute([$id_partida, $id_evento]);

    if (!isset($_SESSION['eventos_recogidos_sesion'])) {
        $_SESSION['eventos_recogidos_sesion'] = [];
    }
    if (!in_array($id_evento, $_SESSION['eventos_recogidos_sesion'])) {
        $_SESSION['eventos_recogidos_sesion'][] = $id_evento;
    }

    // Buscar el primer slot libre (0-7)
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

    // Insertar en la DB
    $stmt_inv = $pdo->prepare("INSERT INTO inventario (id_partida, tipo_objeto, id_objeto, cantidad, posicion_slot) VALUES (?, 'item', 13, 1, ?)");
    $stmt_inv->execute([$id_partida, $posicion_slot]);

    // Opcional: También registrar en sesión si se prefiere redundancia, 
    // pero get_inventario.php ya lee de la DB.
    if (!isset($_SESSION['inventario_sesion'])) {
        $_SESSION['inventario_sesion'] = [];
    }
    $_SESSION['inventario_sesion'][] = [
        'tipo_objeto' => 'item',
        'id_objeto' => 13,
        'cantidad' => 1,
        'posicion_slot' => $posicion_slot
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Caja fuerte abierta. Has obtenido: Cortacadenas.',
        'nombre_objeto' => 'Cortacadenas'
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>