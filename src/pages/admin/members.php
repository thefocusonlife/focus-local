<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/security/guard.php';
guardMember();
// Use the existing runtime guard you already have
is_admin($session->role);

$websiteId = (int) ($_SESSION['website'] ?? 1);
if ($websiteId <= 0) {
    $websiteId = 1;
    $_SESSION['website'] = 1;
}

$data = [];

if (!empty($_SESSION['flash_failure'])) {
    $data['flash_failure'] = $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

if (!empty($_SESSION['flash_success'])) {
    $data['flash_success'] = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

$data['success'] = $_GET['success'] ?? null;
$data['failure'] = $_GET['failure'] ?? null;
$data['members'] = $cms->getMember()->getAll2($websiteId);
$data['website'] = $cms->getWebsite()->get($websiteId);
echo $twig->render('admin/members.html', $data);
