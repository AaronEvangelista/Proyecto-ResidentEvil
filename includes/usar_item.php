<?php
session_start();
require_once 'conexion.php';

$data = json_decode(file_get_contents('php://input'), true);
$id_partida = $_SESSION['id_partida'] ?? null;

if (!$id_partida || !$data) {
    echo json_encode(['success' => false, 'error' => 'Sesión o datos inválidos']);
    exit;
}

$fuente = $data['fuente'];
$id_registro = $data['id_registro'] ?? null;
$sesion_idx = $data['sesion_idx'] ?? null;
$nombre_item = $data['nombre'] ?? '';

// 1. Determinar cuánto cura
$curacion = 0;
if (strpos(strtolower($nombre_item), 'hierba verde') !== false) {
    $curacion = 25;
} else if (strpos(strtolower($nombre_item), 'spray') !== false) {
    $curacion = 100;
}

if ($curacion <= 0) {
    echo json_encode(['success' => false, 'error' => 'El objeto no tiene efecto curativo']);
    exit;
}

// 2. Consumir el objeto
if ($fuente === 'db') {
    $stmt = $pdo->prepare("UPDATE inventario SET cantidad = cantidad - 1 WHERE id_registro = ? AND id_partida = ?");
    $stmt->execute([$id_registro, $id_partida]);
} else if ($fuente === 'sesion' && isset($_SESSION['inventario_sesion'][$sesion_idx])) {
    $_SESSION['inventario_sesion'][$sesion_idx]['cantidad']--;
    if ($_SESSION['inventario_sesion'][$sesion_idx]['cantidad'] <= 0) {
        unset($_SESSION['inventario_sesion'][$sesion_idx]);
        $_SESSION['inventario_sesion'] = array_values($_SESSION['inventario_sesion']);
    }
}

// 3. Aplicar curación
$stmt_vida = $pdo->prepare("SELECT vida_actual FROM estado_personaje WHERE id_partida = ?");
$stmt_vida->execute([$id_partida]);
$vida_actual = $stmt_vida->fetchColumn();

$nueva_vida = min(100, $vida_actual + $curacion);

$stmt_upd = $pdo->prepare("UPDATE estado_personaje SET vida_actual = ? WHERE id_partida = ?");
$stmt_upd->execute([$nueva_vida, $id_partida]);

echo json_encode(['success' => true, 'nueva_vida' => $nueva_vida, 'curacion' => $curacion]);