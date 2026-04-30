<?php
session_start();
require_once '../includes/conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id_registro_arrastrado = $data['id_registro_arrastrado'] ?? null;
    $id_registro_destino = $data['id_registro_destino'] ?? null;
    $id_partida = $_SESSION['id_partida'] ?? 1;

    if ($id_registro_arrastrado && $id_registro_destino) {
        try {
            $pdo->beginTransaction();

            // 1. Obtener información de ambos objetos desde el inventario
            $stmt = $pdo->prepare("
                SELECT i.id_registro, i.id_objeto, i.tipo_objeto, c.nombre 
                FROM inventario i
                LEFT JOIN catalogo_items c ON i.id_objeto = c.id_item AND i.tipo_objeto = 'item'
                WHERE i.id_registro IN (?, ?) AND i.id_partida = ?
            ");
            $stmt->execute([$id_registro_arrastrado, $id_registro_destino, $id_partida]);
            $objetos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($objetos) !== 2) {
                throw new Exception("No se encontraron los objetos.");
            }

            // Identificar cuál es cuál
            $obj1 = $objetos[0]['id_registro'] == $id_registro_arrastrado ? $objetos[0] : $objetos[1];
            $obj2 = $objetos[0]['id_registro'] == $id_registro_destino ? $objetos[0] : $objetos[1];

            // Solo combinamos items (pólvoras)
            if ($obj1['tipo_objeto'] !== 'item' || $obj2['tipo_objeto'] !== 'item') {
                echo json_encode(['success' => false, 'action' => 'swap']);
                $pdo->rollBack();
                exit;
            }

            $nombre1 = $obj1['nombre'];
            $nombre2 = $obj2['nombre'];

            $resultado_nombre = null;
            $resultado_tipo = 'item'; // por defecto municion es item
            $es_granada = false;

            // Reglas de Combinación
            if ($nombre1 === 'Pólvora Gris' && $nombre2 === 'Pólvora Gris') {
                $resultado_nombre = 'Munición de Pistola';
            } elseif (
                ($nombre1 === 'Pólvora Gris' && $nombre2 === 'Pólvora Amarilla') ||
                ($nombre1 === 'Pólvora Amarilla' && $nombre2 === 'Pólvora Gris')
            ) {
                $resultado_nombre = 'Munición de Escopeta';
            } elseif (
                ($nombre1 === 'Pólvora Gris' && $nombre2 === 'Pólvora Roja') ||
                ($nombre1 === 'Pólvora Roja' && $nombre2 === 'Pólvora Gris')
            ) {
                $resultado_nombre = 'Munición de Fusil';
            } elseif ($nombre1 === 'Pólvora Amarilla' && $nombre2 === 'Pólvora Amarilla') {
                $resultado_nombre = 'Granada de Fragmentación';
                $resultado_tipo = 'arma';
                $es_granada = true;
            } elseif ($nombre1 === 'Pólvora Roja' && $nombre2 === 'Pólvora Roja') {
                $resultado_nombre = 'Granada de Fragmentación';
                $resultado_tipo = 'arma';
                $es_granada = true;
            }

            if ($resultado_nombre) {
                // Hay combinación!
                // Obtener ID del resultado
                if ($es_granada) {
                    $stmt_res = $pdo->prepare("SELECT id_arma as id_resultado FROM catalogo_armas WHERE nombre = ?");
                } else {
                    $stmt_res = $pdo->prepare("SELECT id_item as id_resultado FROM catalogo_items WHERE nombre = ?");
                }
                $stmt_res->execute([$resultado_nombre]);
                $id_resultado = $stmt_res->fetchColumn();

                if ($id_resultado) {
                    // Guardar el slot destino antes de borrar
                    $slot_final = $obj2['posicion_slot'] ?? $obj1['posicion_slot'];

                    // Borrar los dos objetos originales
                    $stmt_del = $pdo->prepare("DELETE FROM inventario WHERE id_registro IN (?, ?)");
                    $stmt_del->execute([$id_registro_arrastrado, $id_registro_destino]);

                    // Insertar el nuevo objeto en el slot
                    $stmt_insert = $pdo->prepare("INSERT INTO inventario (id_partida, tipo_objeto, id_objeto, cantidad, posicion_slot) VALUES (?, ?, ?, 1, ?)");
                    $stmt_insert->execute([$id_partida, $resultado_tipo, $id_resultado, $slot_final]);

                    $pdo->commit();
                    echo json_encode(['success' => true, 'action' => 'combined', 'message' => "Has creado: $resultado_nombre"]);
                    exit;
                }
            }
            
            // Si llegamos aquí, no son combinables. Intercambiamos posiciones (swap)
            $pdo->commit();
            echo json_encode(['success' => false, 'action' => 'swap']);
            
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (Exception $e) {
             if ($pdo->inTransaction()) $pdo->rollBack();
             echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Faltan datos']);
    }
}
?>
