<?php
declare(strict_types=1);

// ------------------------------------------------------------
// Bootstrap
// ------------------------------------------------------------

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/src/bootstrap.php';

// ------------------------------------------------------------
// Debug toggle
// ------------------------------------------------------------

define('TFOL_ROUTE_DEBUG', false);

$trace = substr(bin2hex(random_bytes(4)), 0, 8);

function route_log(string $trace, string $msg): void
{
    if (!TFOL_ROUTE_DEBUG) {
        return;
    }
    error_log("[$trace] $msg");
}

// ------------------------------------------------------------
// Resolve request path → route parts
// ------------------------------------------------------------

// 1) Get URI path only (no query string)
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// 2) Strip DOC_ROOT ONLY if it matches the beginning
//    DOC_ROOT example: "/focus-local/public/"
$docRoot = defined('DOC_ROOT') ? DOC_ROOT : '/';

if ($docRoot !== '/' && str_starts_with($path, $docRoot)) {
    $path = substr($path, strlen($docRoot));
}

// 3) Normalize
$path = trim($path, '/'); // "" or "index/1"
$parts = $path === '' ? [] : explode('/', $path);

// ------------------------------------------------------------
// HARD SAFETY NORMALIZATION (intentional, minimal, safe)
// ------------------------------------------------------------

// If router ever sees project folder, drop it
if (($parts[0] ?? '') === 'focus-local') {
    array_shift($parts);
}

// If router ever sees "public", drop it
if (($parts[0] ?? '') === 'public') {
    array_shift($parts);
}

// ------------------------------------------------------------
// Route + ID (AFTER normalization)
// ------------------------------------------------------------

$route = $parts[0] ?? '';
$id = (int) ($parts[1] ?? 0);

// ------------------------------------------------------------
// Canonical defaults (prevents splash → 404 flash)
// ------------------------------------------------------------

if ($route === '') {
    redirect('index/1');
    exit();
}

if ($route === 'index' && $id <= 0) {
    redirect('index/1');
    exit();
}

// ------------------------------------------------------------
// Resolve PHP page from ROUTE ONLY
// ------------------------------------------------------------

$php_page = APP_ROOT . '/src/pages/' . $route . '.php';

// ------------------------------------------------------------
// Debug
// ------------------------------------------------------------

route_log(
    $trace,
    'REQUEST_URI=' .
        ($_SERVER['REQUEST_URI'] ?? '') .
        " path=$path route=$route id=$id parts=" .
        json_encode($parts),
);

// ------------------------------------------------------------
// Dispatch
// ------------------------------------------------------------

if (!is_file($php_page)) {
    route_log($trace, "404 missing php_page=$php_page");
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}
if (!is_file($php_page)) {
    route_log($trace, "404 missing php_page=$php_page");
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

$page = $route; // legacy compatibility

route_log($trace, "DISPATCH php_page=$php_page");
include $php_page;
exit();
