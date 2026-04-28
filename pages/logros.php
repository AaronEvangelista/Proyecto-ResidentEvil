<?php
session_start();
require_once '../includes/conexion.php';

$logueado = $_SESSION['logueado'] ?? false;
$id_usuario = $_SESSION['usuario_id'] ?? null;

$stmtLogros = $pdo->query("SELECT * FROM catalogo_logros ORDER BY id_logro ASC");
$todos_los_logros = $stmtLogros->fetchAll(PDO::FETCH_ASSOC);

$logros_desbloqueados = [];
if ($logueado && $id_usuario) {
    $stmtDesbloqueados = $pdo->prepare("SELECT id_logro, fecha_desbloqueo FROM logros_desbloqueados WHERE id_usuario = :id_usuario");
    $stmtDesbloqueados->execute([':id_usuario' => $id_usuario]);
    while ($row = $stmtDesbloqueados->fetch(PDO::FETCH_ASSOC)) {
        $logros_desbloqueados[$row['id_logro']] = $row['fecha_desbloqueo'];
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logros Resident</title>
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/logros.css">
</head>

<body>

    <div class="efecto-crt"></div>

    <div class="fase-contenedor layout-logros">
        <div class="contenedor-logros">
            <header class="header-logros">
                <h2 style="color: #8b0000; text-shadow: 2px 2px 0px #000; margin: 0;">HISTORIAL DE LOGROS</h2>
                <a href="../index.php" class="btn-retro" style="padding: 10px 20px;">Volver al menú</a>
            </header>

            <?php if (!$logueado): ?>
                <div class="mensaje-login">
                    ⚠ IDENTIFICACIÓN REQUERIDA. Inicia sesión para rastrear tu progreso de logros.
                </div>
            <?php endif; ?>

            <div class="lista-logros">
                <?php foreach ($todos_los_logros as $logro): ?>
                    <?php
                    $estaDesbloqueado = isset($logros_desbloqueados[$logro['id_logro']]);
                    $claseCompletado = $estaDesbloqueado ? 'logro-completado' : '';
                    $icono = $estaDesbloqueado ? '🏆' : '🔒';
                    ?>
                    <div class="logro-item <?= $claseCompletado ?>">
                        <div class="logro-icono">
                            <?= $icono ?>
                        </div>
                        <div class="logro-info">
                            <h3><?= htmlspecialchars($logro['nombre']) ?></h3>
                            <p><?= htmlspecialchars($logro['descripcion']) ?></p>
                            <?php if ($estaDesbloqueado): ?>
                                <div class="fecha-desbloqueo">Desbloqueado el:
                                    <?= htmlspecialchars($logros_desbloqueados[$logro['id_logro']]) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</body>

</html>