<?php
declare(strict_types=1);

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
// Resolve request path → parts
// ------------------------------------------------------------
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = mb_strtolower($uri);
$path = parse_url($path, PHP_URL_PATH) ?: '/';

$docRoot = DOC_ROOT;
if ($docRoot !== '' && str_starts_with($path, $docRoot)) {
    $path = substr($path, strlen($docRoot));
}

$path = trim($path, '/');
$parts = explode('/', $path);

// admin routes: /admin/<page>/<id
if (($parts[0] ?? '') === 'admin') {
    $page = 'admin/' . ($parts[1] ?? 'index');
    $id = $parts[2] ?? null;
} else {
    $page = $parts[0] ?? 'index';
    $id = $parts[1] ?? null;
}

route_log($trace, "REQUEST uri=$uri path=$path page=$page id=$id parts=" . json_encode($parts));

// ------------------------------------------------------------
// Dispatch
// ------------------------------------------------------------
$php_page = APP_ROOT . '/src/pages/' . $page . '.php';

if (!is_file($php_page)) {
    route_log($trace, "404: missing php_page=$php_page");
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

route_log($trace, "DISPATCH php_page=$php_page");
include $php_page;
exit();
