<?php
require_once 'database/db_init.php';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Evil: Trivia Survival</title>
    <link rel="stylesheet" href="styles/style.css">
</head>

<body>

    <div class="fase-contenedor">

        <header class="logo-area">
            <img src="./img/logo.png" alt="Resident Evil Trivia" class="main-logo">
            <p class="subtitulo">THE SURVIVAL HORROR</p>
        </header>

        <nav class="menu-area">
            <ul>
                <li><a href="./pages/juego.php" class="item-seleccionado">
                        <span class="icono"></span>JUGAR STORY<span class="llave"></span>
                    </a></li>
                <li><a href="./pages/enciclopedia.php"><span class="icono"></span> ENCICLOPEDIA DE RACCOON CITY</a></li>
                <li><a href="#"><span class="icono"></span> ARCHIVOS DE DATOS</a></li>
                <li><a href="./pages/logros.php"><span class="icono"></span> Logros</a></li>
                <li><a href="#"><span class="icono"></span> OPCIONES</a></li>
            </ul>
        </nav>

    </div>
</body>

</html>