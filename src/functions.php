<?php
// UTILITY FUNCTIONS

function is_admin($role): bool
{
    return $role === 'admin' || $role === 'uber';
}

function is_guest($role)
{
    if ($role == 'guest') {
    } else {
        // If role is not admin
        header('Location: ' . DOC_ROOT); // Send to home page
        exit(); // Stop code running
    }
}

function redirect(string $location, array $parameters = [], int $response_code = 302): void
{
    $qs = $parameters ? '?' . http_build_query($parameters) : '';

    // Normalize leading slash
    $location = '/' . ltrim($location, '/');

    $base = defined('DOC_ROOT') ? rtrim((string) DOC_ROOT, '/') : '';

    header('Location: ' . $base . $location . $qs, true, $response_code);
    exit();
}

function create_filename(string $filename, string $uploads): string
{
    $basename = pathinfo($filename, PATHINFO_FILENAME); // Get basename
    $extension = pathinfo($filename, PATHINFO_EXTENSION); // Get extension
    $cleanname = preg_replace('/[^A-z0-9]/', '-', $basename); // Clean basename
    $filename = $cleanname . '.' . $extension; // Destination
    $i = 0; // Counter
    while (file_exists($uploads . $filename)) {
        // If file exists
        $i = $i + 1; // Update counter
        $filename = $basename . $i . '.' . $extension; // New filename
    }

    return $filename; // Return filename
}

function create_seo_name(string $text): string
{
    $text = strtolower($text); // Convert text to lowercase
    $text = trim($text); // Remove spaces from start/end
    if (function_exists('transliterator_transliterate')) {
        // If transliterator installed
        $text = transliterator_transliterate('Latin-ASCII', $text); // Transliterate
    }
    $text = preg_replace('/ /', '-', $text); // Replace spaces with dashes
    $text = preg_replace('/[^-A-z0-9 ]+/', '', $text); // Remove if not a dash, A-z or 0-9
    return $text; // Return the SEO name
}

// ERROR AND EXCEPTION HANDLING FUNCTIONS
// Convert errors to exceptions
function handle_error($error_type, $error_message, $error_file, $error_line)
{
    throw new ErrorException($error_message, 0, $error_type, $error_file, $error_line); // Turn into ErrorException
}

// Handle exceptions - log exception and show error message (if server does not send error page listed in .htaccess)
function handle_exception($e)
{
    error_log($e); // Log the error
    http_response_code(500); // Set the http response code
    echo "<h1>Sorry, a problem occurred</h1>
          The site's owners have been informed. Please try again later.";
}

// Handle fatal errors
function handle_shutdown()
{
    $error = error_get_last(); // Check for error in script
    if ($error !== null) {
        // If there was an error next line throws exception
        $e = new ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line'],
        );
        handle_exception($e); // Call exception handler
    }
}

// formatted var dump variables
function var_dump_pre($mixed = null)
{
    echo '<pre>';
    var_dump($mixed);
    echo '</pre>';
    return null;
}

/**
 * Generate a CSRF token and store it in session
 */
function generate_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;

    return $token;
}

/**
 * Verify CSRF token from form against session
 */
function verify_csrf(string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $valid = isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);

    // Invalidate the token after checking so it can't be reused
    unset($_SESSION['csrf_token']);

    return $valid;
}

function autoLinkUrls(string $text): string
{
    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    $linked = preg_replace(
        '~(https?://[^\s<]+)~i',
        '<a href="$1" target="_blank" rel="noopener">$1</a>',
        $escaped,
    );

    return nl2br($linked);
}

function formatTextWithLinks(string $text): string
{
    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    $linked = preg_replace(
        '~(https?://[^\s<]+)~i',
        '<a href="$1" target="_blank" rel="noopener">$1</a>',
        $escaped,
    );

    return nl2br($linked);
}

function getScopedWebsiteFilter($websiteId)
{
    return $websiteId == 1 ? '' : 'WHERE website_id = ' . intval($websiteId);
}
