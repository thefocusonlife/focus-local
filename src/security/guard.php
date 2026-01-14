<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Generic access guard
 *
 * Options:
 * - requireLogin (bool) default true
 * - allowedRoles (array|null) default null
 * - allowUber (bool) default true
 * - loginPath (string) default '/login'
 * - fallbackPath (string|null) default null (auto: /index/{session website or 1})
 * - rememberReturnTo (bool) default true
 */
function guard(array $options = []): void
{
    $requireLogin = $options['requireLogin'] ?? true;
    $allowedRoles = $options['allowedRoles'] ?? null;
    $allowUber = $options['allowUber'] ?? true;
    $loginPath = (string) ($options['loginPath'] ?? '/login');
    $fallbackPath = $options['fallbackPath'] ?? null;
    $rememberReturnTo = $options['rememberReturnTo'] ?? true;

    // ---- Login check ----
    if ($requireLogin && empty($_SESSION['member_id'])) {
        redirectToLogin($loginPath, $rememberReturnTo);
    }

    // ---- Role check ----
    if ($allowedRoles !== null) {
        $role = (string) ($_SESSION['role'] ?? 'guest');

        if ($allowUber && $role === 'uber') {
            return;
        }

        if (!in_array($role, $allowedRoles, true)) {
            denyAccess($fallbackPath);
        }
    }
}

function redirectToLogin(string $loginPath = '/login', bool $rememberReturnTo = true): void
{
    if ($rememberReturnTo) {
        $returnTo = $_SERVER['REQUEST_URI'] ?? '';
        if ($returnTo !== '') {
            $_SESSION['return_to'] = $returnTo;
        }
    }
    header('Location: ' . $loginPath);
    exit();
}

function denyAccess(?string $fallbackPath = null): void
{
    if ($fallbackPath === null || $fallbackPath === '') {
        $websiteId = (int) ($_SESSION['website'] ?? 1);
        if ($websiteId <= 0) {
            $websiteId = 1;
        }
        $fallbackPath = '/index/' . $websiteId;
    }
    header('Location: ' . $fallbackPath);
    exit();
}
