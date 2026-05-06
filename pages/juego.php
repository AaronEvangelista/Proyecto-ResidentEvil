<?php
session_start();
require_once '../includes/conexion.php';

$id_sala_actual = $_GET['sala'] ?? 'banos_inicio';

//1. CARGAR DATOS DE LA SALA
$query_sala = $pdo->prepare("SELECT * FROM catalogo_salas WHERE id_sala = ?");
$query_sala->execute([$id_sala_actual]);
$sala = $query_sala->fetch(PDO::FETCH_ASSOC);

if (!$sala) {
    header("Location: juego.php?sala=banos_inicio");
    exit;
}
$_SESSION['sala_actual'] = $id_sala_actual;

//2. USUARIO / PARTIDA
$id_usuario = $_SESSION['usuario_id'] ?? null;
if (!$id_usuario) {
    header('Location: ../sessions/login.php');
    exit;
}

try {
    $stmt_partida = $pdo->prepare("SELECT id_partida FROM partida WHERE id_usuario = ? AND slot_numero = 0 ORDER BY fecha_guardado DESC LIMIT 1");
    $stmt_partida->execute([$id_usuario]);
    $partida = $stmt_partida->fetch();
} catch (PDOException $e) {
    // Si el error es por la columna faltante, la creamos dinámicamente
    if (strpos($e->getMessage(), 'no such column: slot_numero') !== false) {
        $pdo->exec("ALTER TABLE partida ADD COLUMN slot_numero INTEGER DEFAULT 0");
        // Reintentamos la consulta
        $stmt_partida = $pdo->prepare("SELECT id_partida FROM partida WHERE id_usuario = ? AND slot_numero = 0 ORDER BY fecha_guardado DESC LIMIT 1");
        $stmt_partida->execute([$id_usuario]);
        $partida = $stmt_partida->fetch();
    } else {
        throw $e;
    }
}

$forzar_nueva = isset($_GET['new']);

if (!$partida || $forzar_nueva) {
    $stmt_crear = $pdo->prepare("INSERT INTO partida (id_usuario, ruta, sala_actual, slot_numero) VALUES (?, 'chico', 'banos_inicio', 0)");
    $stmt_crear->execute([$id_usuario]);
    $id_partida = $pdo->lastInsertId();
    $stmt_estado = $pdo->prepare("INSERT INTO estado_personaje (id_partida, vida_actual) VALUES (?, 100)");
    $stmt_estado->execute([$id_partida]);
    
    //Limpiar inventario de sesión si es nueva partida
    if ($forzar_nueva) {
        $_SESSION['inventario_sesion'] = [];
        $_SESSION['eventos_recogidos_sesion'] = [];
    }
} else {
    $id_partida = $partida['id_partida'];
    
    //Verificar si el jugador está muerto en esta partida. Si es así, resetear vida para que pueda jugar.
    $st_check_v = $pdo->prepare("SELECT vida_actual FROM estado_personaje WHERE id_partida = ?");
    $st_check_v->execute([$id_partida]);
    $v_check = $st_check_v->fetchColumn();
    if ($v_check !== false && (int)$v_check <= 0) {
        $pdo->prepare("UPDATE estado_personaje SET vida_actual = 100 WHERE id_partida = ?")->execute([$id_partida]);
    }
}
$_SESSION['id_partida'] = $id_partida;

//3. PROCESAR RETORNO DE COMBATE (VICTORIA / HUIDA)
if (isset($_GET['muerto'], $_GET['id_reg'])) {
    $id_reg = (int)$_GET['id_reg'];
    $stmt_upd = $pdo->prepare("UPDATE estado_enemigos SET estado = 'muerto' WHERE id_registro = ? AND id_partida = ?");
    $stmt_upd->execute([$id_reg, $id_partida]);
    unset($_SESSION['huido_de'][$id_sala_actual]);
    header("Location: juego.php?sala=" . $id_sala_actual);
    exit;
}

if (isset($_GET['huir'], $_GET['id_reg'])) {
    if (!isset($_SESSION['huido_de'])) $_SESSION['huido_de'] = [];
    $_SESSION['huido_de'][$id_sala_actual] = (int)$_GET['id_reg'];
    header("Location: juego.php?sala=" . $id_sala_actual);
    exit;
}

//Inicializar huida
if (!isset($_SESSION['huido_de'])) $_SESSION['huido_de'] = [];
$id_reg_huido = $_SESSION['huido_de'][$id_sala_actual] ?? 0;

//Limpiar marcas de huida de otras salas
foreach (array_keys($_SESSION['huido_de']) as $s) {
    if ($s !== $id_sala_actual) unset($_SESSION['huido_de'][$s]);
}

//4. GENERADOR DE ENEMIGOS (SPAWN) 
//Salas con zombies básicos (IDs 1-4) → pueden reaparecer (40% al volver)
$salas_respawn = [
    'sala_espera'         => [1, 2, 3, 4],
    'oficina_este'        => [1, 2, 3, 4],
    'biblioteca'          => [1, 2, 3, 4],
];
//Salas con enemigos especiales → aparecen UNA sola vez, no respawnean
$salas_unicas = [
    'oficina_capitan'     => [6],   // Lastre
    'pasillo'             => [7],   // Espasmo
    'sala_interrogatorios'=> [7],   // Espasmo
    'sala_arte'           => [5],   // Licker
];

$distribucion_enemigos = array_merge($salas_respawn, $salas_unicas);
$puede_respawnear = isset($salas_respawn[$id_sala_actual]);

if (isset($distribucion_enemigos[$id_sala_actual])) {
    $q_existe = $pdo->prepare("SELECT id_registro FROM estado_enemigos WHERE id_partida = ? AND sala_ubicacion = ? AND estado = 'vivo' LIMIT 1");
    $q_existe->execute([$id_partida, $id_sala_actual]);
    $hay_vivo = $q_existe->fetchColumn();

    if (!$hay_vivo) {
        $q_veces = $pdo->prepare("SELECT COUNT(*) FROM estado_enemigos WHERE id_partida = ? AND sala_ubicacion = ?");
        $q_veces->execute([$id_partida, $id_sala_actual]);
        $veces_spawn = (int)$q_veces->fetchColumn();
        
        if ($veces_spawn === 0 || ($puede_respawnear && rand(1, 100) <= 40)) {
            $pool = $distribucion_enemigos[$id_sala_actual];
            $id_enemigo_eleg = $pool[array_rand($pool)];
            
            $q_vida = $pdo->prepare("SELECT vida_maxima FROM catalogo_enemigos WHERE id_enemigo = ?");
            $q_vida->execute([$id_enemigo_eleg]);
            $vida_base = $q_vida->fetchColumn();

            $pdo->prepare("INSERT INTO estado_enemigos (id_partida, id_enemigo, sala_ubicacion, vida_restante, estado) VALUES (?, ?, ?, ?, 'vivo')")
                ->execute([$id_partida, $id_enemigo_eleg, $id_sala_actual, $vida_base]);
        }
    }
}

//5. DETECTAR ENEMIGO ACTUAL
$q_ep = $pdo->prepare("SELECT ee.*, ce.nombre, ce.imagen_url FROM estado_enemigos ee JOIN catalogo_enemigos ce ON ee.id_enemigo = ce.id_enemigo WHERE ee.id_partida = ? AND ee.sala_ubicacion = ? AND ee.estado = 'vivo' LIMIT 1");
$q_ep->execute([$id_partida, $id_sala_actual]);
$enemigo_presente = $q_ep->fetch(PDO::FETCH_ASSOC);

$hay_combate = ($enemigo_presente && $id_reg_huido != $enemigo_presente['id_registro']);

//6. EVENTOS Y VIDA 
if (!isset($_SESSION['eventos_recogidos_sesion'])) $_SESSION['eventos_recogidos_sesion'] = [];
$query_eventos = $pdo->prepare("SELECT * FROM eventos_interactivos WHERE id_sala = ?");
$query_eventos->execute([$id_sala_actual]);
$eventos = $query_eventos->fetchAll(PDO::FETCH_ASSOC);

$query_archivos = $pdo->query("SELECT * FROM catalogo_archivos");
$archivos = $query_archivos->fetchAll(PDO::FETCH_ASSOC);

$st_vida = $pdo->prepare("SELECT vida_actual FROM estado_personaje WHERE id_partida = ?");
$st_vida->execute([$id_partida]);
$vida_p = $st_vida->fetchColumn() ?: 100;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Evil - <?php echo htmlspecialchars($sala['nombre_visual']); ?></title>
    <link rel="stylesheet" href="../styles/juego.css">
    <link rel="stylesheet" href="../styles/inventario.css">
    <style>
        .vats-life-container { position: fixed; bottom: 20px; left: 20px; width: 250px; background: rgba(0,0,0,0.7); border: 1px solid #333; padding: 10px; z-index: 1000; }
        .vats-label { color: #00ff66; font-size: 10px; letter-spacing: 1px; margin-bottom: 5px; }
        .vats-hp-fill { height: 12px; transition: width 0.5s; }
        .hp-fine { background: #00ff66; box-shadow: 0 0 10px #00ff66; }
        .hp-caution { background: #ffcc00; box-shadow: 0 0 10px #ffcc00; }
        .hp-danger { background: #ff4444; box-shadow: 0 0 10px #ff4444; }

        /* Estilos del Enemigo en Pantalla */
        .enemy-encounter {
            position: absolute; left: 30%; top: 15%; width: 40%; height: 70%;
            z-index: 999; display: flex; flex-direction: column; align-items: center; justify-content: center;
            cursor: crosshair; transition: transform 0.3s;
        }
        .enemy-encounter:hover { transform: scale(1.05); }
        .enemy-encounter img { height: 90%; filter: drop-shadow(0 0 15px rgba(255,0,0,0.6)); animation: creature-pulse 2s infinite; }
        .enemy-label { background: rgba(0,0,0,0.85); color: #ff0000; padding: 8px 15px; border: 1px solid #ff0000; font-family: 'Courier New', monospace; font-size: 0.9rem; text-align: center; margin-top: -20px; box-shadow: 0 0 10px red; }
        
        @keyframes creature-pulse { 0%, 100% { filter: drop-shadow(0 0 10px red); } 50% { filter: drop-shadow(0 0 25px red); } }
        
        .nav-blocked { opacity: 0; pointer-events: none; transition: opacity 0.5s; }
    </style>
</head>
<body>

<div class="vats-life-container">
    <div class="vats-label">CONDITION: <?php 
        if ($vida_p >= 75) echo "FINE"; 
        elseif ($vida_p >= 30) echo "CAUTION"; 
        else echo "DANGER"; 
    ?></div>
    <div style="background: #002200; height: 12px; width: 100%; border: 1px solid #00ff66;">
        <div class="vats-hp-fill <?php 
            echo ($vida_p >= 75 ? "hp-fine" : ($vida_p >= 30 ? "hp-caution" : "hp-danger")); 
        ?>" style="width:<?php echo $vida_p; ?>%;"></div>
    </div>
</div>

<div id="game-container" style="background-image: url('<?php echo $sala['imagen_url']; ?>');">

    <div class="hud-top">
        <span class="location-name"><?php echo htmlspecialchars($sala['nombre_visual']); ?></span>
        <button id="btn-inventario" class="hud-btn">INVENTARIO (TAB)</button>
    </div>

    <div class="navigation-controls <?php echo $hay_combate ? 'nav-blocked' : ''; ?>">
        <?php if ($sala['norte']): ?><a href="juego.php?sala=<?php echo $sala['norte']; ?>" class="nav-btn north">▲</a><?php endif; ?>
        <?php if ($sala['sur']):   ?><a href="juego.php?sala=<?php echo $sala['sur'];   ?>" class="nav-btn south">▼</a><?php endif; ?>
        <?php if ($sala['este']):  ?><a href="juego.php?sala=<?php echo $sala['este'];  ?>" class="nav-btn east" >►</a><?php endif; ?>
        <?php if ($sala['oeste']): ?><a href="juego.php?sala=<?php echo $sala['oeste']; ?>" class="nav-btn west" >◄</a><?php endif; ?>
    </div>

    <div class="message-box">
        <p><?php echo $hay_combate ? "¡Un engendro bloquea el camino!" : $sala['descripcion']; ?></p>
    </div>

    <?php if ($hay_combate): ?>
    <div class="enemy-encounter" onclick="window.location.href='combate.php?id_registro=<?php echo $enemigo_presente['id_registro']; ?>&vuelta=<?php echo $id_sala_actual; ?>'">
        <img src="<?php echo $enemigo_presente['imagen_url']; ?>" alt="Enemigo">
        <div class="enemy-label">
            <strong>ADVERTENCIA:</strong> ENEMIGO CERCANO<br>
            <small>PULSA PARA INICIAR COMBATE</small>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$hay_combate): ?>
        <?php foreach ($eventos as $ev): ?>
            <div class="hotspot" 
                 style="left:<?php echo $ev['xmin']; ?>%; top:<?php echo $ev['ymin']; ?>%; width:<?php echo ($ev['xmax']-$ev['xmin']); ?>%; height:<?php echo ($ev['ymax']-$ev['ymin']); ?>%;"
                 onclick='ejecutarEvento(<?php echo json_encode($ev); ?>, event)'>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div id="inventory-screen" style="display:none;">...</div>
    <div id="note-viewer" style="display:none;">...</div>

</div>

<script src="../js/movimientos.js"></script>
<script src="../js/interacciones.js"></script>
<script src="../js/inventario.js"></script>
<script>
    const catalogoArchivos = <?php echo json_encode($archivos); ?>;
    const tension = "<?php echo $hay_combate ? 'alta' : 'baja'; ?>";
</script>
</body>
</html>