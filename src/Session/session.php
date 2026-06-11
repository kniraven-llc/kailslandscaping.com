<?php
declare(strict_types=1);

/*
    Shared session helper.

    This file starts the PHP session and creates a CSRF token.
    The CSRF token helps protect forms from fake submissions.
*/

if (ob_get_level() === 0) {
    ob_start();
}

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443')
);

if (session_status() === PHP_SESSION_NONE) {
    session_name('kailslandscaping_session');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function getCsrfToken(): string
{
    return (string)($_SESSION['csrf_token'] ?? '');
}

function isValidCsrfToken(string $token): bool
{
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');

    return $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function isUserLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function redirectTo(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function requireLogin(string $loginPath = '/login.php'): void
{
    if (!isUserLoggedIn()) {
        redirectTo($loginPath);
    }
}