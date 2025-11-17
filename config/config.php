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

/// Database settings
$type     = 'mysql';                 // Type of database
$server   = 'localhost';             // Server the database is on
$db       = 'focus_local';           // Name of the database
$port     = '3306';                  // Port is usually 8889 in MAMP and 3306 in XAMPP
$charset  = 'utf8mb4';               // UTF-8 encoding using 4 bytes of data per character
$username = 'Geoff';                 // Enter YOUR username here
$password = 'Gfocus2025!local';          // Enter YOUR password here


// DO NOT CHANGE NEXT LINE
$dsn = "$type:host=$server;dbname=$db;port=$port;charset=$charset"; // Create DSN

// SMTP server settings
$email_config = [
    'server'      => 'smtp.gmail.com',
    'port'        => '587',
    'username'    => 'geoff@thefocusonlife.com',

    'password'    => 'qvazffrszrbklahv',
    'security'    => 'TLS/STARTTLS',
    'admin_email' => 'contact@thefocusonlife.com',
   // 'debug'       => (DEV) ? 2 : 0,
    'debug'       => (DEV) ? 0 : 2,  
];


// File upload settings
define('MEDIA_TYPES', ['image/jpeg', 'image/png', 'image/gif','image/tmp',]); // Allowed file types
define('FILE_EXTENSIONS', ['jpeg', 'jpg', 'png', 'gif','tmp']);       // Allowed file extensions
define('MAX_SIZE', '5242880');                                    // Max file size
// DO NOT EDIT:
define('UPLOADS', dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . ROOT_FOLDER . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR); // Image upload folder
