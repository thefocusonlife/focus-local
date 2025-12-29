<?php
declare(strict_types=1);

// In development or live? Development = true | Live = false
define('DEV', true);
define('DOMAIN', 'http://localhost'); // Domain (used to create links in emails)
define('ROOT_FOLDER', 'public'); // Name of document root folder (e.g. public, content, htdocs)

// DOC_ROOT is created because the download code has several versions of the sample site
// On a live site a single forward slash / would indicate the document root folder
$this_folder = substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT']));
$parent_folder = dirname($this_folder);
//define("DOC_ROOT", $parent_folder . DIRECTORY_SEPARATOR . ROOT_FOLDER . DIRECTORY_SEPARATOR);
define('DOC_ROOT', '/focus-local/public/'); // LINUX

// Simple .env loader (safe to commit; .env itself is not)
$dotenvFile = __DIR__ . '/../.env';
if (file_exists($dotenvFile)) {
    $lines = file($dotenvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if (!getenv($name)) {
            putenv("$name=$value");
        }
    }
}

// Auto-detect environment (local vs A2) and load env-specific config
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (
    $host === 'localhost' ||
    $host === '127.0.0.1' ||
    strncmp($host, '192.168.', 8) === 0 || // LAN addresses
    strpos($host, 'focus-local') !== false // if you ever use a local vhost
) {
    $env = 'local';
} else {
    $env = 'a2';
}

$envFile = __DIR__ . "/config.$env.php";

if (!file_exists($envFile)) {
    die('Environment config file not found: ' . htmlspecialchars($envFile));
}

require $envFile;
// Now we expect these variables to be defined in config.local.php / config.a2.php:
// $db_host, $db_name, $db_user, $db_pass
// $smtp_host, $smtp_port, $smtp_encryption
// $smtp_username, $smtp_password
// $from_email, $from_name

// SMTP server settings (from env-specific config)
$email_config = [
    'server' => $smtp_host,
    'port' => $smtp_port,
    'username' => $smtp_username,
    'password' => $smtp_password,
    'security' => $smtp_encryption,
    'admin_email' => $from_email,
    'debug' => 0,
];

/// Database settings (from env-specific config)
$type = 'mysql'; // Type of database
$server = $db_host; // Server the database is on
$db = $db_name; // Name of the database
$port = '3306'; // Port; keep 3306 unless you use something else
$charset = 'utf8mb4'; // UTF-8 encoding using 4 bytes per character
$username = $db_user; // DB username
$password = $db_pass; // DB password

// DO NOT CHANGE NEXT LINE
$dsn = "$type:host=$server;dbname=$db;port=$port;charset=$charset"; // Create DSN

// ===============================
// File upload settings (smartphone-friendly)
// ===============================

// Prefer validating by MIME detected server-side (finfo), not by extension.
define('MEDIA_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    // iOS HEIC / HEIF (may or may not be convertible depending on server libs)
    'image/heic',
    'image/heif',
]);

define('FILE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif']);

// =====================================================
// TFOL Image + Upload Constants (canonical + safe guards)
// =====================================================

// --- Upload size ---
if (!defined('MAX_SIZE')) {
    // 5MB is often too small for modern phones. 20MB is a practical starting point.
    define('MAX_SIZE', 20 * 1024 * 1024); // 20 MB
}

// --- Image driver selection: auto | imagick | gd ---
if (!defined('IMAGE_DRIVER')) {
    $driver = strtolower((string) (getenv('IMAGE_DRIVER') ?: 'auto'));
    if (!in_array($driver, ['auto', 'imagick', 'gd'], true)) {
        $driver = 'auto';
    }
    define('IMAGE_DRIVER', $driver);
}

// --- Canonical max dimension (long edge of bounding box) ---
if (!defined('IMAGE_MAX_DIM')) {
    $dim = (int) (getenv('IMAGE_MAX_DIM') ?: 1600);
    if ($dim < 200) {
        $dim = 200;
    } // prevent nonsense values
    if ($dim > 8000) {
        $dim = 8000;
    } // prevent runaway memory use
    define('IMAGE_MAX_DIM', $dim);
}

// --- Thumbnail defaults ---
if (!defined('IMAGE_THUMB_W')) {
    $tw = (int) (getenv('IMAGE_THUMB_W') ?: 600);
    if ($tw < 50) {
        $tw = 50;
    }
    if ($tw > 4000) {
        $tw = 4000;
    }
    define('IMAGE_THUMB_W', $tw);
}

if (!defined('IMAGE_THUMB_H')) {
    $th = (int) (getenv('IMAGE_THUMB_H') ?: 400);
    if ($th < 50) {
        $th = 50;
    }
    if ($th > 4000) {
        $th = 4000;
    }
    define('IMAGE_THUMB_H', $th);
}

// --- Canonical quality (single knob) ---
if (!defined('IMAGE_QUALITY')) {
    $q = (int) (getenv('IMAGE_QUALITY') ?: 82);
    if ($q < 40) {
        $q = 40;
    }
    if ($q > 95) {
        $q = 95;
    }
    define('IMAGE_QUALITY', $q);
}

// --- Output format (keep existing behavior; can move to .env later) ---
if (!defined('IMAGE_OUTPUT_FORMAT')) {
    $fmt = strtolower((string) (getenv('IMAGE_OUTPUT_FORMAT') ?: 'jpg'));
    if (!in_array($fmt, ['jpg', 'jpeg', 'webp', 'png'], true)) {
        $fmt = 'jpg';
    }
    // normalize jpeg -> jpg for consistency
    if ($fmt === 'jpeg') {
        $fmt = 'jpg';
    }
    define('IMAGE_OUTPUT_FORMAT', $fmt);
}

// -----------------------------------------------------
// Backward-compatible aliases (only if not already set)
// -----------------------------------------------------

if (!defined('IMAGE_MAX_WIDTH')) {
    define('IMAGE_MAX_WIDTH', IMAGE_MAX_DIM);
}

if (!defined('IMAGE_MAX_HEIGHT')) {
    define('IMAGE_MAX_HEIGHT', IMAGE_MAX_DIM);
}

// ImageService.php currently references IMAGE_JPEG_QUALITY.
// Keep it as an alias to the canonical IMAGE_QUALITY.
if (!defined('IMAGE_JPEG_QUALITY')) {
    define('IMAGE_JPEG_QUALITY', IMAGE_QUALITY);
}

if (!defined('IMAGE_WEBP_QUALITY')) {
    define('IMAGE_WEBP_QUALITY', IMAGE_QUALITY);
}

// Optional legacy aliases if you have older code somewhere:
// (uncomment ONLY if you find references)
// if (!defined('THUMB_W')) { define('THUMB_W', IMAGE_THUMB_W); }
// if (!defined('THUMB_H')) { define('THUMB_H', IMAGE_THUMB_H); }

// DO NOT EDIT:
define(
    'UPLOADS',
    dirname(__DIR__, 1) .
        DIRECTORY_SEPARATOR .
        ROOT_FOLDER .
        DIRECTORY_SEPARATOR .
        'uploads' .
        DIRECTORY_SEPARATOR,
);
