<?php
session_start();
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id_evento = $data['id_evento'] ?? null;
    $tipo_objeto = $data['tipo_objeto'] ?? null;
    $id_objeto = $data['id_objeto'] ?? null;
    $id_partida = $_SESSION['id_partida'] ?? 1;

    if ($id_evento) {
        try {
            if (!isset($_SESSION['eventos_recogidos_sesion'])) {
                $_SESSION['eventos_recogidos_sesion'] = [];
            }
            if (!in_array($id_evento, $_SESSION['eventos_recogidos_sesion'])) {
                $_SESSION['eventos_recogidos_sesion'][] = $id_evento;
            }

            if ($tipo_objeto && $id_objeto) {
                if (!isset($_SESSION['inventario_sesion'])) {
                    $_SESSION['inventario_sesion'] = [];
                }

                // Las armas empiezan con 6 balas; los ítems con cantidad 1
                $cantidad_inicial = ($tipo_objeto === 'arma') ? 6 : 1;
                $_SESSION['inventario_sesion'][] = [
                    'tipo_objeto' => $tipo_objeto,
                    'id_objeto'   => $id_objeto,
                    'cantidad'    => $cantidad_inicial
                ];
            }

            echo json_encode(['success' => true, 'message' => 'Objeto recogido en sesión']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Faltan datos']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
?>
