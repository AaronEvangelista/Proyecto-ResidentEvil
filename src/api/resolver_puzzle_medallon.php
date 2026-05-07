<?php
session_start();
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id_partida = $_SESSION['id_partida'] ?? null;
$tipo_puzzle = $data['puzzle'] ?? '';
$combinacion = $data['combinacion'] ?? [];

if (!$id_partida || !$tipo_puzzle) {
    echo json_encode(['success' => false, 'error' => 'Sesión o datos no válidos']);
    exit;
}

$soluciones = [
    'puzzle_leon' => ['Leon', 'Rama', 'Ave'],
    'puzzle_unicornio' => ['Pez', 'Escorpion', 'Jarra'],
    'puzzle_doncella' => ['Mujer', 'Arco', 'Serpiente']
];

$recompensas = [
    'puzzle_leon' => 7,
    'puzzle_unicornio' => 8,
    'puzzle_doncella' => 9
];

if (!isset($soluciones[$tipo_puzzle])) {
    echo json_encode(['success' => false, 'error' => 'Puzzle no reconocido']);
    exit;
}

$solucion = $soluciones[$tipo_puzzle];

if ($combinacion !== $solucion) {
    echo json_encode(['success' => false, 'error' => 'La combinación es incorrecta. Nada ha ocurrido.']);
    exit;
}

try {
    $id_objeto = $recompensas[$tipo_puzzle];

    $stmt_item = $pdo->prepare("SELECT nombre FROM catalogo_items WHERE id_item = ?");
    $stmt_item->execute([$id_objeto]);
    $nombre_objeto = $stmt_item->fetchColumn();

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
        echo json_encode(['success' => false, 'error' => 'Inventario lleno. No puedes recoger más objetos.']);
        exit;
    }

    $stmt_inv = $pdo->prepare("INSERT INTO inventario (id_partida, tipo_objeto, id_objeto, cantidad, posicion_slot) VALUES (?, 'item', ?, 1, ?)");
    $stmt_inv->execute([$id_partida, $id_objeto, $posicion_slot]);

    $stmt_evento = $pdo->prepare("SELECT id_evento FROM eventos_interactivos WHERE contenido_accion = ? LIMIT 1");
    $stmt_evento->execute([$tipo_puzzle]);
    $id_evento = $stmt_evento->fetchColumn();

    if ($id_evento) {
        $stmt_comp = $pdo->prepare("INSERT OR IGNORE INTO eventos_completados (id_partida, id_evento) VALUES (?, ?)");
        $stmt_comp->execute([$id_partida, $id_evento]);

        if (!isset($_SESSION['eventos_recogidos_sesion'])) {
            $_SESSION['eventos_recogidos_sesion'] = [];
        }
        $_SESSION['eventos_recogidos_sesion'][] = (int) $id_evento;
    }

    echo json_encode([
        'success' => true,
        'message' => "¡Mecanismo activado! Has obtenido: $nombre_objeto",
        'id_objeto' => $id_objeto,
        'nombre_objeto' => $nombre_objeto
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>