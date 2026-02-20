<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/security/guard.php';
guardMember();
// ------------------------------------------------------------
// newnote-gate.php
// Creates a one-time intent token, then redirects to newnote.
// ------------------------------------------------------------

// Robust parse: /newnote-gate/{targetId}
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

$targetId = 0;
if (preg_match('~/newnote-gate/(\d+)$~', $uriPath, $m)) {
    $targetId = (int) $m[1];
}

if ($targetId <= 0) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

// Logged-in viewer ONLY
$viewerId = (int) ($_SESSION['id'] ?? 0);
if ($viewerId <= 0) {
    header('Location: ' . DOC_ROOT . '/login');
    exit();
}

// Current website context (used only for sanity / logging)
$sessionWebsiteId = (int) ($_SESSION['website'] ?? 0);
if ($sessionWebsiteId <= 0) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

// Load target member
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

// Prevent request-to-self
if ($targetId === $viewerId) {
    header('Location: ' . DOC_ROOT . '/member/' . $viewerId);
    exit();
}

// Ensure intent store exists
if (!isset($_SESSION['newnote_intents']) || !is_array($_SESSION['newnote_intents'])) {
    $_SESSION['newnote_intents'] = [];
}

// Prune expired intents
$now = time();
foreach ($_SESSION['newnote_intents'] as $key => $it) {
    if (!is_array($it) || (int) ($it['exp'] ?? 0) < $now) {
        unset($_SESSION['newnote_intents'][$key]);
    }
}

// ✅ Mint the one-time intent token
$k = bin2hex(random_bytes(16));
$_SESSION['newnote_intents'][$k] = [
    'viewer_id' => $viewerId,
    'target_id' => $targetId,
    'target_website' => $targetWebsiteId,
    'exp' => $now + 300, // 5 minutes
];

// ✅ Redirect into newnote WITH token
header('Location: ' . DOC_ROOT . '/newnote/' . $targetId . '?k=' . $k);
exit();
