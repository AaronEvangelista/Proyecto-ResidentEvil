<?php
session_start();
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id_registro = $data['id_registro'] ?? null;
    $id_partida = $_SESSION['id_partida'] ?? 1;

    if (strpos((string) $id_registro, 'session_') === 0) {
        $index = (int) str_replace('session_', '', $id_registro);
        if (isset($_SESSION['inventario_sesion'][$index])) {
            $s_item = $_SESSION['inventario_sesion'][$index];

            if ($s_item['tipo_objeto'] === 'arma') {
                echo json_encode(['success' => false, 'error' => 'No puedes eliminar armas.']);
                exit;
            }

            if ($s_item['tipo_objeto'] === 'item') {
                $st = $pdo->prepare("SELECT tipo FROM catalogo_items WHERE id_item = ?");
                $st->execute([$s_item['id_objeto']]);
                if ($st->fetchColumn() === 'clave') {
                    echo json_encode(['success' => false, 'error' => 'No puedes eliminar objetos clave.']);
                    exit;
                }
            }

            unset($_SESSION['inventario_sesion'][$index]);
            $_SESSION['inventario_sesion'] = array_values($_SESSION['inventario_sesion']);
            echo json_encode(['success' => true, 'message' => 'Objeto descartado.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se encontró el objeto en la sesión actual.']);
        }
        exit;
    }

    if ($id_registro) {
        try {
            $stmt_check = $pdo->prepare("SELECT tipo_objeto FROM inventario WHERE id_registro = ? AND id_partida = ?");
            $stmt_check->execute([$id_registro, $id_partida]);
            $tipo = $stmt_check->fetchColumn();

            if (!$tipo) {
                echo json_encode(['success' => false, 'error' => 'Objeto no encontrado.']);
                exit;
            }

            if ($tipo === 'arma') {
                echo json_encode(['success' => false, 'error' => 'No puedes eliminar armas.']);
                exit;
            }

            if ($tipo === 'item') {
                $stmt_clave = $pdo->prepare("
                    SELECT tipo FROM catalogo_items ci
                    JOIN inventario i ON ci.id_item = i.id_objeto
                    WHERE i.id_registro = ?
                ");
                $stmt_clave->execute([$id_registro]);
                $tipo_cat = $stmt_clave->fetchColumn();

                if ($tipo_cat === 'clave') {
                    echo json_encode(['success' => false, 'error' => 'No puedes eliminar objetos clave.']);
                    exit;
                }
            }

            $stmt_del = $pdo->prepare("DELETE FROM inventario WHERE id_registro = ? AND id_partida = ?");
            $stmt_del->execute([$id_registro, $id_partida]);

            echo json_encode(['success' => true, 'message' => 'Objeto eliminado.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'ID de registro no proporcionado.']);
    }
}
?>