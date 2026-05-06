<?php
session_start();
require_once 'conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$id_partida = isset($_SESSION['id_partida']) ? (int)$_SESSION['id_partida'] : null;
if (!$id_partida) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin sesión activa']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$fuente    = $data['fuente']     ?? null;   // 'db' o 'sesion'
$id_reg    = isset($data['id_registro']) ? (int)$data['id_registro'] : null;
$sesion_idx = isset($data['sesion_idx'])  ? (int)$data['sesion_idx']  : null;

$municion_restante = 0;

if ($fuente === 'db' && $id_reg) {
    //Decrementar en BD y eliminar si llega a 0
    $stmt = $pdo->prepare("UPDATE inventario SET cantidad = cantidad - 1 WHERE id_registro = ? AND id_partida = ? AND cantidad > 0");
    $stmt->execute([$id_reg, $id_partida]);

    $q = $pdo->prepare("SELECT cantidad FROM inventario WHERE id_registro = ?");
    $q->execute([$id_reg]);
    $municion_restante = (int)$q->fetchColumn();

    if ($municion_restante <= 0) {
        $pdo->prepare("DELETE FROM inventario WHERE id_registro = ? AND id_partida = ?")->execute([$id_reg, $id_partida]);
        $municion_restante = 0;
    }

} elseif ($fuente === 'sesion' && $sesion_idx !== null) {
    //Decrementar en sesión
    if (isset($_SESSION['inventario_sesion'][$sesion_idx])) {
        $actual = (int)($_SESSION['inventario_sesion'][$sesion_idx]['cantidad'] ?? 1);
        $nuevo  = max(0, $actual - 1);
        $_SESSION['inventario_sesion'][$sesion_idx]['cantidad'] = $nuevo;
        $municion_restante = $nuevo;
    }
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

echo json_encode(['ok' => true, 'municion_restante' => $municion_restante]);
