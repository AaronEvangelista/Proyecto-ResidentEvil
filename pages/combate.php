<?php
session_start();
require_once '../includes/conexion.php';

$id_registro = $_GET['id_registro'] ?? null;
$sala_vuelta = $_GET['vuelta'] ?? 'lobby_principal';
$id_partida = $_SESSION['id_partida'] ?? null;

if (!$id_registro || !$id_partida)
    die("Error: Sesión o enemigo no encontrado.");

$query = $pdo->prepare("
    SELECT ee.*, ce.nombre, ce.vida_maxima, ce.dano_base, ce.imagen_url,
           ce.precision_cabeza, ce.precision_torso, ce.precision_piernas, 
           ce.multiplicador_cabeza, ce.esquive_base
    FROM estado_enemigos ee
    JOIN catalogo_enemigos ce ON ee.id_enemigo = ce.id_enemigo
    WHERE ee.id_registro = ? AND ee.id_partida = ?
");
$query->execute([$id_registro, $id_partida]);
$enemigo = $query->fetch(PDO::FETCH_ASSOC);
$query_p = $pdo->prepare("SELECT vida_actual FROM estado_personaje WHERE id_partida = ?");
$query_p->execute([$id_partida]);
$vida_jugador = $query_p->fetchColumn();
if ($vida_jugador === false || $vida_jugador === null)
    $vida_jugador = 100;

if (!$enemigo) {
    header("Location: juego.php?sala=" . $sala_vuelta);
    exit;
}

$armas_disponibles = [];

$q_armas_db = $pdo->prepare("
    SELECT i.id_registro, i.cantidad, ca.nombre, ca.dano_porcentaje, ca.imagen_url, 'db' as fuente, NULL as sesion_idx
    FROM inventario i
    JOIN catalogo_armas ca ON i.id_objeto = ca.id_arma
    WHERE i.id_partida = ? AND i.tipo_objeto = 'arma' AND i.cantidad > 0
");
$q_armas_db->execute([$id_partida]);
$armas_disponibles = $q_armas_db->fetchAll(PDO::FETCH_ASSOC);

$inv_sesion = $_SESSION['inventario_sesion'] ?? [];
foreach ($inv_sesion as $idx => $item) {
    if ($item['tipo_objeto'] === 'arma' && (int) ($item['cantidad'] ?? 0) > 0) {
        $q_cat = $pdo->prepare("SELECT nombre, dano_porcentaje, imagen_url FROM catalogo_armas WHERE id_arma = ?");
        $q_cat->execute([$item['id_objeto']]);
        $cat = $q_cat->fetch(PDO::FETCH_ASSOC);
        if ($cat) {
            $armas_disponibles[] = [
                'id_registro' => null,
                'cantidad' => $item['cantidad'],
                'nombre' => $cat['nombre'],
                'dano_porcentaje' => $cat['dano_porcentaje'],
                'imagen_url' => $cat['imagen_url'],
                'fuente' => 'sesion',
                'sesion_idx' => $idx
            ];
        }
    }
}

$armas_disponibles[] = [
    'id_registro' => null,
    'cantidad' => -1,
    'nombre' => 'Cuchillo',
    'dano_porcentaje' => 10,
    'imagen_url' => '../img/Cuchillo_Defensivo.webp',
    'fuente' => 'infinito',
    'sesion_idx' => null
];

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <style>
        :root {
            --vats-green: #00ff66;
            --vats-bg: rgba(0, 20, 0, 0.9);
        }

        body {
            background: #000;
            color: var(--vats-green);
            font-family: 'Courier New', monospace;
            margin: 0;
            overflow: hidden;
            text-transform: uppercase;
        }

        #vats-container {
            width: 100vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: radial-gradient(circle, #001100 0%, #000 100%);
        }

        .main-view {
            flex: 1;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .enemy-sprite {
            height: 70vh;
            filter: sepia(1) brightness(0.8) hue-rotate(80deg) drop-shadow(0 0 10px var(--vats-green));
        }

        .vats-node {
            position: absolute;
            background: var(--vats-bg);
            border: 1px solid var(--vats-green);
            padding: 10px;
            cursor: pointer;
            transition: 0.2s;
            z-index: 100;
            min-width: 80px;
            text-align: center;
        }

        .vats-node:hover {
            background: var(--vats-green);
            color: #000;
            font-weight: bold;
        }

        .node-head {
            top: 15%;
            left: 55%;
        }

        .node-torso {
            top: 40%;
            left: 60%;
        }

        .node-legs {
            top: 70%;
            left: 55%;
        }

        .vats-footer {
            height: 160px;
            border-top: 2px solid var(--vats-green);
            display: grid;
            grid-template-columns: 1.5fr 1.5fr 1fr;
            background: rgba(0, 10, 0, 0.95);
            padding: 15px;
            gap: 20px;
        }

        .stat-box {
            border: 1px solid var(--vats-green);
            padding: 10px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .hp-bar-bg {
            background: #003300;
            height: 15px;
            border: 1px solid var(--vats-green);
            margin-top: 5px;
        }

        .hp-bar-fill {
            height: 100%;
            background: var(--vats-green);
            transition: width 0.4s;
        }

        .hp-player-fill {
            background: #ffb000;
        }

        .btn-huir {
            background: transparent;
            color: #ff4444;
            border: 1px solid #ff4444;
            padding: 10px;
            cursor: pointer;
            font-family: inherit;
            font-size: 1.1rem;
            width: 100%;
            height: 100%;
        }

        .btn-huir:hover {
            background: #ff4444;
            color: #000;
        }

        .log-box {
            font-size: 0.75rem;
            border: 1px solid #333;
            padding: 5px;
            overflow-y: hidden;
            background: rgba(0, 0, 0, 0.5);
            min-height: 40px;
        }

        .flash-hit {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: red;
            opacity: 0;
            pointer-events: none;
            z-index: 1000;
            transition: opacity 0.1s;
        }

        .ammo-display {
            font-size: 0.7rem;
            margin-top: 3px;
            color: var(--vats-green);
            letter-spacing: 1px;
        }

        .ammo-empty {
            color: #ff4444;
        }

        .weapon-selector {
            display: flex;
            gap: 10px;
            margin-top: 5px;
        }

        .weapon-btn {
            border: 1px solid var(--vats-green);
            background: rgba(0, 40, 0, 0.8);
            color: var(--vats-green);
            padding: 5px;
            cursor: pointer;
            font-size: 0.6rem;
            transition: 0.3s;
            flex: 1;
            text-align: center;
        }

        .weapon-btn.active {
            background: var(--vats-green);
            color: #000;
            font-weight: bold;
        }

        .weapon-btn:hover {
            background: rgba(0, 255, 102, 0.2);
        }
    </style>
</head>

<body>

    <div id="flash" class="flash-hit"></div>

    <div id="vats-container">
        <div
            style="padding: 10px; border-bottom: 1px solid var(--vats-green); font-size: 0.8rem; display: flex; justify-content: space-between;">
            <span>VAULT-TEC PROTOCOL v4.0.2</span>
            <span>OBJETIVO: <?php echo $enemigo['nombre']; ?></span>
        </div>

        <div class="main-view">
            <img src="<?php echo $enemigo['imagen_url']; ?>" class="enemy-sprite">

            <div class="vats-node node-head"
                onclick="procesarAccion('cabeza', <?php echo $enemigo['precision_cabeza']; ?>)">
                <span
                    style="font-size: 1.2rem;"><?php echo $enemigo['precision_cabeza']; ?>%</span><br><small>CABEZA</small>
            </div>
            <div class="vats-node node-torso"
                onclick="procesarAccion('torso', <?php echo $enemigo['precision_torso']; ?>)">
                <span
                    style="font-size: 1.2rem;"><?php echo $enemigo['precision_torso']; ?>%</span><br><small>TORSO</small>
            </div>
            <div class="vats-node node-legs"
                onclick="procesarAccion('piernas', <?php echo $enemigo['precision_piernas']; ?>)">
                <span
                    style="font-size: 1.2rem;"><?php echo $enemigo['precision_piernas']; ?>%</span><br><small>PIERNAS</small>
            </div>
        </div>

        <div class="vats-footer">
            <div class="stat-box" style="border-color: #ffb000; color: #ffb000;">
                <div>JUGADOR (ESTADO ACTUAL)</div>
                <div style="font-size: 1.2rem; font-weight: bold;">HP: <span
                        id="hp-p-text"><?php echo $vida_jugador; ?></span> / 100</div>
                <div class="hp-bar-bg" style="border-color: #ffb000;">
                    <div id="hp-p-bar" class="hp-bar-fill hp-player-fill" style="width: <?php echo $vida_jugador; ?>%">
                    </div>
                </div>
                <div class="ammo-display">
                    MUNICIÓN: <span id="ammo-count">--</span>
                </div>
                <div class="weapon-selector" id="weapon-selector">
                </div>
                <div class="log-box" id="combat-log" style="margin-top: 5px; color: #ffb000;">> ESPERANDO ÓRDENES...
                </div>
            </div>

            <div class="stat-box">
                <div>OBJETIVO (ESTADO)</div>
                <div style="font-size: 1.2rem; font-weight: bold;">HP: <span
                        id="hp-e-text"><?php echo $enemigo['vida_restante']; ?></span></div>
                <div class="hp-bar-bg">
                    <div id="hp-e-bar" class="hp-bar-fill"
                        style="width: <?php echo ($enemigo['vida_restante'] / $enemigo['vida_maxima']) * 100; ?>%">
                    </div>
                </div>
            </div>

            <div class="stat-box" style="padding: 0; border: none;">
                <button class="btn-huir" onclick="intentarEscapar()">[ HUIR ]<br><small
                        style="font-size: 0.6rem;">PROBABILIDAD:
                        <?php echo $enemigo['esquive_base']; ?>%</small></button>
            </div>
        </div>
    </div>

    <script>
        // --- EFECTOS DE SONIDO ---
        const sndPistola = new Audio('../sounds/disparo_pistola.mp3');
        const sndEscopeta = new Audio('../sounds/disparo_escopeta.mp3');
        const sndAtaqueEnemigo = new Audio('../sounds/ataque_mordisco.mp3');

        // Función genérica para reproducir y cortar el audio
        function reproducirSonidoCorto(audio, duracionMs) {
            audio.currentTime = 0;
            audio.play();
            setTimeout(() => {
                audio.pause();
                audio.currentTime = 0;
            }, duracionMs);
        }

        let eHP = parseInt(<?php echo (int) $enemigo['vida_restante']; ?>);
        const eHPMax = parseInt(<?php echo (int) $enemigo['vida_maxima']; ?>);
        let pHP = parseInt(<?php echo (int) $vida_jugador; ?>);

        const eDmgBase = parseInt(<?php echo (int) ($enemigo['dano_base'] ?? 25); ?>);

        const multCabezaJugador = parseFloat(<?php echo (float) $enemigo['multiplicador_cabeza']; ?>);

        const armas = <?php echo json_encode($armas_disponibles); ?>;
        let armaActualIdx = 0;

        let turnoBloqueado = false;
        let enemigoAturdido = false;

        function initWeaponSelector() {
            const container = document.getElementById('weapon-selector');
            container.innerHTML = '';
            armas.forEach((arma, idx) => {
                const btn = document.createElement('div');
                btn.className = 'weapon-btn' + (idx === armaActualIdx ? ' active' : '');
                btn.innerText = arma.nombre;
                btn.onclick = () => seleccionarArma(idx);
                container.appendChild(btn);
            });
            actualizarMunicionUI();
        }

        function seleccionarArma(idx) {
            if (turnoBloqueado) return;
            armaActualIdx = idx;
            document.querySelectorAll('.weapon-btn').forEach((btn, i) => {
                btn.classList.toggle('active', i === idx);
            });
            actualizarMunicionUI();
            escribirLog("ARMA SELECCIONADA: " + armas[idx].nombre);
        }

        function escribirLog(msg) {
            document.getElementById('combat-log').innerHTML = "> " + msg;
        }

        function actualizarMunicionUI() {
            const arma = armas[armaActualIdx];
            const el = document.getElementById('ammo-count');
            if (arma.cantidad === -1) {
                el.innerText = '∞';
                el.classList.remove('ammo-empty');
            } else {
                el.innerText = arma.cantidad;
                if (arma.cantidad <= 0) el.classList.add('ammo-empty');
                else el.classList.remove('ammo-empty');
            }
        }

        function consumirMunicion(callback) {
            const arma = armas[armaActualIdx];
            if (arma.cantidad === -1 || arma.cantidad <= 0) { callback(); return; }

            arma.cantidad--;
            actualizarMunicionUI();

            const body = {};
            if (arma.fuente === 'db') {
                body.fuente = 'db';
                body.id_registro = arma.id_registro;
            } else {
                body.fuente = 'sesion';
                body.sesion_idx = arma.sesion_idx;
            }

            fetch('../includes/usar_municion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            })
                .then(r => r.json())
                .then(() => callback())
                .catch(() => callback());
        }

        function procesarAccion(zona, probabilidad) {
            if (turnoBloqueado) return;

            const arma = armas[armaActualIdx];
            const usandoMunicion = arma.cantidad > 0 || arma.cantidad === -1;
            const danoBase = usandoMunicion ? arma.dano_porcentaje : 5;

            if (!usandoMunicion && arma.cantidad !== -1) {
                escribirLog("SIN MUNICIÓN EN " + arma.nombre + ". ¡Derrotalo con el cuchillo!");
                return;
            }

            turnoBloqueado = true;
            escribirLog("ATACANDO A " + zona + "...");

            if (arma.nombre.toLowerCase().includes('escopeta')) {
                reproducirSonidoCorto(sndEscopeta, 1500);
            } else if (arma.nombre.toLowerCase().includes('pistola')) {
                reproducirSonidoCorto(sndPistola, 1500);
            }

            consumirMunicion(() => {
                setTimeout(() => {
                    if (Math.random() * 100 <= probabilidad) {
                        let danoFinal = danoBase;
                        if (zona === 'cabeza') {
                            danoFinal = Math.round(danoBase * multCabezaJugador);
                            escribirLog("¡TIRO EN LA CABEZA! DAÑO MASIVO.");
                        } else if (zona === 'piernas') {
                            danoFinal = Math.round(danoBase * 0.7);
                            if (Math.random() > 0.5) {
                                enemigoAturdido = true;
                                escribirLog("¡IMPACTO EN PIERNAS! ENEMIGO ATURDIDO.");
                            } else {
                                escribirLog("IMPACTO EN PIERNAS.");
                            }
                        } else {
                            escribirLog("¡IMPACTO! OBJETIVO PIERDE " + danoFinal + " HP.");
                        }

                        eHP = Math.max(0, eHP - danoFinal);
                        actualizarInterfaz();

                        if (eHP <= 0) {
                            escribirLog("AMENAZA ELIMINADA.");
                            guardarVidaJugador(pHP, () => {
                                window.location.href = "juego.php?sala=<?php echo $sala_vuelta; ?>&muerto=1&id_reg=<?php echo $id_registro; ?>";
                            });
                            return;
                        }
                    } else {
                        escribirLog("DISPARO FALLIDO.");
                    }
                    setTimeout(turnoEnemigo, 1000);
                }, 600);
            });
        }

        function turnoEnemigo() {
            if (enemigoAturdido) {
                escribirLog("EL ENEMIGO ESTÁ ATURDIDO Y PIERDE EL TURNO.");
                enemigoAturdido = false;
                turnoBloqueado = false;
                return;
            }

            escribirLog("EL ENEMIGO ATACA...");

            setTimeout(() => {
                // Sonido de ataque enemigo (Configurado a 1500ms = 1.5 segundos)
                reproducirSonidoCorto(sndAtaqueEnemigo, 1500);

                const flash = document.getElementById('flash');
                flash.style.opacity = "0.4";
                setTimeout(() => flash.style.opacity = "0", 150);

                pHP = Math.max(0, pHP - eDmgBase);
                actualizarInterfaz();

                if (pHP <= 0) {
                    escribirLog("ERROR FATAL: CONSTANTES VITALES NULAS.");
                    guardarVidaJugador(0, () => {
                        window.location.href = "../sessions/gameover.php";
                    });
                } else {
                    escribirLog("RECIBES " + eDmgBase + " DE DAÑO. ESPERANDO ÓRDENES...");
                    guardarVidaJugador(pHP, null);
                    turnoBloqueado = false;
                }
            }, 800);
        }

        function guardarVidaJugador(nuevaVida, callback) {
            fetch('../includes/guardar_vida.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ vida: nuevaVida })
            })
                .then(r => r.json())
                .then(data => { if (callback) callback(); })
                .catch(() => { if (callback) callback(); });
        }

        function intentarEscapar() {
            if (turnoBloqueado) return;
            escribirLog("SOLICITANDO RETIRADA...");

            setTimeout(() => {
                if (Math.random() * 100 <= <?php echo (int) $enemigo['esquive_base']; ?>) {
                    guardarVidaJugador(pHP, () => {
                        window.location.href = "juego.php?sala=<?php echo $sala_vuelta; ?>&huir=1&id_reg=<?php echo $id_registro; ?>";
                    });
                } else {
                    escribirLog("RETIRADA DENEGADA POR EL ENEMIGO.");
                    turnoBloqueado = true;
                    setTimeout(turnoEnemigo, 1000);
                }
            }, 500);
        }

        function actualizarInterfaz() {
            document.getElementById('hp-e-text').innerText = eHP;
            document.getElementById('hp-p-text').innerText = pHP;
            document.getElementById('hp-e-bar').style.width = (eHP / eHPMax * 100) + "%";
            document.getElementById('hp-p-bar').style.width = pHP + "%";
        }

        initWeaponSelector();
    </script>

</body>

</html>