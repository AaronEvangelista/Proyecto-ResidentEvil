<?php
require_once '../../includes/conexion.php';

try {
    // Quitar la conexión oeste de oficina_oeste (no debe aparecer flecha izquierda)
    $stmt = $pdo->prepare(
        "UPDATE catalogo_salas SET oeste = NULL WHERE id_sala = 'oficina_oeste'"
    );
    $stmt->execute();

    // Verificar
    $row = $pdo->query("SELECT oeste FROM catalogo_salas WHERE id_sala = 'oficina_oeste'")->fetch();
    echo "OK: oficina_oeste.oeste actualizado a: " . ($row['oeste'] ?? 'NULL');
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
