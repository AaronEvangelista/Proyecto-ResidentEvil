<?php
session_start();

if (!empty($_SESSION['logueado'])) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../includes/loginValidacion.php';
require_once __DIR__ . '/../includes/registrarServicio.php';

$errores = [];
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    $validacion = validarRegistro($username, $email, $password, $confirm);

    if (!$validacion['valido']) {
        $errores = $validacion['errores'];
    } else {
        $resultado = registrarUsuario($username, $email, $password);

        if ($resultado['exito']) {
            $exito = true;
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
    <meta name="description" content="Crea tu cuenta de superviviente">
    <title>Registro</title>
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/session.css">
</head>

<body>
    <div class="login-wrapper">
        <div class="login-card">

            <img src="../img/logo.png" alt="Resident Evil" class="logo-mini">
            <h2>Registro</h2>
            <p class="subtitulo-form">Nuevo superviviente</p>

            <?php if ($exito): ?>
                <div class="alerta alerta-exito">
                    Registrado Ya puedes iniciar sesion
                    <br><br>
                    <button type="button" class="btn-submit" onclick="window.location.href='login.php'"
                        style="width:100%;margin-top:6px;">
                        Iniciar sesion
                    </button>
                </div>
            <?php else: ?>

                <?php if (!empty($errores)): ?>
                    <div class="alerta alerta-error">
                        <ul>
                            <?php foreach ($errores as $e): ?>
                                <li>
                                    <?= htmlspecialchars($e) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form id="form-registro" action="registro.php" method="POST" novalidate>

                    <div class="campo-grupo">
                        <label for="username">Nombre de usuario</label>
                        <input type="text" id="username" name="username" placeholder="Nombre"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" maxlength="50" autocomplete="username"
                            required>
                    </div>

                    <div class="campo-grupo" style="margin-top:10px;">
                        <label for="email">Correo electronico</label>
                        <input type="email" id="email" name="email" placeholder="gmail"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" maxlength="100" autocomplete="email"
                            required>
                    </div>

                    <div class="campo-grupo" style="margin-top:10px;">
                        <label for="password">Contraseña</label>
                        <input type="password" id="password" name="password" placeholder="Contraseña" maxlength="72"
                            autocomplete="new-password" required>
                        <div class="strength-bar">
                            <div class="strength-fill" id="strength-fill"></div>
                        </div>
                        <div class="strength-label" id="strength-label"></div>
                    </div>

                    <div class="campo-grupo" style="margin-top:10px;">
                        <label for="confirm">Confirmar contraseña</label>
                        <input type="password" id="confirm" name="confirm" placeholder="Repite la contraseña" maxlength="72"
                            autocomplete="new-password" required>
                    </div>

                    <button type="submit" class="btn-submit" style="margin-top:10px;">
                        ▶ Crear cuenta
                    </button>
                </form>

            <?php endif; ?>

            <hr class="separador">

            <button type="button" class="btn-secundario" onclick="window.location.href='login.php'">
                &larr; Ya tengo cuenta
            </button>

            <button type="button" class="btn-secundario" onclick="window.location.href='../index.php'">
                &larr; Volver al menu
            </button>

        </div>
    </div>

    <script>
        const pwdInput = document.getElementById('password');
        const fillEl = document.getElementById('strength-fill');
        const labelEl = document.getElementById('strength-label');

        if (pwdInput && fillEl && labelEl) {
            pwdInput.addEventListener('input', () => {
                const val = pwdInput.value;
                let score = 0;
                if (val.length >= 6) score++;
                if (val.length >= 10) score++;
                if (/[A-Z]/.test(val)) score++;
                if (/[0-9]/.test(val)) score++;
                if (/[^A-Za-z0-9]/.test(val)) score++;

                const niveles = [
                    { label: '', color: '#1a1a1a', pct: '0%' },
                    { label: 'MUY DEBIL', color: '#8b0000', pct: '20%' },
                    { label: 'DEBIL', color: '#cc3300', pct: '40%' },
                    { label: 'MODERADA', color: '#cc8800', pct: '60%' },
                    { label: 'FUERTE', color: '#336600', pct: '80%' },
                    { label: 'MUY FUERTE', color: '#00aa00', pct: '100%' },
                ];

                const n = niveles[Math.min(score, 5)];
                fillEl.style.width = n.pct;
                fillEl.style.background = n.color;
                labelEl.textContent = n.label;
                labelEl.style.color = n.color;
            });
        }

        const confirmInput = document.getElementById('confirm');
        if (pwdInput && confirmInput) {
            const checkMatch = () => {
                if (!confirmInput.value) {
                    confirmInput.style.borderColor = '#3a3a3a';
                    return;
                }
                if (confirmInput.value === pwdInput.value) {
                    confirmInput.style.borderColor = '#2a8a2a';
                } else {
                    confirmInput.style.borderColor = '#cc2200';
                }
            };
            confirmInput.addEventListener('input', checkMatch);
            pwdInput.addEventListener('input', checkMatch);
        }
    </script>
</body>

</html>