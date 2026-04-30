<?php
// 1. Conexión y sesión
session_start();
require_once '../includes/conexion.php';

// 2. Determinar la sala actual
$id_sala_actual = $_GET['sala'] ?? 'banos_inicio';

// 3. Consultar los datos de la sala
$query_sala = $pdo->prepare("SELECT * FROM catalogo_salas WHERE id_sala = ?");
$query_sala->execute([$id_sala_actual]);
$sala = $query_sala->fetch(PDO::FETCH_ASSOC);

// 4. Enemigos (para el script de sonido)
$query_enemigos = $pdo->prepare("SELECT * FROM estado_enemigos WHERE sala_ubicacion = ? AND estado = 'vivo'");
$query_enemigos->execute([$id_sala_actual]);
$enemigo_presente = $query_enemigos->fetch();

// 5. Consultar eventos interactivos de la sala
$id_partida = $_SESSION['id_partida'] ?? 1;

// Filtrar eventos ya completados (recogidos)
$query_completados = $pdo->prepare("SELECT id_evento FROM eventos_completados WHERE id_partida = ?");
$query_completados->execute([$id_partida]);
$completados = $query_completados->fetchAll(PDO::FETCH_COLUMN);

$query_eventos = $pdo->prepare("SELECT * FROM eventos_interactivos WHERE id_sala = ?");
$query_eventos->execute([$id_sala_actual]);
$eventos = $query_eventos->fetchAll(PDO::FETCH_ASSOC);

// Pool de Loot Aleatorio (Buscamos por nombre para evitar errores de ID)
$loot_pool_names = ['Hierba Verde', 'Cuchillo Defensivo', 'Pólvora Gris', 'Cinta de Guardado'];
$placeholders = implode(',', array_fill(0, count($loot_pool_names), '?'));
$query_loot = $pdo->prepare("SELECT * FROM catalogo_items WHERE nombre IN ($placeholders)");
$query_loot->execute($loot_pool_names);

$items_pool = [];
while ($row = $query_loot->fetch(PDO::FETCH_ASSOC)) {
    $items_pool[$row['id_item']] = $row;
}

foreach ($eventos as $key => &$ev) {
    if (in_array($ev['id_evento'], $completados)) {
        unset($eventos[$key]);
        continue;
    }

    if ($ev['contenido_accion'] === 'random') {
        // Si no hay items en el pool, no mostramos nada para evitar el error
        if (empty($items_pool)) {
            unset($eventos[$key]);
            continue;
        }

        if (rand(1, 100) > 85) { // 85% de probabilidad de que aparezca
            unset($eventos[$key]);
            continue;
        }

        $item_data = $items_pool[array_rand($items_pool)];
        $ev['nombre_objeto'] = $item_data['nombre'];
        $ev['contenido_accion'] = $item_data['id_item'];
        $ev['imagen_item'] = $item_data['imagen_url'];
    } elseif ($ev['tipo_accion'] === 'recoger_item' && is_numeric($ev['contenido_accion'])) {
        // Cargar imagen para items fijos
        $id_item = $ev['contenido_accion'];
        $stmt_item = $pdo->prepare("SELECT imagen_url FROM catalogo_items WHERE id_item = ?");
        $stmt_item->execute([$id_item]);
        $ev['imagen_item'] = $stmt_item->fetchColumn();
    } elseif ($ev['tipo_accion'] === 'recoger_arma' && is_numeric($ev['contenido_accion'])) {
        // Cargar imagen para armas fijas
        $id_arma = $ev['contenido_accion'];
        $stmt_arma = $pdo->prepare("SELECT imagen_url FROM catalogo_armas WHERE id_arma = ?");
        $stmt_arma->execute([$id_arma]);
        $ev['imagen_item'] = $stmt_arma->fetchColumn();
    }
}

// 6. Consultar todos los archivos para el visor
$query_archivos = $pdo->query("SELECT * FROM catalogo_archivos");
$archivos = $query_archivos->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Evil - <?php echo $sala['nombre_visual']; ?></title>

    <link rel="stylesheet" href="../styles/juego.css">
    <link rel="stylesheet" href="../styles/inventario.css">
</head>

<body>
    <!-- MENÚ DE PAUSA -->
    <div id="game-container" style="background-image: url('<?php echo $sala['imagen_url']; ?>');">

        <!-- MENÚ DE PAUSA -->
        <div id="pause-menu" style="display: none;">
            <h2>PAUSA</h2>
            <button id="btn-continuar">Continuar (ESC)</button>
            <button id="btn-cargar">Cargar Partida</button>
            <button id="btn-salir">Salir del Juego</button>
        </div>

        <div class="hud-top">
            <span class="location-name"><?php echo $sala['nombre_visual']; ?></span>
            <button id="btn-inventario" class="hud-btn">INVENTARIO (TAB)</button>
        </div>

        <div class="navigation-controls">
            <?php if ($sala['norte']): ?>
                <a href="juego.php?sala=<?php echo $sala['norte']; ?>" class="nav-btn north">▲</a>
            <?php endif; ?>
            <?php if ($sala['sur']): ?>
                <a href="juego.php?sala=<?php echo $sala['sur']; ?>" class="nav-btn south">▼</a>
            <?php endif; ?>
            <?php if ($sala['este']): ?>
                <a href="juego.php?sala=<?php echo $sala['este']; ?>" class="nav-btn east">►</a>
            <?php endif; ?>
            <?php if ($sala['oeste']): ?>
                <a href="juego.php?sala=<?php echo $sala['oeste']; ?>" class="nav-btn west">◄</a>
            <?php endif; ?>
        </div>

        <div class="message-box">
            <p><?php echo $sala['descripcion']; ?></p>
        </div>

        <!-- RENDERIZAR EVENTOS DESDE LA DB -->
        <?php foreach ($eventos as $ev): ?>
            <div class="hotspot <?php echo !empty($ev['imagen_item']) ? 'has-item' : ''; ?>" style="left: <?php echo $ev['xmin']; ?>%; 
                        top: <?php echo $ev['ymin']; ?>%; 
                        width: <?php echo ($ev['xmax'] - $ev['xmin']); ?>%; 
                        height: <?php echo ($ev['ymax'] - $ev['ymin']); ?>%;"
                title="<?php echo $ev['nombre_objeto']; ?>"
                onclick='ejecutarEvento(<?php echo json_encode($ev); ?>, event)'>

                <?php if (!empty($ev['imagen_item'])): ?>
                    <img src="<?php echo $ev['imagen_item']; ?>" alt="Objeto" class="item-visual">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <!-- VISOR DE NOTAS (MODAL) -->
        <div id="note-viewer" style="display: none;">
            <div class="note-container">
                <img src="../img/nota.png" alt="Papel de nota" class="note-paper" id="note-img">
                <div class="note-content">
                    <h3 id="note-title">Título de la Nota</h3>
                    <div id="note-body">Cuerpo de la nota...</div>
                </div>
                <button id="btn-cerrar-nota">CERRAR (ESC)</button>
            </div>
        </div>

        <!-- INVENTARIO (MODAL) -->
        <div id="inventory-screen" style="display: none;">
            <div class="inventory-container">
                <h2>INVENTARIO</h2>
                <div class="inventory-grid" id="inventory-grid">
                    <!-- Los slots se generarán dinámicamente -->
                </div>
                <div class="item-details" id="item-details">
                    <h3 id="detail-name">Selecciona un objeto</h3>
                    <p id="detail-description">Pasa el ratón sobre un objeto para ver sus detalles.</p>
                </div>
                <button id="btn-cerrar-inventario">CERRAR (ESC)</button>
            </div>
        </div>

    </div>

    <script src="../js/movimientos.js"></script>
    <script src="../js/interacciones.js"></script>
    <script src="../js/inventario.js"></script>
    <script src="../js/eventos_este.js"></script>
    <script src="../js/eventos_oeste.js"></script>
    <script>
        // Pasar archivos a JS
        const catalogoArchivos = <?php echo json_encode($archivos); ?>;

        // Lógica de tensión para tu API de Python
        const tension = "<?php echo $enemigo_presente ? 'alta' : 'baja'; ?>";
        console.log("Sistema de sonido: Nivel " + tension);

        // Aquí podrías añadir el fetch a tu sound_service.py
    </script>
</body>