<?php
function validarLogin(string $username, string $password): array
{
    $errores = [];

    if (empty(trim($username))) {
        $errores[] = 'El nombre de usuario no puede estar vacio.';
    } elseif (strlen($username) < 3) {
        $errores[] = 'El nombre de usuario debe tener al menos 3 caracteres.';
    } elseif (strlen($username) > 50) {
        $errores[] = 'El nombre de usuario no puede superar los 50 caracteres.';
    } elseif (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username)) {
        $errores[] = 'El nombre de usuario solo puede contener letras, numeros, guiones y puntos.';
    }

    if (empty($password)) {
        $errores[] = 'La contraseña no puede estar vacia.';
    } elseif (strlen($password) < 6) {
        $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif (strlen($password) > 72) {
        $errores[] = 'La contraseña no puede superar los 72 caracteres.';
    }

    return [
        'valido' => empty($errores),
        'errores' => $errores,
    ];
}


function validarRegistro(string $username, string $email, string $password, string $confirm): array
{
    $errores = [];

    if (empty(trim($username))) {
        $errores[] = 'El nombre de usuario no puede estar vacio.';
    } elseif (strlen($username) < 3) {
        $errores[] = 'El nombre de usuario debe tener al menos 3 caracteres.';
    } elseif (strlen($username) > 50) {
        $errores[] = 'El nombre de usuario no puede superar los 50 caracteres.';
    } elseif (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username)) {
        $errores[] = 'El nombre de usuario solo puede contener letras, numeros, guiones y puntos.';
    }

    if (empty(trim($email))) {
        $errores[] = 'El correo electronico no puede estar vacio.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El formato del correo electronico no es valido.';
    } elseif (strlen($email) > 100) {
        $errores[] = 'El correo electronico no puede superar los 100 caracteres.';
    }

    if (empty($password)) {
        $errores[] = 'La contraseña no puede estar vacia.';
    } elseif (strlen($password) < 6) {
        $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif (strlen($password) > 72) {
        $errores[] = 'La contraseña no puede superar los 72 caracteres.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errores[] = 'La contraseña debe contener al menos una letra y un numero.';
    }

    if ($password !== $confirm) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    return [
        'valido' => empty($errores),
        'errores' => $errores,
    ];
}