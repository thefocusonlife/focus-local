<?php
declare(strict_types=1);
$allowedLocations = ['bend', 'redmond', 'sisters'];

$location = strtolower((string) ($_GET['location'] ?? 'redmond'));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'redmond';
}
require_once APP_ROOT . '/src/security/guard.php';

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? ''));

if ($viewerId <= 0 || $role === 'guest') {
    $_SESSION['return_to'] = $_SERVER['REQUEST_URI'] ?? '/bicycle-community?website=44';
    $_SESSION['flash_failure'] = 'You must be logged in to add community content.';
    redirect('login');
    exit();
}

$data = [];

$websiteId = (int) ($_GET['website'] ?? ($_SESSION['website'] ?? 44));
if ($websiteId !== 44) {
    $websiteId = 44;
}

$id = (int) ($_GET['id'] ?? 0);

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
$data['location'] = $location;
$data['locationName'] = ucfirst($location);
if ($id > 0) {
    $sql = "
        SELECT *
        FROM bicycle_community
        WHERE id = :id
          AND website_id = :website_id
        LIMIT 1
    ";

    $existing = $cms
        ->getDb()
        ->runSql($sql, [
            'id' => $id,
            'website_id' => $websiteId,
        ])
        ->fetch();

    if ($existing) {
        $data['community'] = $existing;
    } else {
        $_SESSION['flash_failure'] = 'Community item not found.';
        redirect('index/44');
        exit();
    }
}

echo $twig->render('bicycle-community-form.html', $data);
