<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php'; // <-- this must create $cms (or include file that does)

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (($_SESSION['id'] ?? 0) <= 0) {
    // guest
    if (!isset($_SESSION['pagelimit'])) {
        $_SESSION['pagelimit'] = 100;
    }
}

$uri = $_SERVER['REQUEST_URI'] ?? '';

// If someone hits the non-public front path, redirect to the canonical /public path
if (strpos($uri, '/focus-local/index/') === 0) {
    $prefix = '/focus-local/index';
    if (strpos($uri, $prefix . '/') === 0) {
        header('Location: /focus-local/public' . substr($uri, strlen($prefix)), true, 302);
        exit();
    }
}

// Also cover exact /focus-local/index (no trailing slash)
if ($uri === '/focus-local/index' || $uri === '/focus-local/index/') {
    header('Location: /focus-local/public/index/1', true, 302); // or /public/index/
    exit();
}

// ----------------------------
// EARLY TERMINAL ROUTES
// ----------------------------
$path = parse_url($uri, PHP_URL_PATH) ?? '';
$path = rtrim($path, '/');

// Normalize against DOC_ROOT so this works on local + staging + prod
$normalized = preg_replace('#^' . preg_quote(DOC_ROOT, '#') . '#', '', $path);
$normalized = trim($normalized, '/');

if ($normalized === 'logout') {
    require __DIR__ . '/../src/pages/logout.php';
    exit();
}

if (defined('DEV') && DEV) {
    error_log(
        '[INDEX] ' .
            date('c') .
            ' method=' .
            ($_SERVER['REQUEST_METHOD'] ?? '') .
            ' uri=' .
            ($_SERVER['REQUEST_URI'] ?? '') .
            ' host=' .
            ($_SERVER['HTTP_HOST'] ?? '') .
            ' sid=' .
            (session_id() ?: 'NONE') .
            ' user=' .
            ($_SESSION['id'] ?? 'NULL'),
    );
}

if (defined('DEV') && DEV) {
    $rid = bin2hex(random_bytes(3));
    $_SESSION['__rid'] = $rid;

    error_log(
        '[ROUTER] rid=' .
            $rid .
            ' method=' .
            ($_SERVER['REQUEST_METHOD'] ?? '') .
            ' uri=' .
            ($_SERVER['REQUEST_URI'] ?? '') .
            ' host=' .
            ($_SERVER['HTTP_HOST'] ?? '') .
            ' sess_id=' .
            (session_id() ?: 'NONE') .
            ' user=' .
            ($_SESSION['id'] ?? 'NULL'),
    );
}

// Required session defaults (prevents undefined index warnings)
$_SESSION['website'] = (int) ($_SESSION['website'] ?? 1);
$_SESSION['id'] = (int) ($_SESSION['id'] ?? 0);
$_SESSION['role'] = (string) ($_SESSION['role'] ?? 'guest');
$_SESSION['account_id'] = (int) ($_SESSION['account_id'] ?? 0);

file_put_contents(
    '/tmp/tfol-route.log',
    date('c') . ' URI=' . ($_SERVER['REQUEST_URI'] ?? '') . "\n",
    FILE_APPEND,
);
file_put_contents(
    '/tmp/tfol-route.log',
    date('c') .
        ' SESSION id=' .
        var_export($_SESSION['id'] ?? null, true) .
        ' role=' .
        var_export($_SESSION['role'] ?? null, true) .
        ' website=' .
        var_export($_SESSION['website'] ?? null, true) .
        "\n",
    FILE_APPEND,
);

require_once dirname(__DIR__) . '/src/services/ImageCapabilities.php';

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

// If the request targets an existing file, serve it directly (let Apache handle it)
$fullPath = realpath(__DIR__ . $uriPath); // might not resolve depending on docroot layout

// Simple bypass for common static directories under /public/
if (preg_match('#^' . preg_quote(DOC_ROOT, '#') . '(img|css|js|uploads)/#', $uriPath)) {
    return false; // for PHP built-in server; harmless otherwise
}

// get path for website and menus                             // Setup file
//$id = 2;
// Normalize path: strip query string, strip DOC_ROOT, trim leading/trailing slashes
$path = mb_strtolower($uriPath);
$path = preg_replace('#^' . preg_quote(DOC_ROOT, '#') . '#', '', $path);
$path = trim($path, '/');

$parts = $path === '' ? [''] : explode('/', $path);

if ($parts[0] != 'admin') {
    // If an admin page
    $page = $parts[0] ?: 'index'; // Page name (or use index)
    if (!isset($parts[1]) and $parts[0] === '') {
        $page = 'home';
        $php_page = APP_ROOT . '/src/pages/' . $page . '.php';

        include $php_page;
        exit();
    }
    // Special-case: Guide uses /guide and /guide/<slug> (slug is not numeric)
    if ($parts[0] === 'guide') {
        $page = 'guide';
        // Pass slug via $parts[1] (can be empty for /guide)
        include APP_ROOT . '/src/pages/guide.php';
        exit();
    }

    $id = $parts[1] ?? 1;
    // Get ID (or use null)
} else {
    // If not an admin page
    $page = 'admin/' . ($parts[1] ?? '');
    if (isset($parts[2])) {
        // Page name
        $id = intval($parts[2]) ?? 1; // Get ID
    }
}

if (isset($id)) {
    $id = filter_var($id, FILTER_VALIDATE_INT); // Validate ID
}
$php_page = APP_ROOT . '/src/pages/' . $page . '.php'; // Path to PHP page

if (!file_exists($php_page)) {
    // If page not in array
    $php_page = APP_ROOT . '/src/pages/page-not-found.php'; // Include page not found
}
//var_dump_pre($php_page);
include $php_page;
