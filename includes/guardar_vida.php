<?php
session_start();
require_once 'conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

//Leer id_partida SOLO desde sesión
$id_partida = isset($_SESSION['id_partida']) ? (int)$_SESSION['id_partida'] : null;
if (!$id_partida) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin sesión activa']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$nueva_vida = isset($data['vida']) ? (int)$data['vida'] : null;

if ($nueva_vida === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parámetro vida inválido']);
    exit;
}

$nueva_vida = max(0, min(100, $nueva_vida));

//Asegurar que existe el registro o actualizarlo
$stmt = $pdo->prepare("REPLACE INTO estado_personaje (id_partida, vida_actual) VALUES (?, ?)");
$stmt->execute([$id_partida, $nueva_vida]);

echo json_encode(['ok' => true, 'vida' => $nueva_vida, 'id_partida' => $id_partida]);
