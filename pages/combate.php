<?php
session_start();
require_once '../includes/conexion.php';


$id_registro = $_GET['id_registro'] ?? null;
$sala_vuelta = $_GET['vuelta'] ?? 'lobby_principal';
$id_partida = $_SESSION['id_partida'] ?? null;


if (!$id_registro || !$id_partida)
    die("Error: Sesión o enemigo no encontrado.");


//1. Obtener datos del enemigo
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


//2. Obtener vida del jugador
$query_p = $pdo->prepare("SELECT vida_actual FROM estado_personaje WHERE id_partida = ?");
$query_p->execute([$id_partida]);
$vida_jugador = $query_p->fetchColumn();
if ($vida_jugador === false || $vida_jugador === null)
    $vida_jugador = 100;


if (!$enemigo) {
    header("Location: juego.php?sala=" . $sala_vuelta);
    exit;
}


//3. Obtener TODAS las armas del jugador
$armas_disponibles = [];


//Buscar armas en BD
$q_armas_db = $pdo->prepare("
    SELECT i.id_registro, i.cantidad, ca.nombre, ca.dano_porcentaje, ca.imagen_url, 'db' as fuente, NULL as sesion_idx
    FROM inventario i
    JOIN catalogo_armas ca ON i.id_objeto = ca.id_arma
    WHERE i.id_partida = ? AND i.tipo_objeto = 'arma' AND i.cantidad > 0
");
$q_armas_db->execute([$id_partida]);
$armas_disponibles = $q_armas_db->fetchAll(PDO::FETCH_ASSOC);


//Buscar armas en sesión
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


//Cuchillo siempre disponible
$armas_disponibles[] = [
    'id_registro' => null,
    'cantidad' => -1,
    'nombre' => 'Cuchillo',
    'dano_porcentaje' => 10,
    'imagen_url' => '../img/Cuchillo_Defensivo.webp',
    'fuente' => 'infinito',
    'sesion_idx' => null
];


// 4. Obtener objetos de CURACIÓN y MUNICIÓN
$objetos_utilizables = [];
// Desde Base de Datos
$q_items_db = $pdo->prepare("
    SELECT i.id_registro, i.cantidad, ci.nombre, ci.descripcion, ci.imagen_url, 'db' as fuente, NULL as sesion_idx, ci.tipo
    FROM inventario i
    JOIN catalogo_items ci ON i.id_objeto = ci.id_item
    WHERE i.id_partida = ? AND i.tipo_objeto = 'item' AND (ci.tipo = 'curacion' OR ci.tipo = 'municion') AND i.cantidad > 0
");
$q_items_db->execute([$id_partida]);
$objetos_utilizables = $q_items_db->fetchAll(PDO::FETCH_ASSOC);


// Desde Sesión (si hay objetos aún no persistidos)
foreach ($inv_sesion as $idx => $item) {
    if ($item['tipo_objeto'] === 'item' && (int) ($item['cantidad'] ?? 0) > 0) {
        $q_cat = $pdo->prepare("SELECT nombre, tipo, descripcion, imagen_url FROM catalogo_items WHERE id_item = ?");
        $q_cat->execute([$item['id_objeto']]);
        $cat = $q_cat->fetch(PDO::FETCH_ASSOC);
        if ($cat && ($cat['tipo'] === 'curacion' || $cat['tipo'] === 'municion')) {
            $objetos_utilizables[] = [
                'id_registro' => null,
                'cantidad' => $item['cantidad'],
                'nombre' => $cat['nombre'],
                'descripcion' => $cat['descripcion'],
                'imagen_url' => $cat['imagen_url'],
                'fuente' => 'sesion',
                'sesion_idx' => $idx,
                'tipo' => $cat['tipo']
            ];
        }
    }
}


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
            height: 48%;
            transition: 0.2s;
        }

        .btn-huir:hover {
            background: #ff4444;
            color: #000;
            box-shadow: 0 0 15px #ff4444;
        }

        .btn-items {
            background: transparent;
            color: #ffb000;
            border: 1px solid #ffb000;
            padding: 10px;
            cursor: pointer;
            font-family: inherit;
            font-size: 1.1rem;
            width: 100%;
            height: 48%;
            transition: 0.2s;
            margin-bottom: 4%;
        }

        .btn-items:hover {
            background: #ffb000;
            color: #000;
            box-shadow: 0 0 15px #ffb000;
        }

        /* Modal de Objetos */
        #items-menu {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.9);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(8px);
            border: 2px solid var(--vats-green);
            margin: 20px;
        }

        .items-container {
            width: 500px;
            padding: 30px;
            border: 1px solid var(--vats-green);
            background: #000;
            position: relative;
        }

        .items-container::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(rgba(0, 255, 102, 0.03) 0px, rgba(0, 255, 102, 0.03) 1px, transparent 1px, transparent 2px);
            pointer-events: none;
        }

        .item-row {
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid #113311;
            padding: 15px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: 0.3s;
            position: relative;
            z-index: 1;
        }

        .item-row:hover {
            border-color: var(--vats-green);
            background: rgba(0, 255, 102, 0.15);
            transform: translateX(5px);
        }

        .item-img {
            width: 60px;
            height: 60px;
            object-fit: contain;
            filter: sepia(1) hue-rotate(80deg) brightness(1.2);
        }

        .item-info {
            flex: 1;
        }

        .item-name {
            font-weight: bold;
            color: var(--vats-green);
            font-size: 1.1rem;
            letter-spacing: 1px;
        }

        .item-desc {
            font-size: 0.75rem;
            color: #008833;
            margin-top: 4px;
            line-height: 1.2;
        }

        .item-qty {
            font-size: 1.4rem;
            color: #ffb000;
            font-weight: bold;
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


            <div class="stat-box"
                style="padding: 0; border: none; display: flex; flex-direction: column; justify-content: space-between;">
                <button class="btn-items" onclick="abrirMenuObjetos()">[ OBJETOS ]</button>
                <button class="btn-huir" onclick="intentarEscapar()">[ HUIR ]<br><small
                        style="font-size: 0.6rem;">PROBABILIDAD:
                        <?php echo $enemigo['esquive_base']; ?>%</small></button>
            </div>
        </div>
    </div>


    <!-- OVERLAY DE OBJETOS -->
    <div id="items-menu" onclick="event.target === this && cerrarMenuObjetos()">
        <div class="items-container">
            <h2
                style="border-bottom: 2px solid var(--vats-green); margin-bottom: 25px; letter-spacing: 4px; color: var(--vats-green);">
                SISTEMA DE SUMINISTROS</h2>
            <div id="items-list" style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
                <!-- Renderizado vía JS -->
            </div>
            <button class="weapon-btn" onclick="cerrarMenuObjetos()"
                style="width: 100%; padding: 15px; margin-top: 20px; font-size: 1.1rem; border-color: #ff4444; color: #ff4444;">SALIR
                DEL SISTEMA</button>
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


        //1. DATOS DE ESTADO
        let eHP = parseInt(<?php echo (int) $enemigo['vida_restante']; ?>);
        const eHPMax = parseInt(<?php echo (int) $enemigo['vida_maxima']; ?>);
        let pHP = parseInt(<?php echo (int) $vida_jugador; ?>);

        //2. DAÑO DEL ENEMIGO
        const eDmgBase = parseInt(<?php echo (int) ($enemigo['dano_base'] ?? 25); ?>);

        //3. MULTIPLICADOR PARA EL JUGADOR
        const multCabezaJugador = parseFloat(<?php echo (float) $enemigo['multiplicador_cabeza']; ?>);

        //4. SISTEMA DE ARMAS
        const armas = <?php echo json_encode($armas_disponibles); ?>;
        let armaActualIdx = 0;

        //5. SISTEMA DE OBJETOS
        let objetos = <?php echo json_encode($objetos_utilizables); ?>;

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


        // --- MANEJO DE INVENTARIO EN COMBATE ---
        function abrirMenuObjetos() {
            if (turnoBloqueado) return;
            const menu = document.getElementById('items-menu');
            const list = document.getElementById('items-list');
            list.innerHTML = '';

            if (objetos.length === 0) {
                list.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #444; border: 1px dashed #444;">
                    NO SE DETECTAN OBJETOS BIOMÉDICOS EN EL INVENTARIO.
                </div>
            `;
            } else {
                objetos.forEach((item, idx) => {
                    const row = document.createElement('div');
                    row.className = 'item-row';
                    row.onclick = () => usarObjeto(idx);
                    row.innerHTML = `
                    <img src="${item.imagen_url}" class="item-img">
                    <div class="item-info">
                        <div class="item-name">${item.nombre.toUpperCase()}</div>
                        <div class="item-desc">${item.descripcion.toUpperCase()}</div>
                    </div>
                    <div class="item-qty">x${item.cantidad}</div>
                `;
                    list.appendChild(row);
                });
            }
            menu.style.display = 'flex';
        }


        function cerrarMenuObjetos() {
            document.getElementById('items-menu').style.display = 'none';
        }


        function usarObjeto(idx) {
            const item = objetos[idx];
            if (item.tipo === 'curacion' && pHP >= 100) {
                escribirLog("ESTADO DE SALUD ÓPTIMO. NO SE REQUIERE CURACIÓN.");
                cerrarMenuObjetos();
                return;
            }


            cerrarMenuObjetos();
            turnoBloqueado = true;
            escribirLog("PROCESANDO " + item.nombre.toUpperCase() + "...");


            fetch('../includes/usar_item.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(item)
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (data.tipo === 'curacion') {
                            pHP = data.nueva_vida;
                            escribirLog("REGENERACIÓN COMPLETADA: +" + data.curacion + " HP.");
                        } else if (data.tipo === 'recarga') {
                            // Actualizar el arma correspondiente en nuestro array local
                            const armaAfectada = armas.find(a => a.nombre === data.arma);
                            if (armaAfectada) {
                                armaAfectada.cantidad = data.nueva_cantidad;
                                actualizarMunicionUI();
                                initWeaponSelector(); // Refrescar nombres/estados si es necesario
                            }
                            escribirLog("RECARGA COMPLETADA: " + data.arma.toUpperCase() + " (+" + data.recarga + " BALAS).");
                        }


                        // Descontar objeto de la lista local
                        item.cantidad--;
                        if (item.cantidad <= 0) {
                            objetos.splice(idx, 1);
                        }

                        actualizarInterfaz();
                        // El jugador puede seguir actuando
                        turnoBloqueado = false;
                    } else {
                        escribirLog("ERROR: " + (data.error || "FALLO EN EL SUMINISTRO."));
                        turnoBloqueado = false;
                    }
                })
                .catch(() => {
                    escribirLog("ERROR DE ENLACE CON EL INVENTARIO.");
                    turnoBloqueado = false;
                });
        }


        function procesarAccion(zona, probabilidad) {
            if (turnoBloqueado) return;

            const arma = armas[armaActualIdx];
            const usandoMunicion = arma.cantidad > 0 || arma.cantidad === -1;
            const danoBase = usandoMunicion ? arma.dano_porcentaje : 5;

            if (!usandoMunicion && arma.cantidad !== -1) {
                escribirLog("SIN MUNICIÓN EN " + arma.nombre + ". ¡Derrótalo con el cuchillo!");
                return;
            }

            // Sonido de disparo
            if (arma.nombre.toLowerCase().includes('escopeta')) {
                reproducirSonidoCorto(sndEscopeta, 1500);
            } else if (arma.nombre.toLowerCase().includes('pistola')) {
                reproducirSonidoCorto(sndPistola, 1500);
            } else if (arma.nombre.toLowerCase().includes('granada')) {
                // Sonido de granada si existiera
            }

            turnoBloqueado = true;


            // --- SISTEMA TÁCTICO: GRANADAS ---
            // Las granadas tienen 100% de precisión ignorando la zona de impacto
            const esGranada = arma.nombre.toLowerCase().includes('granada');
            const precisionFinal = esGranada ? 100 : probabilidad;


            escribirLog("INICIANDO SECUENCIA DE ATAQUE A " + zona + "...");

            consumirMunicion(() => {
                setTimeout(() => {
                    if (Math.random() * 100 <= precisionFinal) {
                        let danoFinal = danoBase;
                        if (zona === 'cabeza') {
                            danoFinal = Math.round(danoBase * multCabezaJugador);
                            escribirLog("¡TIRO EN LA CABEZA! DAÑO MASIVO.");
                        }
                        if (zona === 'piernas') {
                            danoFinal = Math.round(danoBase * 0.7);
                            if (Math.random() > 0.5) {
                                enemigoAturdido = true;
                                escribirLog("¡IMPACTO EN PIERNAS! ENEMIGO ATURDIDO.");
                            } else {
                                escribirLog("IMPACTO EN PIERNAS.");
                            }
                        }
                        eHP = Math.max(0, eHP - danoFinal);
                        actualizarInterfaz();
                        if (zona !== 'cabeza' && zona !== 'piernas') escribirLog("¡IMPACTO! OBJETIVO PIERDE " + danoFinal + " HP.");


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


        //Inicializar selector de armas al cargar
        initWeaponSelector();
    </script>


</body>

</html>