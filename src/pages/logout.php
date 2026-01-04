<?php
declare(strict_types=1);

$key = (int) ($_SESSION['website'] ?? 1);

// Clear all session data
$_SESSION = [];

// Destroy session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Expire session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        (bool) ($params['secure'] ?? false),
        (bool) ($params['httponly'] ?? true),
    );
}

// If you still want to keep your CMS session cleanup, it can stay,
// but it should be after clearing PHP session state.
// $cms->getSession()->delete();

redirect('index/' . $key);
exit();
