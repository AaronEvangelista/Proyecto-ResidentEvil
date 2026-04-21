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

// 4. Enemigos (para el script de sonido)
$query_enemigos = $pdo->prepare("SELECT * FROM estado_enemigos WHERE sala_ubicacion = ? AND estado = 'vivo'");
$query_enemigos->execute([$id_sala_actual]);
$enemigo_presente = $query_enemigos->fetch();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Evil - <?php echo $sala['nombre_visual']; ?></title>
    
    <style>
        /* RESET DE NAVEGADOR */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body, html {
            width: 100%;
            height: 100%;
            overflow: hidden; /* Evita scrollbars */
            background-color: #000;
            font-family: 'Courier New', Courier, monospace;
        }

        /* CONTENEDOR PRINCIPAL: PANTALLA COMPLETA */
        #game-container {
            width: 100vw;
            height: 100vh;
            /* La imagen se adapta dinámicamente aquí */
            background-image: url('<?php echo $sala['imagen_url']; ?>');
            background-size: cover;      /* Ocupa toda la pantalla */
            background-position: center; /* Centrada */
            background-repeat: no-repeat;
            
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: background-image 0.5s ease-in-out; /* Suaviza el cambio de sala */
        }

        /* CAPA SUPERIOR: NOMBRE DE LA SALA */
        .hud-top {
            background: linear-gradient(to bottom, rgba(0,0,0,0.8) 0%, transparent 100%);
            color: #ccc;
            padding: 20px;
            text-align: right;
            font-size: 1.2rem;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        /* CONTROLES DE NAVEGACIÓN: FLECHAS */
        .navigation-controls {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            height: 60%;
            pointer-events: none; /* Las cajas no bloquean clics, los enlaces sí */
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .nav-btn {
            pointer-events: auto;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.5);
            font-size: 4rem;
            position: absolute;
            transition: all 0.3s;
            text-shadow: 0px 0px 10px rgba(0,0,0,0.9);
        }

        .nav-btn:hover {
            color: #ff0000;
            transform: scale(1.2);
            text-shadow: 0px 0px 20px #ff0000;
        }

        /* Posicionamiento de flechas */
        .north { top: 0; }
        .south { bottom: 0; }
        .east  { right: 0; }
        .west  { left: 0; }

        /* CUADRO DE TEXTO INFERIOR */
        .message-box {
            background: rgba(0, 0, 0, 0.75);
            border-top: 3px solid #333;
            color: #eee;
            padding: 25px 50px;
            min-height: 120px;
            z-index: 10;
        }

        .message-box p {
            font-size: 1.1rem;
            line-height: 1.5;
            max-width: 800px;
            margin: 0 auto;
        }
    </style>
</head>
<body>

    <div id="game-container">
        
        <div class="hud-top">
            <span class="location-name"><?php echo $sala['nombre_visual']; ?></span>
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
        
    </div>

    <script>
        // Lógica de tensión para tu API de Python
        const tension = "<?php echo $enemigo_presente ? 'alta' : 'baja'; ?>";
        console.log("Sistema de sonido: Nivel " + tension);
        
        // Aquí podrías añadir el fetch a tu sound_service.py
    </script>
</body>
</html>