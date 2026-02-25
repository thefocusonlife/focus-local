<?php
declare(strict_types=1); // Use strict types
use PhpBook\Validate\Validate; // Import Validate class
require_once APP_ROOT . '/src/security/guard.php';
guardPublic();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// If you have guard/bootstrap includes, keep them here.

// Session may not be initialized on first request after splash.
$sessionWebsiteId = isset($_SESSION['website']) ? (int) $_SESSION['website'] : 0;

// If select-website needs the current website record for display, only load it when valid.
$currentWebsite = [];
if ($sessionWebsiteId > 0) {
    $currentWebsite = $cms->getWebsite()->get($sessionWebsiteId); // will be [] if not found after fix #2
}

// ... then render the selector using $currentWebsite optionally.
// IMPORTANT: Do not call get(0).

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $w = (int) ($_POST['website'] ?? 0);
    if ($w <= 0) {
        redirect('index/99999', ['failure' => 'No website selected.']);
        exit();
    }

    $website = $cms->getWebsite()->get($w);
    if (empty($website) || empty($website['id'])) {
        redirect('index/99999', ['failure' => 'Website not found.']);
        exit();
    }

    // Enforce access rule: if non-members allowed, switch as Guest and go there
    if ((int) ($website['non_members'] ?? 0) === 1) {
        $cms->getSession()->resetToGuest((int) $website['id']);
        redirect('index/' . (int) $website['id']);
        exit();
    }

    // Members-only site
    redirect('index/99999', [
        'failure' => 'You must register as a member to access GET FOCUSED websites.
Click the "Register" link on top of this page to see pricing.',
    ]);
    exit();
}

$websites = $cms->getWebsite()->getAllActive();
$data['source'] = 'select-website';
$data['websites'] = $websites;

$sessionWebsiteId = (int) ($_SESSION['website'] ?? 0);

// 1) What the page considers “current website” (may be none in fresh guest)
$data['website'] = $sessionWebsiteId > 0 ? $cms->getWebsite()->get($sessionWebsiteId) : [];

// 2) What the header can use to render a logo (fallback to first website)
if (!empty($data['website'])) {
    $data['headerWebsite'] = $data['website'];
} else {
    $fallbackId = (int) ($websites[0]['id'] ?? 0); // or hardcode 1 if that’s your “master”
    $data['headerWebsite'] = $fallbackId > 0 ? $cms->getWebsite()->get($fallbackId) : [];
}

echo $twig->render('select-website.html', $data);
