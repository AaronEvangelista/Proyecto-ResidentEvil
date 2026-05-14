<?php
session_start();
require_once '../../includes/conexion.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$id_partida = $_SESSION['id_partida'] ?? null;
$id_boss = $_POST['id_boss'] ?? 9;
$sala = $_POST['sala'] ?? 'sala_final';

if (!$id_partida) {
    echo json_encode(['success' => false, 'error' => 'Sesión no válida']);
    exit;
}

try {
    $pdo->prepare("DELETE FROM estado_enemigos WHERE id_partida = ? AND sala_ubicacion = ? AND estado = 'vivo'")->execute([$id_partida, $sala]);

    $q_vida = $pdo->prepare("SELECT vida_maxima FROM catalogo_enemigos WHERE id_enemigo = ?");
    $q_vida->execute([$id_boss]);
    $vida_base = $q_vida->fetchColumn();

    if (!$vida_base) {
        echo json_encode(['success' => false, 'error' => 'Jefe no encontrado en el catálogo']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO estado_enemigos (id_partida, id_enemigo, sala_ubicacion, vida_restante, estado) VALUES (?, ?, ?, ?, 'vivo')");
    $stmt->execute([$id_partida, $id_boss, $sala, $vida_base]);
    $id_reg = $pdo->lastInsertId();

    echo json_encode(['success' => true, 'id_registro' => $id_reg]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>