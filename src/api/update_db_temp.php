<?php
require_once '../../includes/conexion.php';

try {
    $stmt = $pdo->prepare("UPDATE eventos_interactivos SET tipo_accion = 'puzzle', contenido_accion = 'caja_fuerte', requiere_item = '' WHERE nombre_objeto = 'CAJA FUERTE CORTACADENAS'");
    $stmt->execute();
    echo "DB actualitzada exitosamente";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
