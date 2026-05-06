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

// IDs fijos: 7=León, 8=Unicornio, 9=Doncella
$medallones_requeridos = [7, 8, 9];

try {
    // ─── 1. Localizar los medallones en la BD ────────────────────────────────
    $medallones_en_db = [];
    foreach ($medallones_requeridos as $id_medallon) {
        $stmt = $pdo->prepare("
            SELECT id_registro FROM inventario
            WHERE id_partida = ? AND id_objeto = ? AND tipo_objeto = 'item'
            LIMIT 1
        ");
        $stmt->execute([$id_partida, $id_medallon]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $medallones_en_db[$id_medallon] = $row['id_registro'];
        }
    }

    // ─── 2. Localizar los medallones en la sesión activa ─────────────────────
    $inventario_sesion = $_SESSION['inventario_sesion'] ?? [];
    $medallones_en_sesion = [];
    foreach ($inventario_sesion as $key => $s_item) {
        if (
            $s_item['tipo_objeto'] === 'item' &&
            in_array((int)$s_item['id_objeto'], $medallones_requeridos)
        ) {
            $id = (int)$s_item['id_objeto'];
            if (!isset($medallones_en_db[$id])) {         // Priorizar DB
                $medallones_en_sesion[$id] = $key;
            }
        }
    }

    // ─── 3. Verificar que existen los 3 ─────────────────────────────────────
    $encontrados = array_unique(
        array_merge(array_keys($medallones_en_db), array_keys($medallones_en_sesion))
    );
    foreach ($medallones_requeridos as $req) {
        if (!in_array($req, $encontrados)) {
            echo json_encode([
                'success' => false,
                'error'   => 'No tienes los tres medallones. Sigue explorando la comisaría.'
            ]);
            exit;
        }
    }

    // ─── 4. Eliminar los medallones del inventario DB ────────────────────────
    foreach ($medallones_en_db as $id_medallon => $id_registro) {
        $stmt = $pdo->prepare("DELETE FROM inventario WHERE id_registro = ?");
        $stmt->execute([$id_registro]);
    }

    // ─── 5. Eliminar los medallones de la sesión ─────────────────────────────
    foreach ($medallones_en_sesion as $id_medallon => $key) {
        unset($_SESSION['inventario_sesion'][$key]);
    }
    $_SESSION['inventario_sesion'] = array_values($_SESSION['inventario_sesion'] ?? []);

    // ─── 6. Marcar el evento de la estatua como completado ───────────────────
    $stmt_evento = $pdo->prepare("
        SELECT id_evento FROM eventos_interactivos
        WHERE id_sala = 'lobby_principal' AND tipo_accion = 'puzzle' AND contenido_accion = 'medallones'
        LIMIT 1
    ");
    $stmt_evento->execute();
    $evento = $stmt_evento->fetch(PDO::FETCH_ASSOC);

    if ($evento) {
        $id_evento = $evento['id_evento'];

        // Insertar en DB de completados
        $stmt_comp = $pdo->prepare("
            INSERT OR IGNORE INTO eventos_completados (id_partida, id_evento) VALUES (?, ?)
        ");
        $stmt_comp->execute([$id_partida, $id_evento]);

        // Marcar también en sesión activa
        if (!isset($_SESSION['eventos_recogidos_sesion'])) {
            $_SESSION['eventos_recogidos_sesion'] = [];
        }
        if (!in_array($id_evento, $_SESSION['eventos_recogidos_sesion'])) {
            $_SESSION['eventos_recogidos_sesion'][] = $id_evento;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => '¡Los tres medallones han sido colocados! La estatua cede...'
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
