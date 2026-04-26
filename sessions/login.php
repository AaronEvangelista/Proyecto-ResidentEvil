<?php
require_once __DIR__ . '/../includes/seguridad.php';
session_start();

if (!empty($_SESSION['logueado'])) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../includes/loginValidacion.php';
require_once __DIR__ . '/../includes/loginServicio.php';

$errores = [];
$mensaje = '';
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $validacion = validarLogin($username, $password);

    if (!$validacion['valido']) {
        $errores = $validacion['errores'];
    } else {
        $resultado = autenticarUsuario($username, $password);

        if ($resultado['exito']) {
            iniciarSesionUsuario($resultado['usuario']);
            header('Location: ../index.php');
            exit;
        } else {
            $errores[] = $resultado['mensaje'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesion</title>
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/auth.css">
</head>

<body>
    <div class="login-wrapper">
        <div class="login-card">

            <img src="../img/logo.png" alt="Resident Evil" class="logo-mini">
            <h2>Acceso</h2>
            <p class="subtitulo-form">Identificate, superviviente</p>

            <?php if (!empty($errores)): ?>
                <div class="alerta alerta-error">
                    <ul>
                        <?php foreach ($errores as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form id="form-login" action="login.php" method="POST" novalidate>

                <div class="campo-grupo">
                    <label for="username">Nombre de usuario</label>
                    <input type="text" id="username" name="username" placeholder="Tu usuario"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username" required>
                </div>

                <div class="campo-grupo" style="margin-top:10px;">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" placeholder="Tu clave"
                        autocomplete="current-password" required>
                </div>

                <button type="submit" class="btn-submit" style="margin-top:10px;">
                    Entrar al sistema
                </button>
            </form>

            <hr class="separador">

            <button type="button" class="btn-secundario" onclick="window.location.href='registro.php'">
                No tengo cuenta
            </button>

            <button type="button" class="btn-secundario" onclick="window.location.href='../index.php'">
                Volver al menu
            </button>

        </div>
    </div>
</body>

</html>