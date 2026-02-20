<?php
declare(strict_types=1);

// DEV MODE ERROR DISPLAY
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * TFOL Front Controller Router
 * - Prefers folder routes:   src/pages/<route>/index.php
 * - Falls back to flat pages: src/pages/<route>.php
 *
 * Examples:
 *   /                 -> src/pages/index.php
 *   /admin            -> src/pages/admin/index.php
 *   /admin/menu       -> src/pages/admin/menu.php   (or admin/menu/index.php if you ever create it)
 *   /story/123        -> src/pages/story.php   (pages can read $id)
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/src/bootstrap.php';
require_once APP_ROOT . '/src/services/AnonIdService.php';
require_once APP_ROOT . '/src/Infrastructure/AppLogger.php';
require_once APP_ROOT . '/src/lib/debug.php';

use App\Infrastructure\AppLogger;

$traceId = null;

if (defined('A2_ENABLED') && A2_ENABLED) {
    require_once APP_ROOT . '/src/RequestContext.php';
    $traceId = RequestContext::traceId();
}

if (empty($traceId)) {
    $traceId = bin2hex(random_bytes(16));
}

// 1) Determine logged-in user (whatever your A2 Blueprint does today)
$userId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
$anonSvc = new AnonIdService();
$existingAnonId = $anonSvc->getFromCookie($_COOKIE);
$anonId = $existingAnonId;
$minted = false;

if ($anonSvc->shouldMint($userId, $existingAnonId)) {
    $anonId = $anonSvc->mint();
    $minted = true;

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    header($anonSvc->buildSetCookieHeader($anonId, $isHttps), false);
}

// 4) Instantiate RequestContext (pass both; do not override user context)
/* $ctx = new RequestContext(
    userId: $userId,
    anonId: $anonId, // may be null when logged in, by design above
    requestId: $requestId, // whatever you already have
    path: $_SERVER['REQUEST_URI'] ?? '/',
);*/

// 5) Log only (minimal). No behavior changes.
// Log only — no behavior changes
if ($minted) {
    AppLogger::log('info', 'anon_id_minted', [
        'trace_id' => $traceId,
        'path' => $_SERVER['REQUEST_URI'] ?? '',
    ]);
} elseif ($anonId !== null && $userId === null) {
    AppLogger::log('info', 'anon_id_reused', [
        'trace_id' => $traceId,
        'path' => $_SERVER['REQUEST_URI'] ?? '',
    ]);
} elseif ($userId !== null) {
    AppLogger::log('info', 'anon_id_suppressed_logged_in', [
        'trace_id' => $traceId,
        'path' => $_SERVER['REQUEST_URI'] ?? '',
        'session_id' => $userId,
    ]);
}

// ... continue existing dispatch/controller handling unchanged

// ------------------------------------------------------------
// Debug toggle
// ------------------------------------------------------------
define('TFOL_ROUTE_DEBUG', false);

$trace = substr(bin2hex(random_bytes(4)), 0, 8);

function route_log(string $trace, string $msg): void
{
    if (defined('TFOL_ROUTE_DEBUG') && TFOL_ROUTE_DEBUG) {
        error_log("[$trace] $msg");
    }
}
$logWebsiteId = (int) ($_SESSION['website'] ?? ($_SESSION['website_id'] ?? 0));
// ------------------------------------------------------------
// Parse request path
// ------------------------------------------------------------
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

AppLogger::log('info', 'request', [
    'trace_id' => $traceId,
    'website_id' => $logWebsiteId,
    'route' => $uriPath,
    'controller' => 'front-controller', // will be improved later once dispatch resolves
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'anon_id' => $anonId,
    'member_id' => $userId, // your app uses $_SESSION['id']
    'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
]);

// ------------------------------------------------------------
// Normalize base path so both /focus-local/... and /focus-local/public/... route the same
// ------------------------------------------------------------
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/'); // e.g. /focus-local/public
$mountCandidates = array_unique([
    $scriptDir,
    preg_replace('#/public$#', '', $scriptDir), // e.g. /focus-local
]);

foreach ($mountCandidates as $base) {
    if ($base !== '' && $base !== '/' && str_starts_with($uriPath, $base . '/')) {
        $uriPath = substr($uriPath, strlen($base));
        break;
    }
}

if ($uriPath === '') {
    $uriPath = '/';
}

route_log($trace, 'URI=' . ($_SERVER['REQUEST_URI'] ?? '') . " PATH=$uriPath");

// Remove DOC_ROOT prefix if your app is in a subfolder like /focus-local/public
// DOC_ROOT should be defined in config/bootstrap (as you already have)
$base = defined('DOC_ROOT') ? rtrim((string) DOC_ROOT, '/') : '';
$path = $uriPath;

if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}

$path = trim((string) $path, '/');

// Split into segments
$parts = $path === '' ? [] : explode('/', $path);

/// ------------------------------------------------------------
// Canonicalize "menu slug" URLs:
// Accept /menu/{id}/{anything...} and ignore the trailing slug/subpath.
// Optionally 301 redirect the browser to the canonical /menu/{id} URL.
// ------------------------------------------------------------
if (($parts[0] ?? '') === 'menu' && isset($parts[1]) && ctype_digit((string) $parts[1])) {
    $menuId = (int) $parts[1];

    if (count($parts) > 2) {
        $ignoredTail = implode('/', array_slice($parts, 2));

        // Build canonical URL using the actual script mount (e.g. /focus-local/public)
        $mountBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $canonical = $mountBase . '/menu/' . $menuId;

        route_log($trace, "menu_canonicalize: ignored_tail=$ignoredTail -> redirect=$canonical");

        // OPTIONAL: uncomment these 2 lines if you want the browser URL cleaned up
        //  header('Location: ' . $canonical, true, 301);
        //  exit();

        // If you prefer NO redirect today, comment out the header/exit above and let it fall through:
        // $parts = ['menu', (string)$menuId];
        // $path  = 'menu/' . $menuId;
        // $uriPath = '/' . $path;
    }
}

// Standard TFOL pattern: first segment is the page route
// e.g. /story/123 => $page='story', $id=123
$page = $parts[0] ?? '';
$id = (int) ($parts[1] ?? 0);

// Normalize default home route
if ($page === '') {
    $page = 'index';
}
// Website context capture (based on your routing convention)
if (in_array($page, ['index', 'login', 'register'], true) && !empty($id)) {
    $_SESSION['website_id'] = (int) $id;
    $_SESSION['website'] = $id; // <-- IMPORTANT: bridge for legacy controllers
}

route_log($trace, "uriPath=$uriPath base=$base path=$path page=$page id=$id");

// ------------------------------------------------------------
// Resolve file to include
// Prefer folder index: src/pages/<route>/index.php
// Then flat page:      src/pages/<route>.php
// ------------------------------------------------------------
$pageRoot = APP_ROOT . '/src/pages';

// This is the *key* behavior that fixes /admin routing:
$candidateDirIndex = $pageRoot . '/' . $page . '/index.php';
$candidateFlat = $pageRoot . '/' . $page . '.php';

// Optional: allow nested routes like /admin/menu to map to src/pages/admin/menu.php
// If you are using nested routes in your app, this enables them:
if (count($parts) > 1) {
    // Build a route like "admin/menu" (but keep $id available separately as numeric if applicable)
    // If your second segment is numeric, we treat it as $id (already captured above) and do NOT nest.
    if (!ctype_digit((string) ($parts[1] ?? ''))) {
        $nested = $parts[0] . '/' . $parts[1];
        $nestedDirIndex = $pageRoot . '/' . $nested . '/index.php';
        $nestedFlat = $pageRoot . '/' . $nested . '.php';

        // Prefer nested routes over top-level ones

        if (is_file($nestedDirIndex)) {
            AppLogger::log('info', 'dispatch', [
                'trace_id' => $traceId,
                'website_id' => $logWebsiteId,
                'route' => $uriPath,
                'controller' => $nestedDirIndex,
                'id' => $id ?? null,
                'page' => $page,
                'nested' => $nested,
                'anon_id' => $anonId,
                'member_id' => $userId,
            ]);

            route_log($trace, "include=$nestedDirIndex");
            require $nestedDirIndex;
            exit();
        }

        if (is_file($nestedFlat)) {
            AppLogger::log('info', 'dispatch', [
                'trace_id' => $traceId,
                'website_id' => $logWebsiteId,
                'route' => $uriPath,
                'controller' => $nestedFlat,
                'id' => $id ?? null,
                'page' => $page,
                'nested' => $nested,
                'anon_id' => $anonId,
                'member_id' => $userId,
            ]);

            route_log($trace, "include=$nestedFlat");
            require $nestedFlat;
            exit();
        }
    }
}

// ------------------------------------------------------------
// Resolve top-level route
// Prefer folder index: src/pages/<page>/index.php
// Then flat page:      src/pages/<page>.php
// ------------------------------------------------------------

if (is_file($candidateDirIndex)) {
    AppLogger::log('info', 'dispatch', [
        'trace_id' => $traceId,
        'website_id' => $logWebsiteId,
        'route' => $uriPath,
        'controller' => $candidateDirIndex,
        'page' => $page,
        'id' => $id ?? null,
        'anon_id' => $anonId,
        'member_id' => $userId,
    ]);

    route_log($trace, "include=$candidateDirIndex");
    require $candidateDirIndex;
    exit();
}

if (is_file($candidateFlat)) {
    AppLogger::log('info', 'dispatch', [
        'trace_id' => $traceId,
        'website_id' => $logWebsiteId,
        'route' => $uriPath,
        'controller' => $candidateFlat,
        'page' => $page,
        'id' => $id ?? null,
        'anon_id' => $anonId,
        'member_id' => $userId,
    ]);

    route_log($trace, "include=$candidateFlat");
    require $candidateFlat;
    exit();
}

// ------------------------------------------------------------
// Not found
// ------------------------------------------------------------
AppLogger::log('warn', 'route.not_found', [
    'trace_id' => $traceId,
    'website_id' => $logWebsiteId,
    'route' => $uriPath,
    'page' => $page,
    'id' => $id ?? null,
    'anon_id' => $anonId,
    'member_id' => $userId,
]);

require $pageRoot . '/page-not-found.php';
exit();
