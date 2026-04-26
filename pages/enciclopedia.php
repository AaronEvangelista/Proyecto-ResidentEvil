<?php
require_once '../includes/conexion.php';

$stmt = $pdo->query("SELECT * FROM catalogo_archivos ORDER BY id_archivo ASC");
$archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enciclopedia - Archivos de Umbrella</title>
    <link rel="stylesheet" href="../styles/style.css">
</head>

<body>

    <div class="efecto-crt"></div>

    <div class="fase-contenedor layout-enciclopedia">

        <header class="header-enciclopedia">
            <h2 style="color: #8b0000; text-shadow: 2px 2px 0px #000; margin: 0;">BASE DE DATOS: UMBRELLA CORP.</h2>
            <a href="../index.php" class="btn-retro" style="padding: 10px 20px;">Volver al menu</a>
        </header>

        <div class="contenedor-archivos">

            <aside class="lista-documentos">
                <h3 style="color: #ffd700; border-bottom: 1px solid #555; padding-bottom: 10px;">INDICE DE ARCHIVOS</h3>
                <ul id="menu-archivos">
                    <?php if (count($archivos) > 0): ?>
                        <?php foreach ($archivos as $index => $archivo): ?>
                            <li>
                                <button class="btn-archivo" data-id="<?= $archivo['id_archivo'] ?>"
                                    data-nombre="<?= htmlspecialchars($archivo['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-ruta="<?= htmlspecialchars($archivo['ruta_exclusiva'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-info="<?= htmlspecialchars($archivo['informacion'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-imagen="<?= htmlspecialchars($archivo['imagen_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <?= str_pad($index + 1, 2, '0', STR_PAD_LEFT) ?>.
                                    <?= htmlspecialchars($archivo['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><span style="color: #555; font-style: italic;">No se encontraron archivos en la base de
                                datos.</span></li>
                    <?php endif; ?>
                </ul>
            </aside>

            <main class="visor-documento">
                <div id="contenido-visor">
                    <h2 style="color: #ff4500;">Sistema en espera</h2>
                    <p style="color: #aaa;">Seleccione un archivo del índice para decodificar su contenido.</p>
                    <p style="color: #555; font-size: 14px;"><?= count($archivos) ?> archivo(s) encontrados en la base
                        de datos.</p>
                </div>
            </main>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const botones = document.querySelectorAll('.btn-archivo');
            const visor = document.getElementById('contenido-visor');
            let animacionActual = null;

            botones.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    botones.forEach(function (b) { b.classList.remove('activo'); });
                    btn.classList.add('activo');

                    const nombre = btn.getAttribute('data-nombre');
                    const ruta = btn.getAttribute('data-ruta');
                    const info = btn.getAttribute('data-info');
                    const imagen = btn.getAttribute('data-imagen');

                    if (animacionActual) {
                        clearInterval(animacionActual);
                        animacionActual = null;
                    }

                    let etiquetaRuta = '';
                    if (ruta === 'ambos') {
                        etiquetaRuta = 'ACCESO: UNIVERSAL';
                    } else if (ruta === 'chico') {
                        etiquetaRuta = 'ACCESO: RUTA LEON';
                    } else if (ruta === 'chica') {
                        etiquetaRuta = 'ACCESO: RUTA CLAIRE';
                    }

                    let html = '';
                    html += '<div style="border-bottom: 1px solid #333; padding-bottom: 15px; margin-bottom: 20px;">';
                    html += '<h2 style="color: #ff4500; margin: 0 0 8px 0; text-transform: uppercase;">' + nombre + '</h2>';
                    html += '<span style="color: #888; font-size: 13px; letter-spacing: 1px;">' + etiquetaRuta + '</span>';
                    html += '</div>';

                    if (imagen) {
                        html += '<div style="text-align: center; margin-bottom: 20px;">';
                        html += '<img src="' + imagen + '" alt="' + nombre + '" style="max-width: 100%; max-height: 200px; border: 1px solid #333; opacity: 0.85;">';
                        html += '</div>';
                    }

                    html += '<div id="texto-archivo" style="white-space: pre-wrap; line-height: 1.8;"></div>';

                    visor.innerHTML = html;

                    const contenedorTexto = document.getElementById('texto-archivo');
                    let i = 0;
                    const velocidad = 15;

                    animacionActual = setInterval(function () {
                        if (i < info.length) {
                            contenedorTexto.textContent += info.charAt(i);
                            i++;
                            visor.scrollTop = visor.scrollHeight;
                        } else {
                            clearInterval(animacionActual);
                            animacionActual = null;
                        }
                    }, velocidad);
                });
            });
        });
    </script>

</body>

</html>