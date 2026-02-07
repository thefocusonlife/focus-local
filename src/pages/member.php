<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/security/guard.php';
include APP_ROOT . '/src/pages/menu-path.php';

$errors = [];
$data = [];

// --- Debug (keep if you still want it) ---
error_log(
    '[member.php] session.id=' .
        ($_SESSION['id'] ?? 'NA') .
        ' session.account_id=' .
        ($_SESSION['account_id'] ?? 'NA') .
        ' session.website=' .
        ($_SESSION['website'] ?? 'NA'),
);

// ------------------------------------------------------------
// Guard: require login (member page is not for guests)
// ------------------------------------------------------------
$viewerId = (int) ($_SESSION['id'] ?? 0);
if ($viewerId <= 0) {
    redirect('login');
    exit();
}

$viewer = $cms->getMember()->get($viewerId);
if (!$viewer || empty($viewer['id'])) {
    redirect('page-not-found/');
    exit();
}

// ------------------------------------------------------------
// Follow logic: ownerId = account_id (if set) else viewerId
// ------------------------------------------------------------
$ownerId = (int) ($viewer['account_id'] ?? 0);
if ($ownerId <= 0) {
    $ownerId = $viewerId;
}

// Load the “owner” member record (the one we’re following)
$ownerMember = $cms->getMember()->get($ownerId);
if (!$ownerMember || empty($ownerMember['id'])) {
    // If account_id is stale/bad, fall back to self
    $ownerId = $viewerId;
    $ownerMember = $viewer;
}

// Website scope: use owner’s website for menus + stories.
// (Keeps follow consistent: you follow their content + nav.)
$websiteId = (int) ($ownerMember['website'] ?? 0);
if ($websiteId <= 0) {
    $websiteId = (int) ($_SESSION['website'] ?? 1);
    if ($websiteId <= 0) {
        $websiteId = 1;
    }
}

$website = $cms->getWebsite()->getById($websiteId);
if (!$website || !isset($website['id'])) {
    $websiteId = 1;
    $website = $cms->getWebsite()->getById(1);
}
$_SESSION['website'] = $websiteId;

// ------------------------------------------------------------
// Page data for template
// ------------------------------------------------------------
$data['failure'] = $_GET['failure'] ?? null;
$data['member'] = $viewer; // viewer identity (who is logged in)
$data['website'] = $website; // resolved website (owner’s website)
$data['follow_owner'] = $ownerMember; // optional: lets template show “Following X”
$data['current_path'] = "member/$ownerId"; // or viewerId if that’s your route
$data['current_menu_id'] = null;

// ------------------------------------------------------------
// Sort preference (member page = "no menu selected" => global override)
// Precedence: session global override -> viewer preference -> website default -> fallback
// ------------------------------------------------------------
$preferredSorttypeId = (int) ($_SESSION['sort_override_global'][$websiteId] ?? 0);

if ($preferredSorttypeId > 0) {
    $resolvedSorttypeId = $preferredSorttypeId;
} else {
    $resolvedSorttypeId = (int) ($viewer['sorttype'] ?? 0);
    if ($resolvedSorttypeId <= 0) {
        $resolvedSorttypeId = (int) ($website['sorttype'] ?? 0);
    }
    if ($resolvedSorttypeId <= 0) {
        $resolvedSorttypeId = 1; // final fallback (Newest if that's your id=1)
    }
}

$data['sorttype'] = $cms->getSorttype()->get($resolvedSorttypeId);

// Navigation (menus) based on owner + owner website
$data['navigation'] = $cms->getMenu()->getAll2($websiteId, $ownerId);

// ------------------------------------------------------------
// Stories
// Keep your original branching:
//  - viewerId === 1 => uber gets all stories
//  - parts[2] == 1 => website-wide stories
//  - else => ownerId stories (follow)
// ------------------------------------------------------------
if ($viewerId === 1) {
    $data['stories'] = $cms->getStory()->getAll(true, null, null); // Uber only
} elseif (!empty($parts[2]) && (int) $parts[2] === 1) {
    $data['stories'] = $cms->getStory()->getAll3($websiteId, true, null, null);
} else {
    $sorttypeId = (int) ($_SESSION['sort_override_global'][$websiteId] ?? 0);

    $data['stories'] = $cms
        ->getStory()
        ->getAll2($websiteId, 0, null, $ownerId, 150, $sorttypeId > 0 ? $sorttypeId : null);
}

// Default sort menu id (Focus menu id=2)
$data['sort_menu_id'] = (int) ($data['sort_menu_id'] ?? 2);

error_log(
    '[member.php BEFORE RENDER] viewer.id=' .
        $viewerId .
        ' viewer.account_id=' .
        (int) ($viewer['account_id'] ?? 0) .
        ' owner.id=' .
        $ownerId .
        ' website.id=' .
        $websiteId,
);

echo $twig->render('member.html', $data);
