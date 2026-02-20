<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/security/guard.php';
require_once APP_ROOT . '/src/security/redirects.php'; // if you have it
include APP_ROOT . '/src/pages/menu-path.php'; // if follow uses $id routing

guardMember();

// ---- Basic boundary: must be logged in (member/admin/uber), not guest ----
if (empty($_SESSION['id']) || ($_SESSION['role'] ?? 'guest') === 'guest') {
    // keep it consistent with your site patterns:
    redirect('login/' . (int) ($_SESSION['website'] ?? 1));
    exit();
}

//--------------------------------
// owner guard
//--------------------------------

$viewerId = (int) ($_SESSION['id'] ?? 0);

$ownerId = (int) ($parts[2] ?? 0);
if ($ownerId <= 0) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

if ($viewerId != $ownerId) {
    // optional: treat as no-op or failure message
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

// Confirm target exists
$owner = $cms->getMember()->get($ownerId);
if (!$owner || empty($owner['id'])) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

$id = $cms->getSession()->id;
//user's id from session                              // Use strict types
// ----------------------------
// Resolve website context
// ----------------------------
$websiteId = (int) ($parts[1] ?? 0);
if ($websiteId <= 0) {
    $websiteId = (int) ($_SESSION['website'] ?? 1);
}
$_SESSION['website'] = $websiteId;

// Fetch website using the method that works in select-website.php
$website = $cms->getWebsite()->get($websiteId);
/*
if (!$id) {
    // If no valid id
    include APP_ROOT . '/src/pages/page-not-found.php'; // Page not found
}
*/
$member = $cms->getMember()->get($_SESSION['id']); // Get member data
if (!$member) {
    // If array is empty
    include APP_ROOT . '/src/pages/page-not-found.php'; // Page not found
}
$members = $cms->getMember()->getAll();
//$follows = $cms->getFollow()->getAll();
$notes = $cms->getNote()->getAll($_SESSION['website'], $_SESSION['id']);

$data['navigation'] = $cms->getMenu()->getAll2($_SESSION['website'], $_SESSION['id']); // Get menus
$data['member'] = $member;
$data['members'] = $members;
$data['notes'] = $notes;
$data['success'] = $_GET['success'] ?? ''; // Success message if present
$data['website'] = $website;
echo $twig->render('follow-request.html', $data); // Render Twig template
