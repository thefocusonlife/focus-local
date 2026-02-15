<?php
declare(strict_types=1);
include APP_ROOT . '/src/pages/menu-path.php'; // menu-path include
require_once APP_ROOT . '/src/tenancy/website_context.php';

[$websiteId, $website] = resolveWebsiteId(
    $cms,
    $parts ?? [],
    $_SESSION,
    $_COOKIE,
    $_GET,
    null,
    1, // default to 1 for public pages (adjust if you prefer 0 + redirect)
    'tfol_tid',
    true,
);

// Resolve website id safely
$websiteId = (int) ($id ?? 0);
if ($websiteId <= 0) {
    $websiteId = (int) ($_SESSION['website'] ?? 1);
}
if ($websiteId <= 0) {
    $websiteId = 1;
}

// Load website (fallback to 1 if invalid)
$website = $cms->getWebsite()->getById($websiteId);
if (!$website || !isset($website['id'])) {
    $websiteId = 1;
    $website = $cms->getWebsite()->getById(1);
}

// Persist website choice (OK for guests)
$_SESSION['website'] = (int) $website['id'];
// Provide consistent globals to templates/pages
if (!isset($data) || !is_array($data)) {
    $data = [];
}

// Logged-in member (read-only)
$member = null;
$mem = 0;

if (!empty($_SESSION['id'])) {
    $member = $cms->getMember()->get((int) $_SESSION['id']);
    $mem = (int) ($member['account_id'] ?? 0);
}
$data['session'] = $_SESSION;
$data['website'] = $website;
if (!empty($member)) {
    $data['member'] = $member;
}
// Ensure templates always receive current session + website
if (!isset($data) || !is_array($data)) {
    $data = [];
}
$data['session'] = $_SESSION;
$data['website'] = $website;
if (!empty($member) && is_array($member)) {
    $data['member'] = $member;
}

// IMPORTANT: do NOT call $cms->getSession()->create() here.
// _include.php should never create or modify login sessions.
