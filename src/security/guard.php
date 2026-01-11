<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Generic access guard
 *
 * @param array $options
 *   - requireLogin (bool)
 *   - allowedRoles (array|null)
 *   - allowUber (bool)
 */
function guard(array $options = []): void
{
    $requireLogin = $options['requireLogin'] ?? true;
    $allowedRoles = $options['allowedRoles'] ?? null;
    $allowUber    = $options['allowUber'] ?? true;

    // ---- Login check ----
    if ($requireLogin && empty($_SESSION['member_id'])) {
        redirectToLogin();
    }

    // ---- Role check ----
    if ($allowedRoles !== null) {
        $role = $_SESSION['role'] ?? 'guest';

        if ($allowUber && $role === 'uber') {
            return;
        }

        if (!in_array($role, $allowedRoles, true)) {
            denyAccess();
        }
    }
}

function redirectToLogin(): void
{
    header('Location: /login');
    exit;
}

function denyAccess(): void
{
    header('Location: /index/1');
    exit;
}
