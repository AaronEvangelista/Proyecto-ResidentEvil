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
$idObjetivo = isset($body['id_usuario']) ? (int) $body['id_usuario'] : 0;
$nuevoRol = trim($body['nuevo_rol'] ?? '');

if (!$idObjetivo || !in_array($nuevoRol, ['jugador', 'admin'], true)) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos.']);
    exit;
}

if ($idObjetivo === (int) $_SESSION['usuario_id'] && $nuevoRol !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'No puedes quitarte el rol de admin a ti mismo.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE usuarios
        SET    rol = :rol
        WHERE  id_usuario = :id
    ");
    $stmt->execute([':rol' => $nuevoRol, ':id' => $idObjetivo]);

    echo json_encode([
        'success' => true,
        'mensaje' => "Rol actualizado a '$nuevoRol'.",
        'nuevo_rol' => $nuevoRol,
    ]);
} catch (PDOException $e) {
    error_log('admin_cambiar_rol: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error al cambiar el rol.']);
}
