<?php
declare(strict_types=1);
use PhpBook\Validate\Validate; // Import Validate namespace

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/security/guard.php';

// --------------------------
// newnote.php GUARD PATTERN
// --------------------------

// Parse route: /newnote/{targetId}
// Use your existing menu-path parsing style:
$path = mb_strtolower($_SERVER['REQUEST_URI'] ?? '');
$path = strtok($path, '?'); // strip query string
$path = substr($path, strlen(DOC_ROOT)); // remove DOC_ROOT prefix
$parts = explode('/', trim($path, '/'));

$targetId = (int) ($parts[1] ?? 0);

// Viewer must be logged in
$viewerId = (int) ($_SESSION['id'] ?? 0);
if ($viewerId <= 0) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
    header('Location: ' . DOC_ROOT . '/login');
    exit();
}

// Session website (canonical TFOL key appears to be session.website from your logs)
$sessionWebsiteId = (int) ($_SESSION['website'] ?? 0);

// Viewer website from member session
$viewerWebsiteId = (int) ($_SESSION['website'] ?? 0);

// Basic sanity
if ($targetId <= 0 || $sessionWebsiteId <= 0 || $viewerWebsiteId <= 0) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

// Sanity: viewer must belong to current website session
if ($viewerWebsiteId !== $sessionWebsiteId) {
    // safest: force clean login
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: ' . DOC_ROOT . '/login');
    exit();
}

// Load target member (the person being requested)
$targetMember = $cms->getMember()->get($targetId);
if (!$targetMember || empty($targetMember['id'])) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

$targetWebsiteId = (int) ($targetMember['website'] ?? 0);
if ($targetWebsiteId <= 0) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

// ------------------------------------------------------------------
// Require intent token for ALL loads (same-website and cross-website)
// Blocks address-bar reuse by any non-originating member.
// ------------------------------------------------------------------

$k = (string) ($_GET['k'] ?? '');

if ($k === '') {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

if (!isset($_SESSION['newnote_intents']) || !is_array($_SESSION['newnote_intents'])) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

$intent = $_SESSION['newnote_intents'][$k] ?? null;

$ok =
    is_array($intent) &&
    (int) ($intent['viewer_id'] ?? 0) === (int) $viewerId &&
    (int) ($intent['target_id'] ?? 0) === (int) $targetId &&
    (int) ($intent['target_website'] ?? 0) === (int) $targetWebsiteId &&
    (int) ($intent['exp'] ?? 0) >= time();

if (!$ok) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

// burn token (one-time use)
unset($_SESSION['newnote_intents'][$k]);

// Prevent request-to-self
if ($targetId === $viewerId) {
    header('Location: ' . DOC_ROOT . '/member/' . $viewerId);
    exit();
}

// Optional: On POST, overwrite spoofable fields (recommended)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_POST['viewer_id'] = $viewerId; // who is making request
    $_POST['target_id'] = $targetId; // who request is for
    $_POST['website'] = $sessionWebsiteId; // website scope, if stored
}

// OPTIONAL (recommended): hard-stop any spoofed viewerId in POST.
// If your form includes viewer_id or sender_id, ignore it and overwrite.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_POST['viewer_id'] = $viewerId; // authoritative
    $_POST['owner_id'] = $ownerId; // authoritative
}

// Initialize variables needed for the HTML page

$note = [
    'id' => '',
    'note_type' => 1,
    'to_id' => 0,
    'to_name' => '',
    'from_id' => 0,
    'from_name' => '',
    'family_id' => 0,
    'request' => 'Request to follow.',
    'allow' => 0,
    'request_date' => 'current_time_stamp()',
    'reply_date' => 0,
]; // Story data

$errors = [
    'warning' => '',
    'note_type' => '',
    'to_id' => '',
    'to_name' => '',
    'from_id' => '',
    'from_name' => '',
    'family_id' => '',
    'request' => '',
    'allow' => '',
    'request_date' => '',
    'reply_date' => '',
];

$to_id = (int) $id;
$from_id = $cms->getSession()->id;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // If form was posted
    $note['to_id'] = intval($_POST['to_member']); // Get to member id
    $note['from_id'] = intval($_POST['from_member']); // Get from member id
    $note['to_name'] = $_POST['to_membername']; // Get to name
    $note['from_name'] = $_POST['from_membername']; // Get from name
    $note['note_type'] = intval($_POST['noteid']); // Get notetype
    $note['request'] = $_POST['request']; // Get request
    $note['family_id'] = intval($_POST['family_id']); // Get family id
    $note['allow'] = intval($_POST['allow']); // Get allow

    // Validate form data
    $errors['request'] = Validate::isText($note['request'], 0, 1000)
        ? ''
        : 'request should be between 0 and 1000 characters';

    $invalid = implode($errors); // Join any error messages
    if ($invalid) {
        // If validation failed
        $errors['warning'] = 'Please correct form errors'; // Store a warning
    } else {
        // Otherwise
        $result = $cms->getNewnote()->create($note); // Create a new request notfication
        if ($result === false) {
            // If result is false
            $errors['warning'] = 'Please correct form errors';

            // Store a warning
        } else {
        } // Otherwise

        redirect('admin/newnote/', ['success' => 'Family updated']); // Redirect with message
    }
}

$to_member = $cms->getMember()->get($to_id); // Get member data

$from_member = $cms->getMember()->get($from_id);

if (!$from_member) {
    // If array is empty
    include APP_ROOT . '/src/pages/page-not-found.php'; // Page not found
}
$notes = $cms->getNewnote()->get($from_id);
// $notes2 = $cms->getNewnote()->get($to_id);

$notetype = 1;

$to_membername = $to_member['forename'] . ' ' . $to_member['surname'];
$from_membername = $from_member['forename'] . ' ' . $from_member['surname'];

// Authoritative IDs (session + validated target)
$data['to_member'] = (int) ($targetMember['id'] ?? $targetId); // target
$data['from_member'] = (int) $_SESSION['id']; // viewer (session ONLY)

// Display names (assumes $to_membername / $from_membername already built from $targetMember + $fromMember)
$data['to_membername'] = $to_membername;
$data['from_membername'] = $from_membername;

// Static fields
$data['request'] = 'Follow Request';
$data['description'] = 'Request to Follow';

// Family/account IDs (authoritative)
$data['to_family_id'] = (int) ($targetMember['account_id'] ?? 0);
$data['family_id'] = (int) ($from_member['account_id'] ?? 0);

// Allow flag (safe default)
$data['allow'] = (int) ($note['allow'] ?? 0);

// Success message (fix key)
$data['success'] = $_GET['success'] ?? '';

// Website scope comes from session website (NOT from $from_member)
$data['website'] = $cms->getWebsite()->getById((int) $_SESSION['website']);

echo $twig->render('newnote.html', $data); // Render Twig template
