<?php
session_start();

//1. Lógica para resetear al personaje si es necesario
if (isset($_SESSION['player_hp'])) {
    $_SESSION['player_hp'] = 100;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>GAME OVER - Resident Evil Project</title>
    <style>
        body {
            background-color: black;
            color: #7a0000; /* Rojo sangre oscuro */
            font-family: 'Courier New', Courier, monospace;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            overflow: hidden;
            text-transform: uppercase;
        }

        .death-container {
            text-align: center;
            animation: fadeIn 5s ease-in;
        }

        h1 {
            font-size: 80px;
            letter-spacing: 15px;
            margin: 0;
            text-shadow: 0 0 20px rgba(122, 0, 0, 0.7);
            filter: blur(1px);
        }

        .options {
            margin-top: 50px;
            animation: fadeIn 8s ease-in;
        }

        a {
            color: #cccccc;
            text-decoration: none;
            font-size: 20px;
            border: 1px solid #333;
            padding: 10px 20px;
            transition: all 0.3s;
            display: inline-block;
            margin: 10px;
        }

        a:hover {
            background-color: #7a0000;
            color: white;
            box-shadow: 0 0 15px #7a0000;
        }

        @keyframes fadeIn {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }

        /* Efecto de estática de fondo opcional */
        .overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: repeating-linear-gradient(0deg, rgba(0,0,0,0.1), rgba(0,0,0,0.1) 1px, transparent 1px, transparent 2px);
            pointer-events: none;
        }
    </style>
</head>
<body>

    <div class="overlay"></div>

    <div class="death-container">
        <h1>YOU ARE DEAD</h1>
        
        <div class="options">
            <a href="../index.php">REINTENTAR</a>
        </div>
    </div>

    <audio autoplay>
        <source src="../sounds/death_sound.mp3" type="audio/mpeg">
    </audio>

</body>
</html>