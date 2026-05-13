<?php
session_start();
require_once 'conexion.php';

$data = json_decode(file_get_contents('php://input'), true);
$id_partida = $_SESSION['id_partida'] ?? null;

if (!$id_partida || !$data) {
    echo json_encode(['success' => false, 'error' => 'Sesión o datos inválidos']);
    exit;
}

$fuente = $data['fuente'] ?? 'db';
$id_registro = $data['id_registro'] ?? null;
$sesion_idx = $data['sesion_idx'] ?? null;
$nombre_item = $data['nombre'] ?? '';
$tipo_item = $data['tipo'] ?? '';

$curacion = 0;
$recarga = 0;
$arma_objetivo = '';

if ($tipo_item === 'curacion') {
    if (strpos(strtolower($nombre_item), 'hierba verde') !== false) {
        $curacion = 25;
    } else if (strpos(strtolower($nombre_item), 'spray') !== false) {
        $curacion = 100;
    }
} else if ($tipo_item === 'municion') {
    if (strpos($nombre_item, 'Pistola') !== false) {
        $arma_objetivo = 'Pistola M19';
        $recarga = 4;
    } else if (strpos($nombre_item, 'Escopeta') !== false) {
        $arma_objetivo = 'Escopeta W-870';
        $recarga = 2;
    }
}

if ($curacion <= 0 && $recarga <= 0 && $tipo_item !== 'clave' && $tipo_item !== 'herramienta') {
    echo json_encode(['success' => false, 'error' => 'El objeto no tiene efecto válido']);
    exit;
}

// 2. Consumir el objeto
$id_registro_int = $id_registro !== null ? (int) $id_registro : null;
$id_partida_int = (int) $id_partida;

if ($fuente === 'db' && $id_registro_int) {
    $stmt = $pdo->prepare("UPDATE inventario SET cantidad = cantidad - 1 WHERE id_registro = ? AND id_partida = ? AND cantidad > 0");
    $stmt->execute([$id_registro_int, $id_partida_int]);

    $stmt_check = $pdo->prepare("SELECT cantidad FROM inventario WHERE id_registro = ?");
    $stmt_check->execute([$id_registro_int]);
    $cantidad_restante = $stmt_check->fetchColumn();

    if ($cantidad_restante !== false && (int) $cantidad_restante <= 0) {
        $stmt_del = $pdo->prepare("DELETE FROM inventario WHERE id_registro = ? AND id_partida = ?");
        $stmt_del->execute([$id_registro_int, $id_partida_int]);
    }
} else if ($fuente === 'sesion' && $sesion_idx !== null && isset($_SESSION['inventario_sesion'][$sesion_idx])) {
    $_SESSION['inventario_sesion'][$sesion_idx]['cantidad']--;
    if ($_SESSION['inventario_sesion'][$sesion_idx]['cantidad'] <= 0) {
        unset($_SESSION['inventario_sesion'][$sesion_idx]);
        $_SESSION['inventario_sesion'] = array_values($_SESSION['inventario_sesion']);
    }
}

// 3. Aplicar Efecto
if ($curacion > 0) {
    $stmt_vida = $pdo->prepare("SELECT vida_actual FROM estado_personaje WHERE id_partida = ?");
    $stmt_vida->execute([$id_partida_int]);
    $vida_actual = $stmt_vida->fetchColumn();
    $nueva_vida = min(100, $vida_actual + $curacion);
    $stmt_upd = $pdo->prepare("UPDATE estado_personaje SET vida_actual = ? WHERE id_partida = ?");
    $stmt_upd->execute([$nueva_vida, $id_partida_int]);

    echo json_encode(['success' => true, 'tipo' => 'curacion', 'nueva_vida' => $nueva_vida, 'curacion' => $curacion]);
} else if ($recarga > 0 && $arma_objetivo !== '') {
    // Buscar el arma en el inventario
    $stmt_arma = $pdo->prepare("
        SELECT i.id_registro, i.cantidad
        FROM inventario i
        JOIN catalogo_armas ca ON i.id_objeto = ca.id_arma
        WHERE i.id_partida = ? AND i.tipo_objeto = 'arma' AND ca.nombre = ?
        LIMIT 1
    ");
    $stmt_arma->execute([$id_partida_int, $arma_objetivo]);
    $arma_db = $stmt_arma->fetch(PDO::FETCH_ASSOC);

    if ($arma_db) {
        $nueva_cant = $arma_db['cantidad'] + $recarga;
        $stmt_upd_arma = $pdo->prepare("UPDATE inventario SET cantidad = ? WHERE id_registro = ?");
        $stmt_upd_arma->execute([$nueva_cant, $arma_db['id_registro']]);

        echo json_encode(['success' => true, 'tipo' => 'recarga', 'arma' => $arma_objetivo, 'nueva_cantidad' => $nueva_cant, 'recarga' => $recarga]);
    } else {
        $encontrada_sesion = false;
        if (isset($_SESSION['inventario_sesion'])) {
            foreach ($_SESSION['inventario_sesion'] as &$item_s) {
                if ($item_s['tipo_objeto'] === 'arma') {
                    $q_name = $pdo->prepare("SELECT nombre FROM catalogo_armas WHERE id_arma = ?");
                    $q_name->execute([$item_s['id_objeto']]);
                    if ($q_name->fetchColumn() === $arma_objetivo) {
                        $item_s['cantidad'] += $recarga;
                        $encontrada_sesion = true;
                        echo json_encode(['success' => true, 'tipo' => 'recarga', 'arma' => $arma_objetivo, 'nueva_cantidad' => $item_s['cantidad'], 'recarga' => $recarga]);
                        break;
                    }
                }
            }
        }
        if (!$encontrada_sesion) {
            echo json_encode(['success' => false, 'error' => 'No tienes el arma adecuada para esta munición']);
        }
    }
} else if ($tipo_item === 'clave' || $tipo_item === 'herramienta') {
    echo json_encode(['success' => true, 'tipo' => 'consumo_herramienta']);
} else {
    echo json_encode(['success' => false, 'error' => 'Fallo al procesar el objeto']);
}
