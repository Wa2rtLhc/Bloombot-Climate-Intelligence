<?php
declare(strict_types=1);

// Always start the session first
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 1) Clear all session data
$_SESSION = [];

// 2) Delete the session cookie (if there is one)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        (bool)($params['secure'] ?? false),
        (bool)($params['httponly'] ?? true)
    );
}

// 3) Destroy the session on the server
session_destroy();

// 4) (Optional) Start a fresh session to carry a flash message
session_start();
$_SESSION['flash'] = 'You have been logged out.';

// 5) Redirect to login page (adjust path if needed)
header('Location: login.html');
exit;