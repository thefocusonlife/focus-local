<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/security/guard.php';

include APP_ROOT . '/src/pages/menu-path.php';
$errors = [];
$data = [];

error_log(
    '[POST-LOGIN] session.id=' .
        ($_SESSION['id'] ?? 'NA') .
        ' session.account_id=' .
        ($_SESSION['account_id'] ?? 'NA') .
        ' session.website=' .
        ($_SESSION['website'] ?? 'NA'),
);

// 1) Require login + role
//guardRequireLogin($cms);
//guardRequireRole($cms, ['admin', 'uber']); // whatever your canonical admin roles are

// 2) Resolve website scope (admin actions must be scoped)
$websiteId = (int) ($_SESSION['website'] ?? 1);
if ($websiteId <= 0) {
    $websiteId = 1;
}

// Optional: ensure website exists
$website = $cms->getWebsite()->getById($websiteId);
if (!$website || !isset($website['id'])) {
    $websiteId = 1;
    $_SESSION['website'] = 1;
    $website = $cms->getWebsite()->getById(1);
}

$menuId = (int) ($menuId ?? 0);
if ($menuId <= 0) {
    // Fallback: choose a sensible default menu id for this member/website
    // (see Option B below for how to do this properly)
    $menuId = 1;
}

$memberId = (int) ($parts[1] ?? 0);

// If someone hits /member/0 (or any non-positive id), treat as Guest and bounce

if ($memberId <= 0) {
    $websiteId = (int) ($_SESSION['website'] ?? 0);
    if ($websiteId > 0) {
        redirect('index/' . $websiteId);
        exit();
    }
    redirect('index/1'); // last-resort fallback
    exit();
}

$member = $cms->getMember()->get(intval($parts[1]));

$member = $cms->getMember()->get($memberId);
if (!$member || empty($member['id'])) {
    redirect('index/99999', ['failure' => 'Member not found.']);
    exit();
}
$viewerId = (int) ($_SESSION['id'] ?? 0);
if ($viewerId <= 0) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

$viewer = $cms->getMember()->get($viewerId);
if (!$viewer) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

// For now (today): member page shows your own profile + your own stories
$targetMemberId = $viewerId;

// Navigation ownership (keep your existing rule if you want)
$mem = (int) ($viewer['account_id'] ?? $viewerId);

// Template uses viewer

$data['navigation'] = $cms->getMenu()->getAll2((int) $viewer['website'], $mem);

$mem = intval($member['account_id']);
$websiteId = (int) ($viewer['website'] ?? 1);
if ($websiteId <= 0) {
    $websiteId = 1;
}

$website = $cms->getWebsite()->getById($websiteId);
if (!$website) {
    $websiteId = 1;
    $website = $cms->getWebsite()->getById(1);
}
$_SESSION['website'] = $websiteId;

$data['success'] = $_GET['success'] ?? null; // Check for success message
$data['failure'] = $_GET['failure'] ?? null; // Check for failure message
// Get story summaries
$data['navigation'] = $cms->getMenu()->getAll2($member['website'], $mem); // Get menus
$data['member'] = $viewer; // Member data
$data['website'] = $cms->getWebsite()->getById($member['website']);
$resolvedSorttype = (int) ($member['sorttype'] ?? 9);
$data['sorttype'] = $cms->getSorttype()->get($resolvedSorttype);

$viewerId = (int) ($_SESSION['id'] ?? 0);

if ($viewerId === 1) {
    $data['stories'] = $cms->getStory()->getAll(true, null, null); // Uber only
} elseif (!empty($parts[2]) and $parts[2] == 1) {
    $data['stories'] = $cms->getStory()->getAll3($website['id'], true, null, null);
} else {
    $targetMemberId = $viewerId; // default: show logged-in member's own stories
    $data['stories'] = $cms->getStory()->getAll2($website['id'], 0, null, $targetMemberId);
}

// Default Sort target for global member page (Focus menu id=2)
if (empty($data['sort_menu_id'])) {
    $data['sort_menu_id'] = 2;
}
error_log(
    '[member.php BEFORE RENDER] session.id=' .
        ($_SESSION['id'] ?? 'NA') .
        ' data.member.id=' .
        (int) ($data['member']['id'] ?? -1) .
        ' data.member.account_id=' .
        (int) ($data['member']['account_id'] ?? -1),
);

echo $twig->render('member.html', $data); // Render Twig template
