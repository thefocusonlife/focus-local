<?php
declare(strict_types=1);

/**
 * Anonymous identity helper
 * - Ensures anonymous continuity before login
 * - Used by A2 event logging + user state
 */
function getAnonId(): string
{
    if (
        isset($_COOKIE['anon_id']) &&
        is_string($_COOKIE['anon_id']) &&
        preg_match('/^[A-Za-z0-9_\-]{8,80}$/', $_COOKIE['anon_id'])
    ) {
        return $_COOKIE['anon_id'];
    }

    $anonId = 'anon_' . bin2hex(random_bytes(16));

    setcookie('anon_id', $anonId, [
        'expires' => time() + 60 * 60 * 24 * 365, // 1 year
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    return $anonId;
}
