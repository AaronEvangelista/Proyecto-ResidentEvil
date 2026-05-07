<?php
session_start();
require_once '../includes/conexion.php';

// Admin: control de visibilidad de zombies
$zombiesVisibles = (int) ($_SESSION['zombies_visibles'] ?? 1);
$usuarioRol = $_SESSION['usuario_rol'] ?? 'jugador';

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
    if ($v_check !== false && (int) $v_check <= 0) {
        $pdo->prepare("UPDATE estado_personaje SET vida_actual = 100 WHERE id_partida = ?")->execute([$id_partida]);
    }
}
$_SESSION['id_partida'] = $id_partida;

//3. PROCESAR RETORNO DE COMBATE (VICTORIA / HUIDA)
if (isset($_GET['muerto'], $_GET['id_reg'])) {
    $id_reg = (int) $_GET['id_reg'];
    $stmt_upd = $pdo->prepare("UPDATE estado_enemigos SET estado = 'muerto' WHERE id_registro = ? AND id_partida = ?");
    $stmt_upd->execute([$id_reg, $id_partida]);
    unset($_SESSION['huido_de'][$id_sala_actual]);
    header("Location: juego.php?sala=" . $id_sala_actual);
    exit;
}

if (isset($_GET['huir'], $_GET['id_reg'])) {
    if (!isset($_SESSION['huido_de']))
        $_SESSION['huido_de'] = [];
    $_SESSION['huido_de'][$id_sala_actual] = (int) $_GET['id_reg'];
    header("Location: juego.php?sala=" . $id_sala_actual);
    exit;
}

//Inicializar huida
if (!isset($_SESSION['huido_de']))
    $_SESSION['huido_de'] = [];
$id_reg_huido = $_SESSION['huido_de'][$id_sala_actual] ?? 0;

//Limpiar marcas de huida de otras salas
foreach (array_keys($_SESSION['huido_de']) as $s) {
    if ($s !== $id_sala_actual)
        unset($_SESSION['huido_de'][$s]);
}

//4. GENERADOR DE ENEMIGOS (SPAWN)
$enemigo_presente = null;
$hay_combate = false;

if ($zombiesVisibles) {
    //Salas con zombies básicos (IDs 1-4) → pueden reaparecer (40% al volver)
    $salas_respawn = [
        'sala_espera' => [1, 2, 3, 4],
        'oficina_este' => [1, 2, 3, 4],
        'biblioteca' => [1, 2, 3, 4],
    ];
    //Salas con enemigos especiales → aparecen UNA sola vez, no respawnean
    $salas_unicas = [
        'oficina_capitan' => [6],   // Lastre
        'pasillo' => [7],   // Espasmo
        'sala_interrogatorios' => [7],   // Espasmo
        'sala_arte' => [5],   // Licker
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
            $veces_spawn = (int) $q_veces->fetchColumn();

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
} // fin if ($zombiesVisibles) — bloque spawn

//5. DETECTAR ENEMIGO ACTUAL
if ($zombiesVisibles) {
    $q_ep = $pdo->prepare("SELECT ee.*, ce.nombre, ce.imagen_url FROM estado_enemigos ee JOIN catalogo_enemigos ce ON ee.id_enemigo = ce.id_enemigo WHERE ee.id_partida = ? AND ee.sala_ubicacion = ? AND ee.estado = 'vivo' LIMIT 1");
    $q_ep->execute([$id_partida, $id_sala_actual]);
    $enemigo_presente = $q_ep->fetch(PDO::FETCH_ASSOC);

    $hay_combate = ($enemigo_presente && $id_reg_huido != $enemigo_presente['id_registro']);
}

//6. EVENTOS Y VIDA 
if (!isset($_SESSION['eventos_recogidos_sesion']))
    $_SESSION['eventos_recogidos_sesion'] = [];
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
    'Munición de Fusil'
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

$completados = $_SESSION['eventos_recogidos_sesion'] ?? [];

foreach ($eventos as $key => &$ev) {
    if (in_array($ev['id_evento'], $completados)) {
        unset($eventos[$key]);
        continue;
    }

    if ($ev['contenido_accion'] === 'random') {
        // Probabilidad de aparición: 60%
        if (rand(1, 100) > 60) {
            unset($eventos[$key]);
            continue;
        }

        $rand_val = rand(1, 100);
        $item_data = null;

        if ($rand_val <= 50 && !empty($ammo_pool)) {
            $item_data = $ammo_pool[array_rand($ammo_pool)];
        } elseif ($rand_val <= 80 && !empty($items_pool)) {
            $item_data = $items_pool[array_rand($items_pool)];
        } elseif (!empty($key_items_pool)) {
            $item_data = $key_items_pool[array_rand($key_items_pool)];
        } else {
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
        $id_item = $ev['contenido_accion'];
        $stmt_item = $pdo->prepare("SELECT imagen_url FROM catalogo_items WHERE id_item = ?");
        $stmt_item->execute([$id_item]);
        $ev['imagen_item'] = $stmt_item->fetchColumn();
    } elseif ($ev['tipo_accion'] === 'recoger_arma' && is_numeric($ev['contenido_accion'])) {
        $id_arma = $ev['contenido_accion'];
        $stmt_arma = $pdo->prepare("SELECT imagen_url FROM catalogo_armas WHERE id_arma = ?");
        $stmt_arma->execute([$id_arma]);
        $ev['imagen_item'] = $stmt_arma->fetchColumn();
    } elseif ($ev['tipo_accion'] === 'leer_archivo') {
        $ev['imagen_item'] = '../img/fondo_nota.png';
    }
}
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
        .vats-life-container {
            position: fixed;
            bottom: 20px;
            left: 20px;
            width: 250px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #333;
            padding: 10px;
            z-index: 1000;
        }

        .vats-label {
            color: #00ff66;
            font-size: 10px;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .vats-hp-fill {
            height: 12px;
            transition: width 0.5s;
        }

        .hp-fine {
            background: #00ff66;
            box-shadow: 0 0 10px #00ff66;
        }

        .hp-caution {
            background: #ffcc00;
            box-shadow: 0 0 10px #ffcc00;
        }

        .hp-danger {
            background: #ff4444;
            box-shadow: 0 0 10px #ff4444;
        }

        /* Estilos del Enemigo en Pantalla */
        .enemy-encounter {
            position: absolute;
            left: 30%;
            top: 15%;
            width: 40%;
            height: 70%;
            z-index: 999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: crosshair;
            transition: transform 0.3s;
        }

        /* ═══════════════════════════════════════
           PUZZLE MEDALLONES
        ═══════════════════════════════════════ */
        #medallones-puzzle {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.92);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 3000;
            backdrop-filter: blur(8px);
        }

        .medallones-container {
            width: 680px;
            background: linear-gradient(160deg, #0d0d0d, #1a1010);
            border: 1px solid #5a1a1a;
            box-shadow: 0 0 40px rgba(180, 20, 20, 0.3), inset 0 0 60px rgba(0, 0, 0, 0.5);
            padding: 36px 40px 30px;
            position: relative;
        }

        .medallones-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #c00, transparent);
        }

        .medallones-header {
            text-align: center;
            margin-bottom: 28px;
            border-bottom: 1px solid #2a1010;
            padding-bottom: 18px;
        }

        .medallones-header h2 {
            margin: 0 0 8px;
            color: #cc3333;
            font-size: 1.3rem;
            letter-spacing: 4px;
            font-family: 'Courier New', monospace;
        }

        .medallones-header p {
            margin: 0;
            color: #888;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }

        .medallones-base {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 24px;
        }

        .medallon-slot {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            position: relative;
        }

        .medallon-slot-inner {
            width: 150px;
            height: 160px;
            border: 2px solid #333;
            background: #111;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .medallon-slot.available .medallon-slot-inner {
            border-color: #cc9900;
            box-shadow: 0 0 18px rgba(180, 130, 0, 0.4), inset 0 0 20px rgba(180, 130, 0, 0.05);
            animation: pulseGold 1.5s ease-in-out infinite;
        }

        .medallon-slot.placed .medallon-slot-inner {
            border-color: #00cc66;
            box-shadow: 0 0 22px rgba(0, 180, 80, 0.5), inset 0 0 20px rgba(0, 180, 80, 0.08);
        }

        @keyframes pulseGold {

            0%,
            100% {
                box-shadow: 0 0 14px rgba(180, 130, 0, 0.3);
            }

            50% {
                box-shadow: 0 0 28px rgba(220, 170, 0, 0.6);
            }
        }

        .medallon-placeholder {
            width: 80px;
            height: 80px;
            object-fit: contain;
            opacity: 0.18;
            filter: grayscale(100%);
        }

        .medallon-placed {
            position: absolute;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .medallon-placed img {
            width: 90px;
            height: 90px;
            filter: drop-shadow(0 0 8px rgba(0, 220, 100, 0.7));
            animation: floatMedallon 2s ease-in-out infinite;
        }

        @keyframes floatMedallon {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-5px);
            }
        }

        .slot-label {
            position: absolute;
            bottom: 8px;
            font-size: 0.65rem;
            letter-spacing: 2px;
            color: #666;
            font-family: 'Courier New', monospace;
        }

        #estatua-puzzle {
            position: fixed;
            inset: 0;
            background: radial-gradient(ellipse at center, rgba(20, 10, 0, 0.97) 0%, rgba(0, 0, 0, 0.99) 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 3000;
            backdrop-filter: blur(12px);
        }

        .estatua-container {
            width: 560px;
            background: linear-gradient(160deg, #1c1208, #0e0a04);
            border: 1px solid #5a3e10;
            box-shadow:
                0 0 60px rgba(180, 120, 0, 0.15),
                inset 0 0 80px rgba(0, 0, 0, 0.6),
                0 0 0 1px rgba(100, 60, 0, 0.3);
            padding: 44px 48px 36px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .estatua-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #c8860a, transparent);
        }

        .estatua-container::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, #5a3e10, transparent);
        }

        #estatua-titulo {
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            letter-spacing: 5px;
            color: #c8860a;
            text-shadow: 0 0 20px rgba(200, 134, 10, 0.5), 0 0 40px rgba(200, 134, 10, 0.2);
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .estatua-subtitle {
            font-size: 0.7rem;
            color: #5a4020;
            letter-spacing: 3px;
            margin-bottom: 36px;
            font-family: 'Courier New', monospace;
        }

        .symbols-container {
            display: flex;
            justify-content: center;
            gap: 28px;
            margin-bottom: 36px;
        }

        .symbol-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .symbol-btn {
            background: linear-gradient(180deg, #2a1e08, #1a1204);
            color: #c8860a;
            border: 1px solid #5a3e10;
            width: 44px;
            height: 28px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s;
            letter-spacing: 0;
        }

        .symbol-btn:hover {
            background: linear-gradient(180deg, #3a2a0a, #2a1e08);
            border-color: #c8860a;
            box-shadow: 0 0 10px rgba(200, 134, 10, 0.3);
            color: #ffd060;
        }

        .symbol-value {
            font-size: 0.85rem;
            color: #e8c060;
            font-family: 'Courier New', monospace;
            background: linear-gradient(145deg, #0e0a04, #1a1208);
            width: 110px;
            height: 110px;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 1px solid #5a3e10;
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.8), 0 0 12px rgba(180, 120, 0, 0.1);
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            text-shadow: 0 0 8px rgba(200, 180, 80, 0.4);
        }

        #estatua-status {
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            color: #c8860a;
            min-height: 20px;
            margin-bottom: 20px;
            letter-spacing: 1px;
        }

        .estatua-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .estatua-btn {
            flex: 1;
            padding: 12px 20px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            letter-spacing: 2px;
            cursor: pointer;
            border: 1px solid #5a3e10;
            transition: all 0.25s;
            text-transform: uppercase;
        }

        #btn-resolver-estatua {
            background: linear-gradient(160deg, #3a2800, #1e1400);
            color: #c8860a;
            border-color: #7a5418;
        }

        #btn-resolver-estatua:hover:not(:disabled) {
            background: linear-gradient(160deg, #5a3e00, #2e1e00);
            box-shadow: 0 0 20px rgba(200, 134, 10, 0.3);
            border-color: #c8860a;
            color: #ffd060;
        }

        #btn-resolver-estatua:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        #btn-cancelar-estatua {
            background: linear-gradient(160deg, #1a0808, #0e0404);
            color: #884422;
            border-color: #441410;
        }

        #btn-cancelar-estatua:hover {
            background: linear-gradient(160deg, #2a0c0c, #1a0808);
            border-color: #cc3333;
            color: #ff4444;
            box-shadow: 0 0 15px rgba(180, 0, 0, 0.2);
        }

        /* ═══════════════════════════════════════
           NOTIFICACIÓN CENTRADA
        ═══════════════════════════════════════ */
        #item-notification {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.8);
            background: rgba(0, 0, 0, 0.9);
            border: 2px solid #ccaa44;
            padding: 30px 60px;
            color: #fff;
            z-index: 5000;
            display: none;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 0 100px rgba(0, 0, 0, 0.9);
            opacity: 0;
            transition: all 0.3s;
        }

        #item-notification.show {
            display: flex;
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }

        /* Estilos de Combate (Integrados de main) */
        .enemy-encounter:hover {
            transform: scale(1.05);
        }

        .enemy-encounter img {
            height: 90%;
            filter: drop-shadow(0 0 15px rgba(255, 0, 0, 0.6));
            animation: creature-pulse 2s infinite;
        }

        .enemy-label {
            background: rgba(0, 0, 0, 0.85);
            color: #ff0000;
            padding: 8px 15px;
            border: 1px solid #ff0000;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            text-align: center;
            margin-top: -20px;
            box-shadow: 0 0 10px red;
        }

        @keyframes creature-pulse {

            0%,
            100% {
                filter: drop-shadow(0 0 10px red);
            }

            50% {
                filter: drop-shadow(0 0 25px red);
            }
        }

        .nav-blocked {
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.5s;
        }
    </style>
</head>

<body>

    <div class="vats-life-container">
        <div class="vats-label">CONDITION: <?php
        if ($vida_p >= 75)
            echo "FINE";
        elseif ($vida_p >= 30)
            echo "CAUTION";
        else
            echo "DANGER";
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
            <?php if ($sala['norte']): ?><a href="juego.php?sala=<?php echo $sala['norte']; ?>"
                    class="nav-btn north">▲</a><?php endif; ?>
            <?php if ($sala['sur']): ?><a href="juego.php?sala=<?php echo $sala['sur']; ?>"
                    class="nav-btn south">▼</a><?php endif; ?>
            <?php if ($sala['este']): ?><a href="juego.php?sala=<?php echo $sala['este']; ?>"
                    class="nav-btn east">►</a><?php endif; ?>
            <?php if ($sala['oeste']): ?><a href="juego.php?sala=<?php echo $sala['oeste']; ?>"
                    class="nav-btn west">◄</a><?php endif; ?>
        </div>

        <div class="message-box">
            <p><?php echo $hay_combate ? "¡Un engendro bloquea el camino!" : $sala['descripcion']; ?></p>
        </div>

        <?php if ($hay_combate): ?>
            <div class="enemy-encounter"
                onclick="window.location.href='combate.php?id_registro=<?php echo $enemigo_presente['id_registro']; ?>&vuelta=<?php echo $id_sala_actual; ?>'">
                <img src="<?php echo $enemigo_presente['imagen_url']; ?>" alt="Enemigo">
                <div class="enemy-label">
                    <strong>ADVERTENCIA:</strong> ENEMIGO CERCANO<br>
                    <small>PULSA PARA INICIAR COMBATE</small>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$hay_combate): ?>
            <?php foreach ($eventos as $ev): ?>
                <div class="hotspot <?php echo !empty($ev['imagen_item']) ? 'has-item' : ''; ?>"
                    style="left:<?php echo $ev['xmin']; ?>%; top:<?php echo $ev['ymin']; ?>%; width:<?php echo ($ev['xmax'] - $ev['xmin']); ?>%; height:<?php echo ($ev['ymax'] - $ev['ymin']); ?>%;"
                    onclick='ejecutarEvento(<?php echo json_encode($ev); ?>, event)'>
                    <?php if (!empty($ev['imagen_item'])): ?>
                        <img src="<?php echo $ev['imagen_item']; ?>" alt="Objeto" class="item-visual">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- MODALES Y MENÚS -->
        <div id="inventory-screen" style="display: none;">
            <div class="inventory-container">
                <h2>INVENTARIO</h2>
                <div class="inventory-grid" id="inventory-grid"></div>
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

        <div id="save-menu" style="display: none;">
            <div class="save-container">
                <div class="save-header">
                    <h2>MÁQUINA DE ESCRIBIR</h2>
                    <div class="ink-ribbon-count">CINTAS: <span id="ribbon-count">0</span></div>
                </div>
                <div class="save-slots">
                    <div class="save-slot" data-slot="1"><span class="slot-number">01</span>
                        <div class="slot-info"><span class="slot-status">VACÍO</span></div>
                    </div>
                    <div class="save-slot" data-slot="2"><span class="slot-number">02</span>
                        <div class="slot-info"><span class="slot-status">VACÍO</span></div>
                    </div>
                    <div class="save-slot" data-slot="3"><span class="slot-number">03</span>
                        <div class="slot-info"><span class="slot-status">VACÍO</span></div>
                    </div>
                </div>
                <button id="btn-cancelar-guardado" class="hud-btn">CANCELAR</button>
            </div>
        </div>

        <!-- PUZZLE MEDALLONES -->
        <div id="medallones-puzzle" style="display: none;">
            <div class="medallones-container">
                <div class="medallones-header">
                    <h2> ESTATUA DE LOS MEDALLONES </h2>
                    <p>Coloca los tres medallones</p>
                </div>
                <div class="medallones-base">
                    <div class="medallon-slot" id="slot-leon" data-medallon="7">
                        <div class="medallon-slot-inner"><img src="../img/medallon_de_leon.png"
                                class="medallon-placeholder">
                            <div class="medallon-placed" id="placed-leon"><img src="../img/medallon_de_leon.png"></div>
                            <span class="slot-label">LEÓN</span>
                        </div>
                    </div>
                    <div class="medallon-slot" id="slot-unicornio" data-medallon="8">
                        <div class="medallon-slot-inner"><img src="../img/medallon_de_unicornio.png"
                                class="medallon-placeholder">
                            <div class="medallon-placed" id="placed-unicornio"><img
                                    src="../img/medallon_de_unicornio.png"></div><span
                                class="slot-label">UNICORNIO</span>
                        </div>
                    </div>
                    <div class="medallon-slot" id="slot-doncella" data-medallon="9">
                        <div class="medallon-slot-inner"><img src="../img/medallon_de_doncella.png"
                                class="medallon-placeholder">
                            <div class="medallon-placed" id="placed-doncella"><img
                                    src="../img/medallon_de_doncella.png"></div><span class="slot-label">DONCELLA</span>
                        </div>
                    </div>
                </div>
                <div id="medallones-status">Verificando...</div>
                <div class="medallones-actions">
                    <button id="btn-colocar-medallones" disabled>ACTIVAR ESTATUA</button>
                    <button id="btn-cancelar-medallones" onclick="cerrarMenuMedallones()">CANCELAR</button>
                </div>
            </div>
        </div>

        <div id="estatua-puzzle" style="display: none;">
            <div class="estatua-container">
                <h2 id="estatua-titulo">Estatua</h2>
                <p class="estatua-subtitle">— ALINEA LOS SÍMBOLOS CORRECTOS —</p>
                <div class="symbols-container">
                    <div class="symbol-wrapper">
                        <button class="symbol-btn" onclick="cambiarSimbolo(0, 1)">▲</button>
                        <div class="symbol-value" id="symbol-0">---</div>
                        <button class="symbol-btn" onclick="cambiarSimbolo(0, -1)">▼</button>
                    </div>
                    <div class="symbol-wrapper">
                        <button class="symbol-btn" onclick="cambiarSimbolo(1, 1)">▲</button>
                        <div class="symbol-value" id="symbol-1">---</div>
                        <button class="symbol-btn" onclick="cambiarSimbolo(1, -1)">▼</button>
                    </div>
                    <div class="symbol-wrapper">
                        <button class="symbol-btn" onclick="cambiarSimbolo(2, 1)">▲</button>
                        <div class="symbol-value" id="symbol-2">---</div>
                        <button class="symbol-btn" onclick="cambiarSimbolo(2, -1)">▼</button>
                    </div>
                </div>
                <div id="estatua-status"></div>
                <div class="estatua-actions">
                    <button class="estatua-btn" id="btn-resolver-estatua" onclick="intentarResolverEstatua()">⚙
                        CONFIRMAR</button>
                    <button class="estatua-btn" id="btn-cancelar-estatua" onclick="cerrarEstatuaPuzzle()">✕
                        SALIR</button>
                </div>
            </div>
        </div>

        <div id="item-notification">
            <div class="notif-label">OBJETO OBTENIDO</div>
            <div class="notif-name" id="notif-item-name"></div>
        </div>

        <div id="pause-menu" style="display: none;">
            <h2>PAUSA</h2>
            <button id="btn-continuar">CONTINUAR</button>
            <button id="btn-cargar">CARGAR PARTIDA</button>
            <button id="btn-salir">SALIR AL MENÚ</button>
        </div>

    </div> <!-- FIN game-container -->

    <script src="../js/movimientos.js"></script>
    <script src="../js/interacciones.js"></script>
    <script src="../js/inventario.js"></script>
    <script>
        const catalogoArchivos = <?php echo json_encode($archivos); ?>;
        const tension = "<?php echo $hay_combate ? 'alta' : 'baja'; ?>";
        const esAdmin = <?= $usuarioRol === 'admin' ? 'true' : 'false' ?>;
        const zombiesVisibles = <?= $zombiesVisibles ? 'true' : 'false' ?>;
    </script>
    <?php if ($usuarioRol === 'admin'): ?>
        <div id="admin-game-bar" style="
        position:fixed;top:0;left:0;right:0;z-index:9000;
        background:rgba(10,0,0,0.85);border-bottom:1px solid #c0392b;
        display:flex;align-items:center;justify-content:space-between;
        padding:0.3rem 1rem;font-family:'Courier New',monospace;font-size:0.7rem;
        backdrop-filter:blur(4px);
    ">
            <span style="color:#c0392b;letter-spacing:.15em;">MODO ADMIN —
                <?= htmlspecialchars($adminNombre = $_SESSION['usuario_nombre'] ?? 'Admin') ?></span>
            <span style="color:<?= $zombiesVisibles ? '#e74c3c' : '#00ff88' ?>;">ZOMBIES:
                <?= $zombiesVisibles ? 'ACTIVOS ⚠' : 'DESACTIVADOS ✔' ?></span>
            <a href="../pages/admin.php" style="
            color:#aaa;text-decoration:none;border:1px solid #333;
            padding:.2rem .6rem;letter-spacing:.1em;
        " id="link-admin-panel"> PANEL ADMIN</a>
        </div>
    <?php endif; ?>
</body>

</html>