<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    if (!headers_sent()) {
        session_name('TFOLSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_start();
}

function getSessionIpPrefix(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        return count($parts) >= 3 ? $parts[0] . '.' . $parts[1] . '.' . $parts[2] : $ip;
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return substr($ip, 0, 19);
    }

    return '';
}

function destroyGuardSession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly'],
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function validateGuardSessionSecurity(): bool
{
    $role = (string) ($_SESSION['role'] ?? 'guest');
    if ($role === 'guest') {
        return true;
    }

    $now = time();
    $idleLimit = 1800; // 30 minutes
    $absoluteLimit = 43200; // 12 hours

    if (!isset($_SESSION['created_at'], $_SESSION['last_activity'])) {
        destroyGuardSession();
        return false;
    }

    if ($now - (int) $_SESSION['last_activity'] > $idleLimit) {
        destroyGuardSession();
        return false;
    }

    if ($now - (int) $_SESSION['created_at'] > $absoluteLimit) {
        destroyGuardSession();
        return false;
    }

    $expectedUa = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (($_SESSION['user_agent_hash'] ?? '') !== $expectedUa) {
        destroyGuardSession();
        return false;
    }

    $expectedIpPrefix = getSessionIpPrefix();
    if (
        isset($_SESSION['ip_prefix']) &&
        $_SESSION['ip_prefix'] !== '' &&
        $expectedIpPrefix !== '' &&
        $_SESSION['ip_prefix'] !== $expectedIpPrefix
    ) {
        destroyGuardSession();
        return false;
    }

    $_SESSION['last_activity'] = $now;

    return true;
}

/**
 * Generic access guard
 */
function guard(array $options = []): void
{
    $requireLogin = $options['requireLogin'] ?? true;
    $allowedRoles = $options['allowedRoles'] ?? null;
    $allowUber = $options['allowUber'] ?? true;
    $loginPath = (string) ($options['loginPath'] ?? 'login/');
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

    if ($requireLogin && !validateGuardSessionSecurity()) {
        redirectToLogin($loginPath, false);
    }

    // ---- Role check ----
    if ($allowedRoles !== null) {
        $role = strtolower(trim((string) ($_SESSION['role'] ?? 'guest')));
        $_SESSION['role'] = $role;

        if ($allowUber && $role === 'uber') {
            return;
        }

        if (!in_array($role, $allowedRoles, true)) {
            // Default deny behavior for guards: redirect to safe place
            denyAccess($fallbackPath, 403);
        }
    }
}
function guardPublic(): void
{
    guard([
        'requireLogin' => false,
    ]);
}
function guardAdmin(): void
{
    guard([
        'requireLogin' => true,
        'allowedRoles' => ['admin', 'uber'],
        'loginPath' => 'login/',
        'fallbackPath' => 'index/' . (int) ($_SESSION['website'] ?? 1),
        'timeoutSeconds' => 1800,
    ]);
}

function guardMember(): void
{
    guard([
        'requireLogin' => true,
        'allowedRoles' => ['member', 'admin', 'uber'],
        'loginPath' => 'login/',
        'fallbackPath' => 'index/' . (int) ($_SESSION['website'] ?? 1),
        'timeoutSeconds' => 1800,
    ]);
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

    // Normalize to app-root path if caller passed a relative path like 'login/'
    // DOC_ROOT in TFOL typically looks like '/focus-local/public/' (or similar)
    if (!str_starts_with($loginPath, '/') && defined('DOC_ROOT')) {
        $loginPath = rtrim(DOC_ROOT, '/') . '/' . ltrim($loginPath, '/');
    }

    // If caller passed '/login' but app is mounted under DOC_ROOT, fix that too
    if (str_starts_with($loginPath, '/login') && defined('DOC_ROOT') && DOC_ROOT !== '/') {
        // Convert '/login' -> '{DOC_ROOT}login/'
        $loginPath = rtrim(DOC_ROOT, '/') . '/login/';
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
        $websiteId = (int) ($_SESSION['website'] ?? 1);
        if ($websiteId <= 0) {
            $websiteId = 1;
        }
        $fallbackPath = 'index/' . $websiteId; // NOTE: TFOL-style relative path
    } else {
        // Normalize leading slash paths to TFOL relative paths if needed
        $fallbackPath = ltrim($fallbackPath, '/');
    }

    if ($statusCode > 0) {
        http_response_code($statusCode);
    }

    // Prefer TFOL redirect helper if available (handles DOC_ROOT correctly)
    if (function_exists('redirect')) {
        $_SESSION['flash_failure'] = $_SESSION['flash_failure'] ?? 'Access denied.';
        redirect($fallbackPath);
        exit();
    }

    // Fallback: raw header with DOC_ROOT normalization
    $target = $fallbackPath;

    if (defined('DOC_ROOT')) {
        $target = rtrim(DOC_ROOT, '/') . '/' . ltrim($fallbackPath, '/');
    } elseif (!str_starts_with($target, '/')) {
        $target = '/' . $target;
    }

    if (headers_sent()) {
        echo '<script>window.location.href=' . json_encode($target) . ';</script>';
        exit();
    }

    header('Location: ' . $target);
    exit();
}
