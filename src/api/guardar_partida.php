<?php
session_start();
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$slot_numero = $data['slot_numero'] ?? 1;
$id_usuario = $_SESSION['usuario_id'] ?? null;
$id_partida_actual = $_SESSION['id_partida'] ?? null;

if (!$id_usuario || !$id_partida_actual) {
    echo json_encode(['success' => false, 'error' => 'No autorizado o partida no iniciada']);
    exit;
}

try {
    $pdo->beginTransaction();

    $cinta_consumida = false;

    if (isset($_SESSION['inventario_sesion'])) {
        foreach ($_SESSION['inventario_sesion'] as $index => &$item) {
            if ($item['tipo_objeto'] === 'item') {
                $stmt_name = $pdo->prepare("SELECT nombre FROM catalogo_items WHERE id_item = ?");
                $stmt_name->execute([$item['id_objeto']]);
                if ($stmt_name->fetchColumn() === 'Cinta de Guardado') {
                    if ($item['cantidad'] > 1) {
                        $item['cantidad']--;
                    } else {
                        array_splice($_SESSION['inventario_sesion'], $index, 1);
                    }
                    $cinta_consumida = true;
                    break;
                }
            }
        }
    }

    if (!$cinta_consumida) {
        $stmt_cinta_db = $pdo->prepare("
            SELECT i.id_registro, i.cantidad 
            FROM inventario i 
            JOIN catalogo_items c ON i.id_objeto = c.id_item AND i.tipo_objeto = 'item'
            WHERE i.id_partida = ? AND c.nombre = 'Cinta de Guardado'
            LIMIT 1
        ");
        $stmt_cinta_db->execute([$id_partida_actual]);
        $cinta_db = $stmt_cinta_db->fetch();

        if ($cinta_db) {
            if ($cinta_db['cantidad'] > 1) {
                $stmt_upd = $pdo->prepare("UPDATE inventario SET cantidad = cantidad - 1 WHERE id_registro = ?");
                $stmt_upd->execute([$cinta_db['id_registro']]);
            } else {
                $stmt_del = $pdo->prepare("DELETE FROM inventario WHERE id_registro = ?");
                $stmt_del->execute([$cinta_db['id_registro']]);
            }
            $cinta_consumida = true;
        }
    }

    if (!$cinta_consumida) {
        throw new Exception("No tienes Cintas de Guardado.");
    }

    $stmt_inv_db = $pdo->prepare("SELECT tipo_objeto, id_objeto, cantidad, posicion_slot FROM inventario WHERE id_partida = ?");
    $stmt_inv_db->execute([$id_partida_actual]);
    $items_actuales = $stmt_inv_db->fetchAll(PDO::FETCH_ASSOC);

    $items_sesion = $_SESSION['inventario_sesion'] ?? [];

    foreach ($items_sesion as $is) {
        $items_actuales[] = [
            'tipo_objeto' => $is['tipo_objeto'],
            'id_objeto' => $is['id_objeto'],
            'cantidad' => $is['cantidad'],
            'posicion_slot' => null
        ];
    }

    $stmt_ev_db = $pdo->prepare("SELECT id_evento FROM eventos_completados WHERE id_partida = ?");
    $stmt_ev_db->execute([$id_partida_actual]);
    $eventos_actuales = $stmt_ev_db->fetchAll(PDO::FETCH_COLUMN);

    $eventos_sesion = $_SESSION['eventos_recogidos_sesion'] ?? [];
    $eventos_actuales = array_unique(array_merge($eventos_actuales, $eventos_sesion));

    $sala_actual = $_SESSION['sala_actual'] ?? 'banos_inicio';

    $stmt_check_slot = $pdo->prepare("SELECT id_partida FROM partida WHERE id_usuario = ? AND slot_numero = ?");
    $stmt_check_slot->execute([$id_usuario, $slot_numero]);
    $id_partida_destino = $stmt_check_slot->fetchColumn();

    if ($id_partida_destino) {
        $stmt_upd_p = $pdo->prepare("UPDATE partida SET sala_actual = ?, fecha_guardado = CURRENT_TIMESTAMP WHERE id_partida = ?");
        $stmt_upd_p->execute([$sala_actual, $id_partida_destino]);

        $pdo->prepare("DELETE FROM inventario WHERE id_partida = ?")->execute([$id_partida_destino]);
        $pdo->prepare("DELETE FROM eventos_completados WHERE id_partida = ?")->execute([$id_partida_destino]);
    } else {
        $stmt_ins_p = $pdo->prepare("INSERT INTO partida (id_usuario, slot_numero, ruta, sala_actual) VALUES (?, ?, 'chico', ?)");
        $stmt_ins_p->execute([$id_usuario, $slot_numero, $sala_actual]);
        $id_partida_destino = $pdo->lastInsertId();
    }


    foreach ($eventos_actuales as $id_ev) {
        $pdo->prepare("INSERT INTO eventos_completados (id_partida, id_evento) VALUES (?, ?)")
            ->execute([$id_partida_destino, $id_ev]);
    }

    $slots_ocupados = [];
    foreach ($items_actuales as $it) {
        $slot = $it['posicion_slot'];
        if ($slot === null || in_array($slot, $slots_ocupados)) {
            for ($i = 0; $i < 8; $i++) {
                if (!in_array($i, $slots_ocupados)) {
                    $slot = $i;
                    break;
                }
            }
        }
        $slots_ocupados[] = $slot;

        $pdo->prepare("INSERT INTO inventario (id_partida, tipo_objeto, id_objeto, cantidad, posicion_slot) VALUES (?, ?, ?, ?, ?)")
            ->execute([$id_partida_destino, $it['tipo_objeto'], $it['id_objeto'], $it['cantidad'], $slot]);
    }

    $_SESSION['eventos_recogidos_sesion'] = [];
    $_SESSION['inventario_sesion'] = [];
    $_SESSION['id_partida'] = $id_partida_destino;

    // --- COPIAR VIDA AL SLOT ---
    $st_get_vida = $pdo->prepare("SELECT vida_actual FROM estado_personaje WHERE id_partida = ?");
    $st_get_vida->execute([$id_partida_actual]);
    $vida_actual = $st_get_vida->fetchColumn() ?: 100;

    $pdo->prepare("REPLACE INTO estado_personaje (id_partida, vida_actual) VALUES (?, ?)")
        ->execute([$id_partida_destino, $vida_actual]);
    // ---------------------------

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Partida guardada correctamente en el Slot ' . $slot_numero]);

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
