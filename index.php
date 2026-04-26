<?php
require_once __DIR__ . '/includes/seguridad.php';
session_start();
$logueado       = !empty($_SESSION['logueado']);
$nombreUsuario  = htmlspecialchars($_SESSION['usuario_nombre'] ?? '');
$emailUsuario   = htmlspecialchars($_SESSION['usuario_email']  ?? '');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Resident Evil: Trivia Survival – El juego de supervivencia basado en el universo Resident Evil.">
    <title>Resident Evil: Trivia Survival</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/index.css">
</head>

<body>

    <!-- ░░ EFECTO CRT ░░ -->
    <div class="efecto-crt"></div>

    <!-- ░░ CONTENEDOR PRINCIPAL ░░ -->
    <div class="fase-contenedor">

        <!-- LOGO -->
        <header class="logo-area">
            <img src="./img/logo.png" alt="Resident Evil Trivia" class="main-logo">
            <p class="subtitulo">THE SURVIVAL HORROR</p>
        </header>

        <!-- MENÚ PRINCIPAL -->
        <nav class="menu-area" id="menu-principal">
            <ul>
                <li>
                    <a href="#" id="btn-jugar" class="item-seleccionado">
                        <span class="icono"></span>JUGAR STORY<span class="llave"></span>
                    </a>
                </li>
                <li><a href="./pages/enciclopedia.php"><span class="icono"></span> ENCICLOPEDIA DE RACCOON CITY</a></li>
                <li><a href="#"><span class="icono"></span> ARCHIVOS DE DATOS</a></li>
                <li><a href="#"><span class="icono"></span> OPCIONES</a></li>
            </ul>
        </nav>

    </div><!-- /fase-contenedor -->

    <!-- ░░ HUD INFERIOR — barra de usuario ░░ -->
    <div class="hud-area" id="hud-usuario">
        <?php if ($logueado): ?>
            <!-- Usuario logueado -->
            <div class="hud-izquierda">
                <div class="hud-avatar-icon">☣</div>
                <div class="hud-info">
                    <span class="hud-label">SUPERVIVIENTE</span>
                    <span class="nombre-jugador"><?= $nombreUsuario ?></span>
                </div>
            </div>
            <div class="hud-derecha">
                <a href="pages/perfil.php" class="hud-btn hud-btn-perfil" id="btn-hud-perfil">
                    ▶ MI PERFIL
                </a>
                <a href="sessions/logout.php" class="hud-btn hud-btn-logout" id="btn-hud-logout">
                    ⏻ CERRAR SESIÓN
                </a>
            </div>
        <?php else: ?>
            <!-- Sin sesión -->
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

    <!-- ░░ MODAL — requiere login para jugar ░░ -->
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
                    ▶ INICIAR SESIÓN
                </a>
                <a href="sessions/registro.php" class="modal-btn modal-btn-secundario" id="modal-btn-registro">
                    ✚ CREAR CUENTA
                </a>
            </div>
            <button class="modal-cerrar" id="modal-cerrar" aria-label="Cerrar">✕ CANCELAR</button>
        </div>
    </div>

    <script>
        const logueado = <?= $logueado ? 'true' : 'false' ?>;

        const btnJugar   = document.getElementById('btn-jugar');
        const modal      = document.getElementById('modal-login');
        const btnCerrar  = document.getElementById('modal-cerrar');
        const overlay    = modal;

        btnJugar.addEventListener('click', function (e) {
            e.preventDefault();
            if (logueado) {
                window.location.href = 'juego.php';
            } else {
                modal.classList.add('modal-visible');
            }
        });

        btnCerrar.addEventListener('click', () => modal.classList.remove('modal-visible'));

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) modal.classList.remove('modal-visible');
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') modal.classList.remove('modal-visible');
        });
    </script>

</body>

</html>