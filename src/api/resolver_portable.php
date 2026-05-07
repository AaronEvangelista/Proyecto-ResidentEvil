<?php
session_start();
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$id_partida = $_SESSION['id_partida'] ?? null;
$data = json_decode(file_get_contents('php://input'), true);
$id_registro = $data['id_registro'] ?? null; // El registro de la caja en el inventario

if (!$id_partida) {
    echo json_encode(['success' => false, 'error' => 'Sesión no válida']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Determinar qué recompensa dar
    // Miramos si el jugador ya tiene la Llave de Diamante (ID 11)
    $stmt_key = $pdo->prepare("SELECT COUNT(*) FROM inventario WHERE id_partida = ? AND id_objeto = 11");
    $stmt_key->execute([$id_partida]);
    $tiene_llave = $stmt_key->fetchColumn() > 0;

    $id_recompensa = $tiene_llave ? 4 : 11; // 4 = Escopeta, 11 = Llave Diamante
    
    // Obtener info del premio
    $stmt_item = $pdo->prepare("SELECT nombre, tipo FROM catalogo_items WHERE id_item = ?");
    $stmt_item->execute([$id_recompensa]);
    $item_info = $stmt_item->fetch(PDO::FETCH_ASSOC);
    $nombre_premio = $item_info['nombre'];

    // 2. Eliminar la caja fuerte del inventario
    if ($id_registro && strpos((string)$id_registro, 'session_') === false) {
        // Estaba en DB
        $stmt_del = $pdo->prepare("DELETE FROM inventario WHERE id_registro = ? AND id_partida = ?");
        $stmt_del->execute([$id_registro, $id_partida]);
    } else if ($id_registro) {
        // Estaba en sesión
        $index = (int)str_replace('session_', '', $id_registro);
        if (isset($_SESSION['inventario_sesion'][$index])) {
            unset($_SESSION['inventario_sesion'][$index]);
            $_SESSION['inventario_sesion'] = array_values($_SESSION['inventario_sesion']);
        }
    }

    // 3. Entregar la recompensa (siempre a la DB para que persista)
    $stmt_slot = $pdo->prepare("
        SELECT s.n 
        FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7) s
        WHERE s.n NOT IN (SELECT posicion_slot FROM inventario WHERE id_partida = ?)
        LIMIT 1
    ");
    $stmt_slot->execute([$id_partida]);
    $posicion_slot = $stmt_slot->fetchColumn();

    if ($posicion_slot === false) {
        echo json_encode(['success' => false, 'error' => 'Inventario lleno. No puedes recibir el premio.']);
        exit;
    }

    $stmt_ins = $pdo->prepare("INSERT INTO inventario (id_partida, tipo_objeto, id_objeto, cantidad, posicion_slot) 
                                VALUES (?, 'item', ?, 1, ?)");
    $stmt_ins->execute([$id_partida, $id_recompensa, $posicion_slot]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "¡Caja abierta! Has encontrado: $nombre_premio",
        'nombre_objeto' => $nombre_premio
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Error DB: ' . $e->getMessage()]);
}
?>
