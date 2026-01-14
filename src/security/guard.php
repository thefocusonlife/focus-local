<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Generic access guard
 */
function guard(array $options = []): void
{
    $requireLogin = $options['requireLogin'] ?? true;
    $allowedRoles = $options['allowedRoles'] ?? null;
    $allowUber = $options['allowUber'] ?? true;
    $loginPath = (string) ($options['loginPath'] ?? '/login');
    $fallbackPath = $options['fallbackPath'] ?? null;
    $rememberReturnTo = $options['rememberReturnTo'] ?? true;

    // ---- Normalize legacy session keys ----
    // TFOL currently stores member id in $_SESSION['id'] (canonicalize to member_id for guards)
    if (empty($_SESSION['member_id']) && !empty($_SESSION['id'])) {
        $_SESSION['member_id'] = (int) $_SESSION['id'];
    }

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
            // Default deny behavior for guards: redirect to safe place
            denyAccess($fallbackPath, 403);
        }
    }
}

/**
 * Canonical redirect to login (header-safe)
 */
function redirectToLogin(string $loginPath = '/login', bool $rememberReturnTo = true): void
{
    if ($rememberReturnTo) {
        $returnTo = $_SERVER['REQUEST_URI'] ?? '';
        if ($returnTo !== '') {
            $_SESSION['return_to'] = $returnTo;
        }
    }

    if (headers_sent()) {
        echo '<script>window.location.href=' . json_encode($loginPath) . ';</script>';
        exit();
    }

    header('Location: ' . $loginPath);
    exit();
}

/**
 * Canonical denyAccess
 *
 * - If $fallbackPath provided: redirect there
 * - Else: redirect to /index/{session website or 1}
 * - Optionally emit an HTTP status code before redirect (default 403)
 *
 * NOTE: redirecting on deny prevents leaking admin pages to unauthorized users
 * while keeping user experience consistent.
 */
function denyAccess(?string $fallbackPath = null, int $statusCode = 403): void
{
    if ($fallbackPath === null || $fallbackPath === '') {
        // Prefer the same session key everywhere
        $websiteId = (int) ($_SESSION['website'] ?? 1);
        if ($websiteId <= 0) {
            $websiteId = 1;
        }
        $fallbackPath = '/index/' . $websiteId;
    }

    if ($statusCode > 0) {
        http_response_code($statusCode);
    }

    if (headers_sent()) {
        echo '<script>window.location.href=' . json_encode($fallbackPath) . ';</script>';
        exit();
    }

    header('Location: ' . $fallbackPath);
    exit();
}
