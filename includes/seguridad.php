<?php

function esLocalhost(): bool
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';

    $locales = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];

    return in_array($host, $locales, true)
        || in_array($ip, $locales, true)
        || str_starts_with($host, '192.168.')
        || str_ends_with($host, '.local');
}
function forzarHttps(): void
{
    if (esLocalhost()) {
        return;
    }

    $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? 80) == 443
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    if (!$esHttps) {
        $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('Location: ' . $url, true, 301);
        exit;
    }
}

function configurarSesionSegura(): void
{
    $enProduccion = !esLocalhost();

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $enProduccion,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function enviarCabecerasSeguridad(): void
{
    header('X-Frame-Options: DENY');

    header('X-Content-Type-Options: nosniff');

    header('X-XSS-Protection: 1; mode=block');

    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (!esLocalhost()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    header(
        "Content-Security-Policy: "
        . "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; "
        . "img-src 'self' data:; "
        . "frame-ancestors 'none';"
    );
}

forzarHttps();
configurarSesionSegura();
enviarCabecerasSeguridad();
