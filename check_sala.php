<?php
require __DIR__ . '/includes/conexion.php';
$r = $pdo->query("SELECT id_sala, nombre_visual FROM catalogo_salas WHERE id_sala='sala_final'");
$row = $r->fetch();
echo $row ? 'EXISTE: ' . $row['nombre_visual'] : 'NO EXISTE EN BD';
