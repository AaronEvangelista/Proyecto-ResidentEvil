<?php
require_once __DIR__ . '/../includes/seguridad.php';
session_start();

if (empty($_SESSION['logueado'])) {
    header('Location: ../sessions/login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$usuarioId = (int) $_SESSION['usuario_id'];
$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? '');
$usuarioEmail = htmlspecialchars($_SESSION['usuario_email'] ?? '');

try {
    $stmt = $pdo->prepare("
        SELECT id_usuario, nombre, email, fecha_registro
        FROM   usuarios
        WHERE  id_usuario = :id
        LIMIT  1
    ");
    $stmt->execute([':id' => $usuarioId]);
    $usuario = $stmt->fetch();
} catch (PDOException $e) {
    $usuario = null;
}

$partidas = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id_partida, p.ruta, p.sala_actual, p.fecha_guardado,
               s.nombre_visual AS nombre_sala
        FROM   partida p
        LEFT JOIN catalogo_salas s ON s.id_sala = p.sala_actual
        WHERE  p.id_usuario = :id
        ORDER BY p.fecha_guardado DESC
    ");
    $stmt->execute([':id' => $usuarioId]);
    $partidas = $stmt->fetchAll();
} catch (PDOException $e) {
    $partidas = [];
}

$fechaRegistro = 'Desconocida';
if ($usuario && !empty($usuario['fecha_registro'])) {
    $dt = new DateTime($usuario['fecha_registro']);
    $meses = [
        'Enero',
        'Febrero',
        'Marzo',
        'Abril',
        'Mayo',
        'Junio',
        'Julio',
        'Agosto',
        'Septiembre',
        'Octubre',
        'Noviembre',
        'Diciembre'
    ];
    $fechaRegistro = $dt->format('d') . ' de ' . $meses[(int) $dt->format('n') - 1] . ' de ' . $dt->format('Y');
}

function enmascararEmail(string $email): string
{
    $partes = explode('@', $email, 2);
    if (count($partes) !== 2)
        return '***';

    $local = $partes[0];
    $dominio = $partes[1];

    $visibles = mb_strlen($local) <= 3 ? 1 : 2;
    $inicio = htmlspecialchars(mb_substr($local, 0, $visibles));

    return $inicio . '***@' . htmlspecialchars($dominio);
}

$emailMasked = enmascararEmail($usuario['email'] ?? $usuarioEmail);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Perfil del superviviente <?= $usuarioNombre ?> en Resident Evil: Trivia Survival.">
    <title>Perfil — <?= $usuarioNombre ?> | RE:Trivia</title>
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/auth.css">
    <link rel="stylesheet" href="../styles/perfil.css">
</head>

<body>

    <div class="efecto-crt"></div>

    <div class="perfil-wrapper">

        <header class="perfil-header">
            <a href="../index.php" class="perfil-volver" id="btn-volver-inicio">
                ← VOLVER AL MENÚ
            </a>
            <h1 class="perfil-titulo">FICHA DEL SUPERVIVIENTE</h1>
            <a href="../sessions/logout.php" class="perfil-logout" id="btn-cerrar-sesion">
                ⏻ CERRAR SESIÓN
            </a>
        </header>

        <section class="perfil-id-card" aria-label="Datos de identidad">

            <div class="perfil-avatar" aria-hidden="true">
                <span class="perfil-avatar-icono">☣</span>
                <div class="perfil-avatar-ring"></div>
            </div>

            <div class="perfil-datos">
                <div class="perfil-nombre"><?= $usuarioNombre ?></div>
                <div class="perfil-rango">SUPERVIVIENTE · RACCOON CITY P.D.</div>

                <div class="perfil-grid-info">
                    <div class="perfil-info-bloque">
                        <span class="perfil-info-label">CORREO
                            <span class="perfil-privado-tag" title="Tu correo real está protegido">🔒 PRIVADO</span>
                        </span>
                        <span class="perfil-info-valor perfil-email-masked"><?= $emailMasked ?></span>
                    </div>
                    <div class="perfil-info-bloque">
                        <span class="perfil-info-label">REGISTRO</span>
                        <span class="perfil-info-valor"><?= $fechaRegistro ?></span>
                    </div>
                    <div class="perfil-info-bloque">
                        <span class="perfil-info-label">ID DE AGENTE</span>
                        <span class="perfil-info-valor">#<?= str_pad($usuarioId, 5, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    <div class="perfil-info-bloque">
                        <span class="perfil-info-label">ESTADO</span>
                        <span class="perfil-info-valor perfil-estado-activo">● ACTIVO</span>
                    </div>
                </div>
            </div>

        </section>

        <section class="perfil-stats" aria-label="Estadísticas">
            <div class="stat-bloque">
                <span class="stat-valor"><?= count($partidas) ?></span>
                <span class="stat-label">PARTIDAS GUARDADAS</span>
            </div>
            <div class="stat-bloque">
                <span class="stat-valor">0</span>
                <span class="stat-label">LOGROS</span>
            </div>
            <div class="stat-bloque">
                <span class="stat-valor">—</span>
                <span class="stat-label">ÚLTIMO ACCESO</span>
            </div>
        </section>

        <section class="perfil-seccion" aria-label="Partidas guardadas">
            <h2 class="perfil-seccion-titulo">
                <span class="icono-seccion">▶</span>
                PARTIDAS GUARDADAS
            </h2>

            <?php if (empty($partidas)): ?>
                <div class="perfil-vacio">
                    <span class="perfil-vacio-icono">⚠</span>
                    <p>No hay ninguna partida guardada.<br>
                        <span class="perfil-vacio-sub">Inicia una partida y guarda tu progreso en una máquina de
                            escribir.</span>
                    </p>
                    <a href="../index.php" class="perfil-btn-jugar" id="btn-ir-a-jugar">▶ JUGAR AHORA</a>
                </div>
            <?php else: ?>
                <div class="partidas-lista">
                    <?php foreach ($partidas as $partida): ?>
                        <div class="partida-card">
                            <div class="partida-ruta-badge partida-ruta-<?= htmlspecialchars($partida['ruta']) ?>">
                                <?= strtoupper(htmlspecialchars($partida['ruta'])) ?>
                            </div>
                            <div class="partida-info">
                                <div class="partida-sala">
                                    <?= htmlspecialchars($partida['nombre_sala'] ?? $partida['sala_actual']) ?>
                                </div>
                                <div class="partida-fecha">
                                    Guardado: <?= htmlspecialchars(substr($partida['fecha_guardado'], 0, 16)) ?>
                                </div>
                            </div>
                            <a href="../juego.php?partida=<?= (int) $partida['id_partida'] ?>" class="partida-btn-continuar"
                                id="btn-continuar-<?= (int) $partida['id_partida'] ?>">
                                CONTINUAR →
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="perfil-seccion" aria-label="Logros">
            <h2 class="perfil-seccion-titulo">
                <span class="icono-seccion">★</span>
                LOGROS
            </h2>
            <div class="perfil-vacio">
                <span class="perfil-vacio-icono"></span>
                <p>Los logros se desbloquean a medida que avanzas por la historia.<br>
                    <span class="perfil-vacio-sub">¿Sobrevivirás a Raccoon City?</span>
                </p>
            </div>
        </section>

    </div>

</body>

</html>