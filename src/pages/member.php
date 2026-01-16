<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/security/guard.php';

include APP_ROOT . '/src/pages/menu-path.php';
$errors = [];
$data = [];

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

$mem = intval($member['account_id']);
if (!$_SESSION['id']) {
    $website = $cms->getWebsite()->getById($member['website']);
} else {
    $website = $cms->getWebsite()->getByID($member['website']);
}
if (empty($_SESSION['id'])) {
} else {
    $member = $cms->getMember()->get(intval($parts[1]));
    $mem = intval($member['account_id']);
}
$data['success'] = $_GET['success'] ?? null; // Check for success message
$data['failure'] = $_GET['failure'] ?? null; // Check for failure message
// Get story summaries
$data['navigation'] = $cms->getMenu()->getAll2($member['website'], $mem); // Get menus
$data['member'] = $member; // Member data
$data['website'] = $cms->getWebsite()->getById($member['website']);
$resolvedSorttype = (int) ($member['sorttype'] ?? 9);
$data['sorttype'] = $cms->getSorttype()->get($resolvedSorttype);

if (isset($id) and $id == 2) {
    $data['stories'] = $cms->getStory()->getAll(true, null, null); // Get all stories for Uber
    //$cms->getSession()->create(0,1);
} elseif (!empty($parts[2]) and $parts[2] == 1) {
    //    $cms->getSession()->get;
    $data['stories'] = $cms->getStory()->getAll3($website['id'], true, null, null);
}

//$data['member']  = null;
//get all stories for member's website
else {
    $id = intval($parts[1]);
    $data['stories'] = $cms->getStory()->getAll2($website['id'], 0, null, $id);
}

// Default Sort target for global member page (Focus menu id=2)
if (empty($data['sort_menu_id'])) {
    $data['sort_menu_id'] = 2;
}

echo $twig->render('member.html', $data); // Render Twig template
