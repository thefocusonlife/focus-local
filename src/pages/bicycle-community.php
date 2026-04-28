<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? ''));

if ($viewerId <= 0 || $role === 'guest') {
    $_SESSION['return_to'] = $_SERVER['REQUEST_URI'] ?? '/bicycle-community?website=44';
    $_SESSION['flash_failure'] = 'You must be logged in to add community content.';
    redirect('login');
    exit();
}

$websiteId = (int) ($_GET['website'] ?? ($_SESSION['website'] ?? 44));
if ($websiteId !== 44) {
    $websiteId = 44;
}

$data = [];
$data['website'] = $cms->getWebsite()->getById($websiteId);
$data['websiteId'] = $websiteId;

$data['community'] = [
    'id' => 0,
    'title' => '',
    'content_type' => 'story',
    'url' => '',
    'summary' => '',
    'content' => '',
    'status' => 'published',
];
$websiteId = 44;

echo $twig->render('bicycle-community-form.html', $data);
