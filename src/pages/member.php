<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/security/guard.php';
include APP_ROOT . '/src/pages/menu-path.php';
guardMember();
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
// Follow logic: ownerId = viewer.account_id (family context)
// BUT: do NOT change tenant/website based on owner.
// Tenant is session-selected (or resolved elsewhere), not follow-selected.
// ------------------------------------------------------------

// Tenant context (single source of truth)
$websiteId = (int) ($_SESSION['website'] ?? 0);
if ($websiteId <= 0) {
    $websiteId = (int) ($viewer['website'] ?? 0);
}
if ($websiteId <= 0) {
    $websiteId = 1;
}

// Follow/owner context (used for content), default to self
$ownerId = (int) ($viewer['account_id'] ?? 0);
if ($ownerId <= 0) {
    $ownerId = $viewerId;
}

$ownerMember = $cms->getMember()->get($ownerId);
if (!$ownerMember || empty($ownerMember['id'])) {
    $ownerId = $viewerId;
    $ownerMember = $viewer;
}

// HARD TENANT GUARD: never allow follow to switch websites here
if ((int) ($ownerMember['website'] ?? 0) !== $websiteId) {
    // If the follow points cross-tenant, ignore it for this page
    $ownerId = $viewerId;
    $ownerMember = $viewer;
}

// Load website from tenant context
$website = $cms->getWebsite()->getById($websiteId);
if (!$website || !isset($website['id'])) {
    $websiteId = 1;
    $_SESSION['website'] = 1;
    $website = $cms->getWebsite()->getById(1);
} else {
    $_SESSION['website'] = $websiteId; // keep consistent
}

// ------------------------------------------------------------
// Page data for template
// ------------------------------------------------------------
$data['failure'] = $_GET['failure'] ?? null;
$data['member'] = $viewer; // viewer identity (who is logged in)
$data['website'] = $website; // resolved website (owner’s website)
$data['follow_owner'] = $ownerMember; // optional: lets template show “Following X”
$data['current_path'] = "member/$viewerId"; // or viewerId if that’s your route
$data['current_menu_id'] = null;
$data['follow_owner'] = $ownerMember;

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
    $websiteId = (int) ($_SESSION['website'] ?? 0);
    $data['stories'] = $cms->getStory()->getAll($websiteId, null, null);
} elseif (!empty($parts[2]) && (int) $parts[2] === 1) {
    $data['stories'] = $cms->getStory()->getAll3($websiteId, true, null, null);
} else {
    $sorttypeId = (int) ($_SESSION['sort_override_global'][$websiteId] ?? 0);

    $data['stories'] = $cms
        ->getStory()
        ->getAll2($websiteId, 0, null, $ownerId, 150, $sorttypeId > 0 ? $sorttypeId : null);
}

/// Default sort menu id (Focus menu id=2)
if (empty($data['sort_menu_id'])) {
    $currentWebsiteId =
        (int) ($data['website']['id'] ??
            ($websiteId ?? ($_SESSION['website'] ?? ($_SESSION['websiteid'] ?? 1))));

    $sortMenuId = 0;

    $menus = $cms->getMenu()->getAll2($currentWebsiteId, 1);
    foreach ($menus as $menu) {
        $name = strtolower(trim((string) ($menu['name'] ?? '')));
        if ($name === 'focus' || $name === 'sort') {
            $sortMenuId = (int) ($menu['id'] ?? 0);
            break;
        }
    }

    if ($sortMenuId <= 0 && $currentWebsiteId === 1) {
        $sortMenuId = 2;
    }

    $data['sort_menu_id'] = $sortMenuId;
}

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
