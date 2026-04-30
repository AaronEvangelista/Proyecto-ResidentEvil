<?php
session_start();
require_once '../includes/conexion.php';

header('Content-Type: application/json');

$id_partida = $_SESSION['id_partida'] ?? 1;

try {
    // 1. Obtener Items
    $query_items = $pdo->prepare("
        SELECT i.*, c.nombre, c.descripcion, c.imagen_url, c.tipo
        FROM inventario i
        JOIN catalogo_items c ON i.id_objeto = c.id_item
        WHERE i.id_partida = ? AND i.tipo_objeto = 'item'
        ORDER BY i.posicion_slot ASC
    ");
    $query_items->execute([$id_partida]);
    $items = $query_items->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener Armas
    $query_armas = $pdo->prepare("
        SELECT i.*, c.nombre, c.descripcion, c.imagen_url, 'arma' as tipo
        FROM inventario i
        JOIN catalogo_armas c ON i.id_objeto = c.id_arma
        WHERE i.id_partida = ? AND i.tipo_objeto = 'arma'
        ORDER BY i.posicion_slot ASC
    ");
    $query_armas->execute([$id_partida]);
    $armas = $query_armas->fetchAll(PDO::FETCH_ASSOC);

    $inventario_db = array_merge($items, $armas);

    // 3. Mezclar con lo que hay en SESIÓN (Sin guardar todavía)
    $inventario_sesion_raw = $_SESSION['inventario_sesion'] ?? [];
    $slots_ocupados = array_column($inventario_db, 'posicion_slot');

    foreach ($inventario_sesion_raw as $s_item) {
        if ($s_item['tipo_objeto'] === 'arma') {
            $st = $pdo->prepare("SELECT nombre, descripcion, imagen_url FROM catalogo_armas WHERE id_arma = ?");
        } else {
            $st = $pdo->prepare("SELECT nombre, descripcion, imagen_url FROM catalogo_items WHERE id_item = ?");
        }
        $st->execute([$s_item['id_objeto']]);
        $cat = $st->fetch(PDO::FETCH_ASSOC);
        
        if ($cat) {
            $nuevo = array_merge($s_item, $cat);
            // Asignar primer slot libre
            for ($i = 0; $i < 8; $i++) {
                 if (!in_array($i, $slots_ocupados)) {
                     $nuevo['posicion_slot'] = $i;
                     $slots_ocupados[] = $i;
                     break;
                 }
            }
            $inventario_db[] = $nuevo;
        }
    }

    // Ordenar todo por posicion_slot
    usort($inventario_db, function ($a, $b) {
        return ($a['posicion_slot'] ?? 99) <=> ($b['posicion_slot'] ?? 99);
    });

    echo json_encode(['success' => true, 'inventario' => $inventario_db]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>