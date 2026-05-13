<?php
session_start();
require_once __DIR__ . '/../../includes/conexion.php';

header('Content-Type: application/json');

if (empty($_SESSION['logueado']) || ($_SESSION['usuario_rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!isset($body['activo'])) {
    echo json_encode(['success' => false, 'error' => 'Parámetro activo requerido.']);
    exit;
}

$activo = $body['activo'] ? 1 : 0;
$idUsuario = (int) $_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("
        UPDATE usuarios
        SET    zombies_visibles = :activo
        WHERE  id_usuario = :id
    ");
    $stmt->execute([':activo' => $activo, ':id' => $idUsuario]);

    $_SESSION['zombies_visibles'] = $activo;

    echo json_encode([
        'success' => true,
        'zombies_visibles' => $activo,
        'mensaje' => $activo ? 'Zombies activados.' : 'Zombies desactivados.'
    ]);
} catch (PDOException $e) {
    error_log('admin_toggle_zombies: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error al actualizar.']);
}
