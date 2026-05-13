<?php
require_once __DIR__ . '/../../includes/conexion.php';

header('Content-Type: text/plain');
echo "Iniciando limpieza profunda de inventario...\n";

try {
    $stmt_items = $pdo->query("SELECT id_registro, id_objeto FROM inventario WHERE tipo_objeto = 'item'");
    while ($row = $stmt_items->fetch(PDO::FETCH_ASSOC)) {
        $st = $pdo->prepare("SELECT id_item FROM catalogo_items WHERE id_item = ?");
        $st->execute([$row['id_objeto']]);
        if (!$st->fetch()) {
            $pdo->prepare("DELETE FROM inventario WHERE id_registro = ?")->execute([$row['id_registro']]);
            echo "Eliminado item huérfano ID: " . $row['id_objeto'] . "\n";
        }
    }

    $stmt_armas = $pdo->query("SELECT id_registro, id_objeto FROM inventario WHERE tipo_objeto = 'arma'");
    while ($row = $stmt_armas->fetch(PDO::FETCH_ASSOC)) {
        $st = $pdo->prepare("SELECT id_arma FROM catalogo_armas WHERE id_arma = ?");
        $st->execute([$row['id_objeto']]);
        if (!$st->fetch()) {
            $pdo->prepare("DELETE FROM inventario WHERE id_registro = ?")->execute([$row['id_registro']]);
            echo "Eliminada arma huérfana ID: " . $row['id_objeto'] . "\n";
        }
    }

    $stmt_partidas = $pdo->query("SELECT DISTINCT id_partida FROM inventario");
    while ($id_partida = $stmt_partidas->fetchColumn()) {
        $stmt_inv = $pdo->prepare("SELECT id_registro FROM inventario WHERE id_partida = ? ORDER BY id_registro ASC");
        $stmt_inv->execute([$id_partida]);
        $items = $stmt_inv->fetchAll(PDO::FETCH_COLUMN);

        foreach ($items as $index => $id_registro) {
            if ($index < 8) {
                $pdo->prepare("UPDATE inventario SET posicion_slot = ? WHERE id_registro = ?")
                    ->execute([$index, $id_registro]);
            } else {
                $pdo->prepare("DELETE FROM inventario WHERE id_registro = ?")->execute([$id_registro]);
                echo "Eliminado objeto excedente (más de 8) en partida $id_partida\n";
            }
        }
    }

    echo "¡Limpieza completada! Todos los inventarios están ahora limitados a 8 espacios únicos.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>