<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';

// Admin boundary: require login + allow admin, allow uber too
guardAdmin();

// Website context (validate)
$websiteId = (int) ($_SESSION['website'] ?? 0);
if ($websiteId <= 0) {
    $websiteId = 1;
    $_SESSION['website'] = 1;
}

$website = $cms->getWebsite()->getById($websiteId);
if (!$website || !isset($website['id'])) {
    // If website context is invalid, recover to 1 (safe public default)
    $websiteId = 1;
    $_SESSION['website'] = 1;
    $website = $cms->getWebsite()->getById(1);
}

// Capability flag for UI only (server-side enforcement still required in each controller)
$role = (string) ($_SESSION['role'] ?? 'guest');
$data = [];
$data['isUber'] = $role === 'uber';
$data['website'] = $website;

// Dashboard metrics (counts)
$data['story_count'] = $cms->getStory()->count();

$data['menu_count'] = $cms->getMenu()->count();

$data['member_count'] = $cms->getMember()->count();
$data['website_count'] = $cms->getWebsite()->count();

echo $twig->render('admin/index.html', $data);
