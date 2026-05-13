<?php
session_start();
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

$id_usuario = $_SESSION['usuario_id'] ?? null;

if (!$id_usuario) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT slot_numero, fecha_guardado, sala_actual FROM partida WHERE id_usuario = ? ORDER BY slot_numero ASC");
    $stmt->execute([$id_usuario]);
    $slots_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $slots = [
        1 => null,
        2 => null,
        3 => null
    ];

    foreach ($slots_db as $row) {
        $stmt_sala = $pdo->prepare("SELECT nombre_visual FROM catalogo_salas WHERE id_sala = ?");
        $stmt_sala->execute([$row['sala_actual']]);
        $nombre_sala = $stmt_sala->fetchColumn();

        $slots[$row['slot_numero']] = [
            'fecha' => date('d/m/Y H:i', strtotime($row['fecha_guardado'])),
            'ubicacion' => $nombre_sala ?: 'Desconocida'
        ];
    }

    $cintas = 0;

    $id_partida = $_SESSION['id_partida'] ?? 0;
    $stmt_cintas = $pdo->prepare("
        SELECT SUM(i.cantidad) 
        FROM inventario i 
        JOIN catalogo_items c ON i.id_objeto = c.id_item AND i.tipo_objeto = 'item'
        WHERE i.id_partida = ? AND c.nombre = 'Cinta de Guardado'
    ");
    $stmt_cintas->execute([$id_partida]);
    $cintas += (int) $stmt_cintas->fetchColumn();

    $inv_sesion = $_SESSION['inventario_sesion'] ?? [];
    foreach ($inv_sesion as $item) {
        if ($item['tipo_objeto'] === 'item') {
            $stmt_check = $pdo->prepare("SELECT nombre FROM catalogo_items WHERE id_item = ?");
            $stmt_check->execute([$item['id_objeto']]);
            if ($stmt_check->fetchColumn() === 'Cinta de Guardado') {
                $cintas += $item['cantidad'];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'slots' => $slots,
        'cintas' => $cintas
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>