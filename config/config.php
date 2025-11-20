<?php
;
define('DEV', true);                       // In development or live? Development = true | Live = false
define('DOMAIN', 'http://localhost');       // Domain (used to create links in emails)
define('ROOT_FOLDER', 'public');           // Name of document root folder (e.g. public, content, htdocs)

// DOC_ROOT is created because the download code has several versions of the sample site
// On a live site a single forward slash / would indicate the document root folder
$this_folder   = substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT']));
$parent_folder = dirname($this_folder);
//define("DOC_ROOT", $parent_folder . DIRECTORY_SEPARATOR . ROOT_FOLDER . DIRECTORY_SEPARATOR);
define("DOC_ROOT", "/focus-local/public/");   // LINUX

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
    strncmp($host, '192.168.', 8) === 0 ||    // LAN addresses
    strpos($host, 'focus-local') !== false    // if you ever use a local vhost
) {
    $env = 'local';
} else {
    $env = 'a2';
}

$envFile = __DIR__ . "/config.$env.php";

if (!file_exists($envFile)) {
    die("Environment config file not found: " . htmlspecialchars($envFile));
}

require $envFile;
// Now we expect these variables to be defined in config.local.php / config.a2.php:
// $db_host, $db_name, $db_user, $db_pass
// $smtp_host, $smtp_port, $smtp_encryption
// $smtp_username, $smtp_password
// $from_email, $from_name

// SMTP server settings (from env-specific config)
$email_config = [
    'server'      => $smtp_host,
    'port'        => $smtp_port,
    'username'    => $smtp_username,
    'password'    => $smtp_password,
    'security'    => $smtp_encryption,
    'admin_email' => $from_email,
    'debug'       => 0,
];

/// Database settings (from env-specific config)
$type     = 'mysql';                 // Type of database
$server   = $db_host;                // Server the database is on
$db       = $db_name;                // Name of the database
$port     = '3306';                  // Port; keep 3306 unless you use something else
$charset  = 'utf8mb4';               // UTF-8 encoding using 4 bytes per character
$username = $db_user;                // DB username
$password = $db_pass;                // DB password

// DO NOT CHANGE NEXT LINE
$dsn = "$type:host=$server;dbname=$db;port=$port;charset=$charset"; // Create DSN



// File upload settings
define('MEDIA_TYPES', ['image/jpeg', 'image/png', 'image/gif','image/tmp',]); // Allowed file types
define('FILE_EXTENSIONS', ['jpeg', 'jpg', 'png', 'gif','tmp']);       // Allowed file extensions
define('MAX_SIZE', '5242880');                                    // Max file size
// DO NOT EDIT:
define('UPLOADS', dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . ROOT_FOLDER . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR); // Image upload folder0