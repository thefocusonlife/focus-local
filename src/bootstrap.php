<?php
use PhpBook\CMS\CMS;

define('APP_ROOT', dirname(__FILE__, 2)); // Application root

require APP_ROOT . '/src/functions.php'; // Functions
require APP_ROOT . '/config/config.php'; // Configuration data
require APP_ROOT . '/vendor/autoload.php'; // Autoload libraries

// -------------------------------------------------
// Session (before any output)
// -------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (DEV === false) {
    // If not in development
    set_exception_handler('handle_exception'); // Set exception handler
    set_error_handler('handle_error'); // Set error handler
    register_shutdown_function('handle_shutdown'); // Set shutdown handler
}

$cms = new CMS($dsn, $username, $password); // Create CMS object
unset($dsn, $username, $password); // Remove database config data

$twig_options['cache'] = APP_ROOT . '/var/cache'; // Path to Twig cache folder
$twig_options['debug'] = DEV; // If dev mode, turn debug on

$loader = new Twig\Loader\FilesystemLoader(APP_ROOT . '/templates'); // Twig loader
$twig = new Twig\Environment($loader, $twig_options); // Twig environment
$twig->addGlobal('doc_root', DOC_ROOT); // Document root
// -------------------------------------------------
// Twig globals (THIS IS WHERE IT GOES)
// -------------------------------------------------
$twig->addGlobal('session', $_SESSION);
$twig->addGlobal('request_uri', $_SERVER['REQUEST_URI'] ?? '');

// php.ini memory_limit setting not working so programatically set php memory limit in bootstrap.php
// this resolved the out of memory problem when trying to upload and resize iphone size images
ini_set('memory_limit', '128M');
$session = $cms->getSession(); // Create session
$twig->addGlobal('session', $session); // Add session to Twig global

if (DEV === true) {
    // If in development
    $twig->addExtension(new \Twig\Extension\DebugExtension()); // Add Twig debug extension
}

// ⭐ Add reCAPTCHA v3 site key to all Twig templates
$twig->addGlobal('recaptcha_site_key', $config['recaptcha_site_key']);
