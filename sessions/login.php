

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="../styles/style.css">
</head>
<body>
    <form action="login.php" method="POST" class="login-form">
        <h2>Iniciar Sesion</h2>
        <input type="text" name="username" placeholder="Usuario" required>
        <input type="password" name="password" placeholder="Contraseña" required>
        <button type="submit">Entrar</button>
        <button type="button" onclick="window.location.href='../index.php'">Volver al menu</button>
        <button type="button" onclick="window.location.href='register.php'">Registrarse</button>
    </form>
</body>
</html>