<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Evil: Trivia Survival</title>
    <link rel="stylesheet" href="styles/style.css">
</head>

<body>


    <header class="logo-area">
        <img src="./img/logo.png" alt="Resident Evil Trivia" class="main-logo">
        <p class="subtitulo">TEST YOUR KNOWLEDGE OF THE SURVIVAL HORROR</p>
    </header>

    <nav class="menu-area">
        <ul>
            <li><a href="juego.php" class="item-seleccionado">
                    <span class="icono"></span> JUGAR TRIVIA <span class="llave"></span>
                </a></li>
            <li><a href="./pages/enciclopedia.php"><span class="icono"></span> ENCICLOPEDIA DE RACCOON CITY</a></li>
            <li><a href="#"><span class="icono"></span> ARCHIVOS DE DATOS</a></li>
            <li><a href="#"><span class="icono"></span> OPCIONES</a></li>
        </ul>
    </nav>

    <footer class="hud-area">
        <div class="hud-izquierda">
            <img id="hud-avatar-img" src="./img/avatar_leon.png" alt="Avatar" class="hud-avatar">
            <span class="hud-texto">JUGADOR 1: <span id="hud-nombre-jugador" class="nombre-jugador">LEON S.
                    KENNEDY</span></span>
        </div>
        <div class="hud-derecha">
            <span class="hud-texto">PUNTOS: <span class="puntos-jugador">0</span></span>
        </div>
    </footer>

    </div>

    <script src="./js/main.js"></script>
</body>

</html>