<?php
require_once '../../includes/conexion.php';

echo "Corrigiendo slots duplicados...<br>";

try {
    $stmt_partidas = $pdo->query("SELECT DISTINCT id_partida FROM inventario");
    $partidas = $stmt_partidas->fetchAll(PDO::FETCH_COLUMN);

    foreach ($partidas as $id_partida) {
        $stmt_items = $pdo->prepare("SELECT id_registro FROM inventario WHERE id_partida = ? ORDER BY id_registro ASC");
        $stmt_items->execute([$id_partida]);
        $items = $stmt_items->fetchAll(PDO::FETCH_COLUMN);

        foreach ($items as $index => $id_registro) {
            if ($index < 8) {
                $stmt_upd = $pdo->prepare("UPDATE inventario SET posicion_slot = ? WHERE id_registro = ?");
                $stmt_upd->execute([$index, $id_registro]);
            } else {
                $stmt_upd = $pdo->prepare("UPDATE inventario SET posicion_slot = 7 WHERE id_registro = ?");
                $stmt_upd->execute([$id_registro]);
            }
        }
        echo "Partida $id_partida procesada.<br>";
    }
    echo "¡Listo! Todos los inventarios han sido reorganizados.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>