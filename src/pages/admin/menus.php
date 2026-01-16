<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/security/guard.php';

is_admin($session->role);

$websiteId = (int) ($_SESSION['website'] ?? 1);
if ($websiteId <= 0) {
    $websiteId = 1;
    $_SESSION['website'] = 1;
}

$data = [];
$data['success'] = $_GET['success'] ?? null;
$data['failure'] = $_GET['failure'] ?? null;

// Your current Uber rule: id==1 sees all menus
if ((int) ($_SESSION['id'] ?? 0) === 1) {
    $data['menus'] = $cms->getMenu()->getAll();
    $data['website'] = $cms->getWebsite()->getById($websiteId);
} else {
    $member = $cms->getMember()->get((int) $_SESSION['id']);
    $mem = (int) ($member['account_id'] ?? 0);

    $website = $cms->getWebsite()->getById($websiteId);
    $data['website'] = $website;

    $data['menus'] = $cms->getMenu()->getAll2($websiteId, $mem);
}

echo $twig->render('admin/menus.html', $data);
