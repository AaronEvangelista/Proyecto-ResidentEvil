<?php
session_start();
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id_registro = $data['id_registro'] ?? null;
    $id_partida = $_SESSION['id_partida'] ?? 1;

    // Si es un objeto que solo existe en la sesión (aún no guardado en DB)
    if (strpos((string)$id_registro, 'session_') === 0) {
        $index = (int)str_replace('session_', '', $id_registro);
        if (isset($_SESSION['inventario_sesion'][$index])) {
            unset($_SESSION['inventario_sesion'][$index]);
            // Reindexar el array para evitar huecos numéricos
            $_SESSION['inventario_sesion'] = array_values($_SESSION['inventario_sesion']);
            echo json_encode(['success' => true, 'message' => 'Objeto descartado (no guardado aún).']);
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