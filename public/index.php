<?php
declare(strict_types=1);

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

$traceId = null;

if (defined('A2_ENABLED') && A2_ENABLED) {
    require_once APP_ROOT . '/src/RequestContext.php';
    $traceId = RequestContext::traceId();
}
/*
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
);

    // 5) Log only (minimal). No behavior changes.
    // Log only — no behavior changes
    if ($minted) {
        error_log(
            json_encode([
                'event' => 'anon_id_minted',
                'trace_id' => $traceId,
                'path' => $_SERVER['REQUEST_URI'] ?? '',
            ]),
        );
    } elseif ($anonId !== null && $userId === null) {
        error_log(
            json_encode([
                'event' => 'anon_id_reused',
                'trace_id' => $traceId,
                'path' => $_SERVER['REQUEST_URI'] ?? '',
            ]),
        );
    } elseif ($userId !== null) {
        error_log(
            json_encode([
                'event' => 'anon_id_suppressed_logged_in',
                'trace_id' => $traceId,
                'path' => $_SERVER['REQUEST_URI'] ?? '',
                'session_id' => $userId,
            ]),
        );
    }
*/
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

// ------------------------------------------------------------
// Parse request path
// ------------------------------------------------------------
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
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

// Standard TFOL pattern: first segment is the page route
// e.g. /story/123 => $page='story', $id=123
$page = $parts[0] ?? '';
$id = (int) ($parts[1] ?? 0);

// Normalize default home route
if ($page === '') {
    $page = 'index';
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
            route_log($trace, "include=$nestedDirIndex");
            require $nestedDirIndex;
            exit();
        }
        if (is_file($nestedFlat)) {
            route_log($trace, "include=$nestedFlat");
            require $nestedFlat;
            exit();
        }
    }
}

// Now resolve the top-level route
if (is_file($candidateDirIndex)) {
    route_log($trace, "include=$candidateDirIndex");
    require $candidateDirIndex;
    exit();
}

if (is_file($candidateFlat)) {
    if (headers_sent($hsFile, $hsLine)) {
        error_log("[HEADERS_SENT] before X-TFOL headers at $hsFile:$hsLine");
    } else {
        error_log('[HEADERS_OK] headers not sent yet');
    }

    route_log($trace, "include=$candidateFlat");
    header('X-TFOL-URI: ' . ($_SERVER['REQUEST_URI'] ?? ''));
    header('X-TFOL-Candidate: ' . basename($candidateFlat));
    header('X-TFOL-Page: ' . ($page ?? ''));
    header('X-TFOL-Id: ' . (string) ($id ?? ''));

    require $candidateFlat;
    exit();
}

// ------------------------------------------------------------
// Not found
// ------------------------------------------------------------
route_log(
    $trace,
    "NOT FOUND page=$page (dirIndex=" .
        (is_file($candidateDirIndex) ? 'YES' : 'NO') .
        ' flat=' .
        (is_file($candidateFlat) ? 'YES' : 'NO') .
        ')',
);

require $pageRoot . '/page-not-found.php';
exit();
