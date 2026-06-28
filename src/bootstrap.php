<?php
use PhpBook\CMS\CMS;

// APP_ROOT should be defined by public/index.php (or other entrypoints)
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__FILE__, 2));
}

if (defined('TFOL_BOOTSTRAPPED')) {
    return;
}
define('TFOL_BOOTSTRAPPED', true);

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/src/functions.php';
require_once APP_ROOT . '/src/identity.php';
require_once APP_ROOT . '/src/RequestContext.php';
require_once APP_ROOT . '/src/Exceptions/ForeignKeyConstraintException.php';
require_once APP_ROOT . '/config/recaptcha.php'; // ⭐ reCAPTCHA helper

// Session (before any output)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

RequestContext::traceId();
RequestContext::anonId();

if (DEV === true) {
    set_exception_handler('handle_exception');
    set_error_handler('handle_error');
    register_shutdown_function('handle_shutdown');
}

$cms = new CMS($dsn, $username, $password);
unset($dsn, $username, $password);

// Ensure a website is always defined for fresh sessions (guest or logged-in)
if (!isset($_SESSION['website']) || (int) $_SESSION['website'] <= 0) {
    $_SESSION['website'] = 1; // default website (TFOL 1)
}

$twig_options['cache'] = APP_ROOT . '/var/cache';
$twig_options['debug'] = DEV;

$loader = new Twig\Loader\FilesystemLoader(APP_ROOT . '/templates');
$twig = new Twig\Environment($loader, $twig_options);

// Make sure RequestContext is loaded before this line (require_once RequestContext.php)
$twig->addGlobal('trace_id', RequestContext::traceId());
$twig->addGlobal('anon_id', RequestContext::anonId());

$twig->addGlobal('doc_root', DOC_ROOT);
$twig->addGlobal('request_uri', $_SERVER['REQUEST_URI'] ?? '');

// Use ONE session global (prefer CMS session if that's your standard)
$session = $cms->getSession();
$twig->addGlobal('session', $session);

$unreadMessages = 0;

if (!empty($session->id)) {
    $unreadMessages = $cms->getNote()->countUnreadMessages((int) $session->id);
}

$twig->addGlobal('unread_messages', $unreadMessages);

$twig->addGlobal('id', $id ?? null);
$twig->addGlobal('websiteId', $id ?? null);

// ------------------------------------------------------------
// Refresh link (return to current page, force new request)
// ------------------------------------------------------------
$uri = $_SERVER['REQUEST_URI'] ?? '/';

$sep = strpos($uri, '?') !== false ? '&' : '?';
$refreshHref = $uri . $sep . '_r=' . time();

$data['refresh_href'] = $refreshHref;

if (DEV === true) {
    try {
        $twig->addExtension(new \Twig\Extension\DebugExtension());
    } catch (\LogicException $e) {
        // DebugExtension already registered; ignore
    }
}

$twig->addGlobal('recaptcha_site_key', $config['recaptcha_site_key']);

// Memory
ini_set('memory_limit', '128M');

$session = $cms->getSession(); // Create session
$twig->addGlobal('session', $session); // Add session to Twig global

// ⭐ Add reCAPTCHA v3 site key to all Twig templates
$twig->addGlobal('recaptcha_site_key', $config['recaptcha_site_key']);
