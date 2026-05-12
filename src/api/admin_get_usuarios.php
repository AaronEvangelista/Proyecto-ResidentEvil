<?php
session_start();
require_once __DIR__ . '/../../includes/conexion.php';

header('Content-Type: application/json');

if (empty($_SESSION['logueado']) || ($_SESSION['usuario_rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado.']);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT id_usuario, nombre, email, rol, zombies_visibles, fecha_registro
        FROM   usuarios
        ORDER BY fecha_registro DESC
    ");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtPartidas = $pdo->query("
        SELECT id_usuario, COUNT(*) AS total
        FROM   partida
        GROUP BY id_usuario
    ");
    $partidasPorUsuario = [];
    foreach ($stmtPartidas->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $partidasPorUsuario[$row['id_usuario']] = (int) $row['total'];
    }

    foreach ($usuarios as &$u) {
        $u['partidas'] = $partidasPorUsuario[$u['id_usuario']] ?? 0;
    }

    $totalUsuarios = count($usuarios);
    $totalPartidas = array_sum($partidasPorUsuario);

    echo json_encode([
        'success' => true,
        'usuarios' => $usuarios,
        'total_usuarios' => $totalUsuarios,
        'total_partidas' => $totalPartidas,
    ]);
} catch (PDOException $e) {
    error_log('admin_get_usuarios: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error al obtener usuarios.']);
}
