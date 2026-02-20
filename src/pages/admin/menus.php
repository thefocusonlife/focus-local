<?php
declare(strict_types=1);

error_log('MENU-DELETE SESSION ID: ' . session_id());

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Add this:
$data = $data ?? [];

if (!empty($_SESSION['flash_failure'])) {
    $data['flash_failure'] = $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

if (!empty($_SESSION['flash_success'])) {
    $data['flash_success'] = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

error_log('MENUS PAGE SESSION ID: ' . session_id());
error_log('MENUS PAGE SESSION CONTENTS: ' . print_r($_SESSION, true));

require_once APP_ROOT . '/src/security/guard.php';

require_once APP_ROOT . '/src/security/guard.php';

guardAdmin();

$websiteId = (int) ($_SESSION['website'] ?? 1);
if ($websiteId <= 0) {
    $websiteId = 1;
}
$website = $cms->getWebsite()->getById($websiteId);
if (!$website || !isset($website['id'])) {
    $websiteId = 1;
    $_SESSION['website'] = 1;
    $website = $cms->getWebsite()->getById(1);
}

$member = $cms->getMember()->get((int) $_SESSION['id']);
$memberWebsiteId = (int) ($member['website'] ?? 0);

if ($memberWebsiteId > 0 && $memberWebsiteId !== $websiteId) {
    // hard reset to the member's own website
    $websiteId = $memberWebsiteId;
    $_SESSION['website'] = $websiteId;
    $data['website'] = $cms->getWebsite()->getById($websiteId);
}

$data['website'] = $website;
$data['success'] = $_GET['success'] ?? null;
$data['failure'] = $_GET['failure'] ?? null;

// Your current Uber rule: id==1 sees all menus
// 3) Menu loading — Uber vs normal admin
if ((int) ($_SESSION['id'] ?? 0) === 1) {
    // Uber admin:
    // still global authority, but default view scoped to selected website
    $data['menus'] = $cms->getMenu()->getAll();
    //$data['menus'] = $cms->getMenu()->getAllByWebsite($websiteId);
} else {
    // Normal admin: website + account scoped
    $member = $cms->getMember()->get((int) $_SESSION['id']);
    $mem = (int) ($member['account_id'] ?? 0);

    $data['menus'] = $cms->getMenu()->getAll2($websiteId, $mem);
}
if (!empty($data['flash_failure'])) {
    error_log('FLASH FAILURE present: ' . $data['flash_failure']);
} else {
    error_log('NO FLASH FAILURE present');
}

echo $twig->render('admin/menus.html', $data);
