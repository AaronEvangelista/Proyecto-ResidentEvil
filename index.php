<?php
session_start();
$logueado = $_SESSION['logueado'] ?? false;
$nombreUsuario = $_SESSION['usuario_nombre'] ?? '';
$usuarioRol = $_SESSION['usuario_rol'] ?? 'jugador';

// Cargar partidas guardadas si está logueado
$partidas_guardadas = [];
if ($logueado) {
    require_once __DIR__ . '/includes/conexion.php';
    $uid = (int)($_SESSION['usuario_id'] ?? 0);
    if ($uid) {
        $stmt = $pdo->prepare("
            SELECT p.id_partida, p.slot_numero, p.sala_actual, p.fecha_guardado,
                   s.nombre_visual AS nombre_sala
            FROM partida p
            LEFT JOIN catalogo_salas s ON s.id_sala = p.sala_actual
            WHERE p.id_usuario = ? AND p.slot_numero IS NOT NULL
            ORDER BY p.fecha_guardado DESC
        ");
        $stmt->execute([$uid]);
        $partidas_guardadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Resident Evil">
    <title>Resident Evil</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/index.css">
</head>

<body>

    <div class="efecto-crt"></div>

    <div class="fase-contenedor">

        <header class="logo-area">
            <img src="./img/logo.png" alt="Resident Evil Trivia" class="main-logo">
            <p class="subtitulo">THE SURVIVAL HORROR</p>
        </header>

        <nav class="menu-area" id="menu-principal">
            <ul>
                <?php if ($logueado && !empty($partidas_guardadas)): ?>
                <li>
                    <a href="#" id="btn-continuar" class="item-seleccionado">
                        <span class="icono"></span>CONTINUAR PARTIDA<span class="llave"></span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="#" id="btn-nueva-partida" <?= ($logueado && !empty($partidas_guardadas)) ? '' : 'class="item-seleccionado"' ?>>
                        <span class="icono"></span>NUEVA PARTIDA
                    </a>
                </li>
                <li><a href="./pages/enciclopedia.php"><span class="icono"></span> ENCICLOPEDIA DE RACCOON CITY</a></li>
                <li><a href="./pages/logros.php"><span class="icono"></span> Logros</a></li>
            </ul>
        </nav>

    </div>

    <!-- ══ MODAL: SELECCIÓN DE PARTIDA ══ -->
    <div id="modal-seleccion-partida" style="display:none;">
        <div class="msp-container">
            <div class="msp-header">
                <h2>SELECCIONAR PARTIDA</h2>
                <p>Elige la partida que deseas continuar</p>
            </div>
            <div class="msp-slots" id="msp-slots">
                <?php foreach ($partidas_guardadas as $p): ?>
                <div class="msp-slot" onclick="window.location.href='pages/juego.php?partida=<?= (int)$p['id_partida'] ?>'">
                    <div class="msp-slot-num">SLOT <?= (int)$p['slot_numero'] ?></div>
                    <div class="msp-slot-info">
                        <span class="msp-sala"><?= htmlspecialchars($p['nombre_sala'] ?? $p['sala_actual']) ?></span>
                        <span class="msp-fecha"><?= htmlspecialchars(substr($p['fecha_guardado'], 0, 16)) ?></span>
                    </div>
                    <div class="msp-arrow">→</div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($partidas_guardadas)): ?>
                <div class="msp-vacio">No hay partidas guardadas.<br><small>Empieza una nueva partida y guarda en una máquina de escribir.</small></div>
                <?php endif; ?>
            </div>
            <button class="msp-cancelar" onclick="document.getElementById('modal-seleccion-partida').style.display='none'">✕ CANCELAR</button>
        </div>
    </div>

    <style>
    #modal-seleccion-partida {
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.92);
        display: flex; justify-content: center; align-items: center;
        z-index: 9000; backdrop-filter: blur(8px);
    }
    .msp-container {
        background: linear-gradient(160deg, #080500, #0f0a02);
        border: 1px solid #3a2a00;
        box-shadow: 0 0 80px rgba(160,100,0,0.15);
        padding: 32px 44px 28px;
        font-family: 'Courier New', monospace;
        min-width: 420px; max-width: 540px; width: 90%;
        position: relative; text-align: center;
    }
    .msp-container::before {
        content:''; position:absolute; top:0; left:0; right:0; height:2px;
        background: linear-gradient(90deg, transparent, #c8a030, transparent);
    }
    .msp-header h2 { color:#c8a030; font-size:1rem; letter-spacing:4px; margin:0 0 6px; }
    .msp-header p  { color:#443322; font-size:0.72rem; letter-spacing:1px; margin:0 0 22px; }
    .msp-slots { display:flex; flex-direction:column; gap:10px; margin-bottom:20px; }
    .msp-slot {
        display:flex; align-items:center; gap:14px;
        padding:14px 18px; background:#0a0700;
        border:1px solid #2a1e00; cursor:pointer;
        transition:background .2s, border-color .2s;
    }
    .msp-slot:hover { background:#14100a; border-color:#8a6000; }
    .msp-slot-num { color:#6a4a00; font-size:0.75rem; letter-spacing:2px; min-width:52px; }
    .msp-slot-info { flex:1; text-align:left; }
    .msp-sala { display:block; color:#c8a030; font-size:0.88rem; letter-spacing:1px; }
    .msp-fecha { display:block; color:#3a2a10; font-size:0.68rem; margin-top:3px; }
    .msp-arrow { color:#6a4a00; font-size:1.1rem; }
    .msp-slot:hover .msp-arrow { color:#c8a030; }
    .msp-vacio { color:#443322; font-size:0.8rem; padding:20px 0; }
    .msp-cancelar {
        padding:10px 28px; background:#080500; border:1px solid #2a1e00;
        color:#443322; cursor:pointer; letter-spacing:2px; font-size:0.78rem;
        font-family:'Courier New',monospace; transition:.2s;
    }
    .msp-cancelar:hover { background:#0f0a02; color:#8a6030; border-color:#4a3000; }
    </style>

    <div class="hud-area" id="hud-usuario">
        <?php if ($logueado): ?>
            <div class="hud-izquierda">
                <div class="hud-avatar-icon">☣</div>
                <div class="hud-info">
                    <span class="hud-label">SUPERVIVIENTE</span>
                    <span class="nombre-jugador"><?= $nombreUsuario ?></span>
                </div>
            </div>
            <div class="hud-derecha">
                <?php if ($usuarioRol === 'admin'): ?>
                    <a href="pages/admin.php" class="hud-btn hud-btn-admin" id="btn-hud-admin">
                        ⚙ PANEL ADMIN
                    </a>
                <?php endif; ?>
                <a href="pages/perfil.php" class="hud-btn hud-btn-perfil" id="btn-hud-perfil">
                    MI PERFIL
                </a>
                <a href="sessions/logout.php" class="hud-btn hud-btn-logout" id="btn-hud-logout">
                    CERRAR SESIÓN
                </a>
            </div>
        <?php else: ?>
            <div class="hud-izquierda">
                <div class="hud-avatar-icon hud-avatar-anon">?</div>
                <div class="hud-info">
                    <span class="hud-label">IDENTIFICACIÓN</span>
                    <span class="nombre-jugador hud-anonimo">DESCONOCIDO</span>
                </div>
            </div>
            <div class="hud-derecha">
                <a href="sessions/login.php" class="hud-btn hud-btn-login" id="btn-hud-login">
                    ▶ INICIAR SESIÓN
                </a>
                <a href="sessions/registro.php" class="hud-btn hud-btn-registro" id="btn-hud-registro">
                    ✚ REGISTRARSE
                </a>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal-overlay" id="modal-login" role="dialog" aria-modal="true" aria-labelledby="modal-titulo">
        <div class="modal-card">
            <div class="modal-icono">☣</div>
            <h2 id="modal-titulo">ACCESO RESTRINGIDO</h2>
            <p class="modal-texto">
                Para entrar en la zona de peligro necesitas identificarte, superviviente.<br>
                <span class="modal-subtexto">Tu progreso quedará guardado en el sistema.</span>
            </p>
            <div class="modal-acciones">
                <a href="sessions/login.php" class="modal-btn modal-btn-principal" id="modal-btn-login">
                    INICIAR SESIÓN
                </a>
                <a href="sessions/registro.php" class="modal-btn modal-btn-secundario" id="modal-btn-registro">
                    CREAR CUENTA
                </a>
            </div>
            <button class="modal-cerrar" id="modal-cerrar" aria-label="Cerrar">✕ CANCELAR</button>
        </div>
    </div>

    <script>
        const logueado = <?= $logueado ? 'true' : 'false' ?>;
        const partidasGuardadas = <?= count($partidas_guardadas) ?>;

        const btnContinuar    = document.getElementById('btn-continuar');
        const btnNuevaPartida = document.getElementById('btn-nueva-partida');
        const modalLogin      = document.getElementById('modal-login');
        const modalSeleccion  = document.getElementById('modal-seleccion-partida');
        const btnCerrar       = document.getElementById('modal-cerrar');

        // CONTINUAR PARTIDA solo existe en el DOM si hay partidas guardadas
        if (btnContinuar) {
            btnContinuar.addEventListener('click', (e) => {
                e.preventDefault();
                if (partidasGuardadas === 1) {
                    // Una sola partida → cargar directo sin selector
                    const primerSlot = document.querySelector('.msp-slot');
                    if (primerSlot) primerSlot.click();
                    else window.location.href = './pages/juego.php';
                } else {
                    // Varias partidas → mostrar modal selector
                    modalSeleccion.style.display = 'flex';
                }
            });
        }

        btnNuevaPartida.addEventListener('click', (e) => {
            e.preventDefault();
            if (logueado) {
                window.location.href = './pages/juego.php?new=1';
            } else {
                modalLogin.classList.add('modal-visible');
            }
        });

        btnCerrar.addEventListener('click', () => modalLogin.classList.remove('modal-visible'));

        modalLogin.addEventListener('click', function(e) {
            if (e.target === modalLogin) modalLogin.classList.remove('modal-visible');
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                modalLogin.classList.remove('modal-visible');
                if (modalSeleccion) modalSeleccion.style.display = 'none';
            }
        });

      // En tu index.php
const musicaMenu = new Audio('sounds/ambiente_index.mp3');
musicaMenu.loop = true;

document.addEventListener('click', () => {
    musicaMenu.play();
}, { once: true });

 </script>

</body>

</html>