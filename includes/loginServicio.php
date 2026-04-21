<?php
require_once __DIR__ . '/../includes/conexion.php';
function autenticarUsuario(string $username, string $password): array
{
    try {
        $pdo = getDB();

        $stmt = $pdo->prepare("
            SELECT id_usuario, nombre, email, password
            FROM   usuarios
            WHERE  LOWER(nombre) = LOWER(:nombre)
            LIMIT  1
        ");
        $stmt->execute([':nombre' => trim($username)]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            return [
                'exito' => false,
                'usuario' => null,
                'mensaje' => 'Usuario o contraseña incorrectos.',
            ];
        }

        if (!password_verify($password, $usuario['password'])) {
            return [
                'exito' => false,
                'usuario' => null,
                'mensaje' => 'Usuario o contraseña incorrectos.',
            ];
        }

        unset($usuario['password']);

        return [
            'exito' => true,
            'usuario' => $usuario,
            'mensaje' => 'Bienvenido, ' . htmlspecialchars($usuario['nombre']) . '!',
        ];

    } catch (PDOException $e) {
        error_log('Error en autenticarUsuario: ' . $e->getMessage());
        return [
            'exito' => false,
            'usuario' => null,
            'mensaje' => 'Error del servidor. Inténtalo de nuevo más tarde.',
        ];
    }
}

function iniciarSesionUsuario(array $usuario): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    session_regenerate_id(true);

    $_SESSION['usuario_id'] = $usuario['id_usuario'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['logueado'] = true;
}