<?php
$db = new PDO('sqlite:' . __DIR__ . '/resident_evil.sqlite3');
$rows = $db->query('SELECT id_archivo, nombre, imagen_url FROM catalogo_archivos LIMIT 12')->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
foreach($rows as $r) {
    echo $r['id_archivo'] . ' | ' . $r['nombre'] . ' | ' . ($r['imagen_url'] ?? 'NULL') . "\n";
}
echo "</pre>";
?>
