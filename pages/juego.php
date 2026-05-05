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

$_SESSION['sala_actual'] = $id_sala_actual;

// 4. Enemigos (para el script de sonido)
$query_enemigos = $pdo->prepare("SELECT * FROM estado_enemigos WHERE sala_ubicacion = ? AND estado = 'vivo'");
$query_enemigos->execute([$id_sala_actual]);
$enemigo_presente = $query_enemigos->fetch();

// 5. Gestión de Partida y Sesión
$id_usuario = $_SESSION['usuario_id'] ?? null;

if (!$id_usuario) {
    header('Location: ../sessions/login.php');
    exit;
}

// Buscar o crear partida para el usuario
$stmt_partida = $pdo->prepare("SELECT id_partida FROM partida WHERE id_usuario = ? ORDER BY fecha_guardado DESC LIMIT 1");
$stmt_partida->execute([$id_usuario]);
$partida = $stmt_partida->fetch();

if (!$partida) {
    // Crear partida por defecto si no existe
    $stmt_crear = $pdo->prepare("INSERT INTO partida (id_usuario, ruta, sala_actual) VALUES (?, 'chico', 'banos_inicio')");
    $stmt_crear->execute([$id_usuario]);
    $id_partida = $pdo->lastInsertId();
} else {
    $id_partida = $partida['id_partida'];
}

$_SESSION['id_partida'] = $id_partida;

// Inicializar contenedores de sesión si no existen
if (!isset($_SESSION['eventos_recogidos_sesion'])) {
    $_SESSION['eventos_recogidos_sesion'] = [];
}
if (!isset($_SESSION['inventario_sesion'])) {
    $_SESSION['inventario_sesion'] = [];
}

// 6. Consultar eventos y filtrar
// Mezclamos lo que ya estaba en la DB (partida guardada) con lo de la sesión actual
$query_completados = $pdo->prepare("SELECT id_evento FROM eventos_completados WHERE id_partida = ?");
$query_completados->execute([$id_partida]);
$completados_db = $query_completados->fetchAll(PDO::FETCH_COLUMN);

// Combinar DB + Sesión
$completados = array_unique(array_merge($completados_db, $_SESSION['eventos_recogidos_sesion']));

$query_eventos = $pdo->prepare("SELECT * FROM eventos_interactivos WHERE id_sala = ?");
$query_eventos->execute([$id_sala_actual]);
$eventos = $query_eventos->fetchAll(PDO::FETCH_ASSOC);

// Pool de Loot (Items consumibles y Claves)
$loot_pool_names = [
    'Hierba Verde',
    'Cuchillo Defensivo',
    'Pólvora Gris',
    'Cinta de Guardado',
    'Munición de Pistola',
    'Munición de Escopeta',
    'Munición de Fusil',
    'Medallon de León',
    'Medallon de Unicornio',
    'Medallon de Doncella',
    'Caja Fuerte Portatil',
    'Llave de Diamante',
    'Llave de Pica',
    'Cortacadenas'
];
$placeholders = implode(',', array_fill(0, count($loot_pool_names), '?'));
$query_loot = $pdo->prepare("SELECT * FROM catalogo_items WHERE nombre IN ($placeholders)");
$query_loot->execute($loot_pool_names);

$items_pool = [];
$key_items_pool = [];
$ammo_pool = [];

while ($row = $query_loot->fetch(PDO::FETCH_ASSOC)) {
    if ($row['tipo'] === 'clave') {
        $key_items_pool[] = $row;
    } elseif ($row['tipo'] === 'municion') {
        $ammo_pool[] = $row;
    } else {
        $items_pool[] = $row;
    }
}

foreach ($eventos as $key => &$ev) {
    if (in_array($ev['id_evento'], $completados)) {
        unset($eventos[$key]);
        continue;
    }

    if ($ev['contenido_accion'] === 'random') {
        // Probabilidad de aparición: 60% (antes 15%)
        if (rand(1, 100) > 60) {
            unset($eventos[$key]);
            continue;
        }

        // Selección con pesos: 50% munición, 30% consumibles, 20% clave (si hay)
        $rand_val = rand(1, 100);
        $item_data = null;

        if ($rand_val <= 50 && !empty($ammo_pool)) {
            $item_data = $ammo_pool[array_rand($ammo_pool)];
        } elseif ($rand_val <= 80 && !empty($items_pool)) {
            $item_data = $items_pool[array_rand($items_pool)];
        } elseif (!empty($key_items_pool)) {
            $item_data = $key_items_pool[array_rand($key_items_pool)];
        } else {
            // Fallback
            $combined = array_merge($items_pool, $ammo_pool, $key_items_pool);
            if (!empty($combined)) {
                $item_data = $combined[array_rand($combined)];
            }
        }

        if ($item_data) {
            $ev['nombre_objeto'] = $item_data['nombre'];
            $ev['contenido_accion'] = $item_data['id_item'];
            $ev['imagen_item'] = $item_data['imagen_url'];
        } else {
            unset($eventos[$key]);
        }
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
    <style>
        /* Estilos Premium para el Menú de Guardado */
        #save-menu {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            backdrop-filter: blur(5px);
        }

        .save-container {
            width: 600px;
            background: #111;
            border: 2px solid #333;
            padding: 30px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            position: relative;
        }

        .save-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .save-header h2 {
            margin: 0;
            color: #ff0000;
            letter-spacing: 2px;
            font-size: 1.5rem;
        }

        .ink-ribbon-count {
            background: #222;
            padding: 5px 15px;
            border: 1px solid #444;
            color: #aaa;
            font-size: 0.9rem;
        }

        .save-hint {
            color: #888;
            font-style: italic;
            margin-bottom: 20px;
        }

        .save-slots {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 30px;
        }

        .save-slot {
            display: flex;
            align-items: center;
            background: #1a1a1a;
            border: 1px solid #333;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .save-slot:hover {
            background: #252525;
            border-color: #ff0000;
            transform: translateX(10px);
        }

        .slot-number {
            font-size: 1.5rem;
            color: #444;
            margin-right: 20px;
            font-family: 'Courier New', Courier, monospace;
        }

        .save-slot:hover .slot-number {
            color: #ff0000;
        }

        .slot-info {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .slot-status {
            color: #eee;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .slot-date {
            font-size: 0.8rem;
            color: #666;
        }

        #btn-cancelar-guardado {
            width: 100%;
            background: #333;
            border: none;
            color: #fff;
            padding: 10px;
            cursor: pointer;
            transition: background 0.3s;
        }

        #btn-cancelar-guardado:hover {
            background: #444;
        }
    </style>
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
            <?php if (!in_array($ev['id_evento'], $completados)): ?>
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
            <?php endif; ?>
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

        <div id="inventory-screen" style="display: none;">
            <div class="inventory-container">
                <h2>INVENTARIO</h2>
                <div class="inventory-grid" id="inventory-grid">
                </div>
                <div class="item-details" id="item-details" style="display: flex;">
                    <div style="flex-grow: 1;">
                        <h3 id="detail-name">Selecciona un objeto</h3>
                        <p id="detail-description">Pasa el ratón sobre un objeto para ver sus detalles.</p>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <button id="btn-examinar" class="hud-btn" style="display: none;">EXAMINAR</button>
                        <button id="btn-eliminar" class="hud-btn"
                            style="display: none; background-color: #600;">ELIMINAR</button>
                    </div>
                </div>
                <button id="btn-cerrar-inventario">CERRAR (ESC)</button>
            </div>
        </div>

        <div id="save-menu" style="display: none;">
            <div class="save-container">
                <div class="save-header">
                    <h2>MÁQUINA DE ESCRIBIR</h2>
                    <div class="ink-ribbon-count">CINTAS: <span id="ribbon-count">0</span></div>
                </div>
                <p class="save-hint">Selecciona un slot para guardar tu progreso.</p>
                <div class="save-slots">
                    <div class="save-slot" data-slot="1">
                        <span class="slot-number">01</span>
                        <div class="slot-info">
                            <span class="slot-status">VACÍO</span>
                            <span class="slot-date">--/--/-- --:--</span>
                        </div>
                    </div>
                    <div class="save-slot" data-slot="2">
                        <span class="slot-number">02</span>
                        <div class="slot-info">
                            <span class="slot-status">VACÍO</span>
                            <span class="slot-date">--/--/-- --:--</span>
                        </div>
                    </div>
                    <div class="save-slot" data-slot="3">
                        <span class="slot-number">03</span>
                        <div class="slot-info">
                            <span class="slot-status">VACÍO</span>
                            <span class="slot-date">--/--/-- --:--</span>
                        </div>
                    </div>
                </div>
                <button id="btn-cancelar-guardado" class="hud-btn">CANCELAR</button>
            </div>
        </div>

    </div>

    <script src="../js/movimientos.js"></script>
    <script src="../js/interacciones.js"></script>
    <script src="../js/inventario.js"></script>
    <script>
        const catalogoArchivos = <?php echo json_encode($archivos); ?>;
        const tension = "<?php echo $enemigo_presente ? 'alta' : 'baja'; ?>";
        console.log("Sistema de sonido: Nivel " + tension);
    </script>
</body>

</html>