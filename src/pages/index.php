<?php
declare(strict_types=1);
error_log(
    '[INDEX] ' . ($_SERVER['REQUEST_METHOD'] ?? '?') . ' ' . ($_SERVER['REQUEST_URI'] ?? '?'),
);

// index/{websiteId}
// $id is coming from menu-path.php routing
file_put_contents(
    '/tmp/tfol-route.log',
    date('c') .
        " ROUTE page={$page} id=" .
        var_export($id, true) .
        ' parts=' .
        (isset($parts) ? json_encode($parts) : 'NA') .
        "\n",
    FILE_APPEND,
);

$data = [];
$guidetext = '';

// 1) Resolve website id (guest default = 1)
$websiteId = (int) ($id ?? 0);

if ($websiteId <= 0) {
    $websiteId = (int) ($_SESSION['website'] ?? 1);
}
if ($websiteId <= 0) {
    $websiteId = 1;
}

// 2) Load website (fallback to 1 if invalid)
$website = $cms->getWebsite()->getById($websiteId);
if (!$website || !isset($website['id'])) {
    $websiteId = 1;
    $website = $cms->getWebsite()->getById(1);
}

// 3) Determine logged-in member (or null)
$member = null;
$memAccountId = 0;

if (!empty($_SESSION['id'])) {
    $member = $cms->getMember()->get((int) $_SESSION['id']);
    if ($member && isset($member['account_id'])) {
        $memAccountId = (int) $member['account_id'];
    }
}
$accountId = (int) ($_SESSION['id'] ?? 0);

// Viewer vs menu owner:
// - viewerId is who is logged in (June=182)
// - menuOwnerId is whose menus we are browsing (Geoff=3 via your account_id hack)
// - guests should browse UberAdmin (1)
$viewerId = (int) ($_SESSION['id'] ?? 0);

if ($viewerId > 0) {
    $menuOwnerId = (int) ($accountId > 0 ? $accountId : $viewerId);
} else {
    $menuOwnerId = 1; // Guest: UberAdmin menus
}

// Visibility filter:
// - owner sees owner-view
// - everyone else (including guests) sees public-view
$visibilityViewerId = $viewerId > 0 && $viewerId === $menuOwnerId ? $viewerId : null;

// Menu owner for navigation: guests should see UberAdmin menus
$viewerId = (int) ($_SESSION['id'] ?? 0);
$menuOwnerId = 0;

if ($viewerId > 0) {
    // Your existing shared-menu behavior: account_id points at the "menu owner" member id
    $menuOwnerId = (int) ($memAccountId > 0 ? $memAccountId : $viewerId);
} else {
    // Guest: show UberAdmin menus
    $menuOwnerId = 1;
}

// 4) Membership gating for websites that require membership
// non_members: allow guests if set (based on your existing logic)
if ($websiteId > 1 && empty($_SESSION['id']) && empty($website['non_members'])) {
    $msg =
        'WARNING: You must be a registered member in order to access a GET FOCUSED website.  Click the Register link above to view subscription plans OR click the Refresh link for more photos on this page. ** Note: You may access websites marked as FREE-Access without a membership.';
    $data['failure'] = $msg;

    $websiteId = 1;
    $website = $cms->getWebsite()->getById(1);
}

// ------------------------------------------------------
// TILE(1,1) MESSAGE OVERRIDE (TFOL UX)
// If there is a one-time flash message (e.g., Registration),
// show it in the TILE slot using the existing pink "failure"
// styling. Otherwise show the normal QuickGuide text.
// NOTE: This intentionally uses $data['failure'] so it lands
// in tile(1,1) per index.html grid behavior.
// ------------------------------------------------------
if (!empty($_SESSION['flash_success'])) {
    $data['failure'] = (string) $_SESSION['flash_success']; // pink tile
    unset($_SESSION['flash_success']);
} elseif (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure']; // pink tile
    unset($_SESSION['flash_failure']);
} else {
    // Normal behavior: QuickGuide text into success tile
    $guidetext = $cms->getQuickguide()->getAll();
    if (!empty($guidetext) && !empty($guidetext[0])) {
        $data['success'] = implode('', $guidetext[0]);
    }
}

/*
// 4b) One-time flash alerts (do NOT collide with QuickGuide 'success')
$data['flash_success'] = $_SESSION['flash_success'] ?? '';
$data['flash_failure'] = $_SESSION['flash_failure'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_failure']);

// 5) Quickguide success message (your existing behavior)
$guidetext = $cms->getQuickguide()->getAll();
if (!empty($guidetext) && !empty($guidetext[0])) {
    $data['success'] = implode('', $guidetext[0]);
}
*/
// 6) Stories
$data['stories'] = $cms->getStory()->getAll3((int) $website['id'], true, null, null, 100);

// 7) Navigation (guest account_id = 0)
$data['navigation'] = $cms->getMenu()->getAll2((int) $website['id'], $menuOwnerId);

// 8) Data for template
$data['website'] = $website;
if ($member) {
    $data['member'] = $member;
}
// 7) Navigation (guest account_id = 0)
$data['navigation'] = $cms->getMenu()->getAll2((int) $website['id'], $menuOwnerId);

// 7.5) Default Sort target for global pages (Focus menu id=2)
if (empty($data['sort_menu_id'])) {
    $data['sort_menu_id'] = 2;
}

// 8) Data for template
$data['website'] = $website;
if ($member) {
    $data['member'] = $member;
}

echo $twig->render('index.html', $data);
return;
