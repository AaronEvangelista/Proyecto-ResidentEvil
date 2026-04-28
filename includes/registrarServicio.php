<?php
require_once __DIR__ . '/conexion.php';

function registrarUsuario(string $username, string $email, string $password): array
{
    try {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT id_usuario FROM usuarios
            WHERE  LOWER(nombre) = LOWER(:nombre)
            LIMIT  1
        ");
        $stmt->execute([':nombre' => trim($username)]);
        if ($stmt->fetch()) {
            return [
                'exito' => false,
                'mensaje' => 'Ese nombre de usuario ya esta en uso',
            ];
        }

        $stmt = $pdo->prepare("
            SELECT id_usuario FROM usuarios
            WHERE  LOWER(email) = LOWER(:email)
            LIMIT  1
        ");
        $stmt->execute([':email' => trim($email)]);
        if ($stmt->fetch()) {
            return [
                'exito' => false,
                'mensaje' => 'Ese correo electronico ya esta registrado.',
            ];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("
            INSERT INTO usuarios (nombre, email, password)
            VALUES (:nombre, :email, :password)
        ");
        $stmt->execute([
            ':nombre' => trim($username),
            ':email' => strtolower(trim($email)),
            ':password' => $hash,
        ]);

        return [
            'exito' => true,
            'mensaje' => 'Ya puedes iniciar sesion superviviente.',
        ];

    } catch (PDOException $e) {
        error_log('Error en registrarUsuario: ' . $e->getMessage());
        return [
            'exito' => false,
            'mensaje' => 'Error del servidor',
        ];
    }
}
