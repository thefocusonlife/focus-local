<?php

declare(strict_types=1);

$websiteId = 44;

$website = $cms->getWebsite()->get($websiteId);

if (!$website) {
    $_SESSION['flash_failure'] = 'Central Oregon Bicycle Community website not found.';
    redirect('index/44');
    exit();
}

$allowedLocations = [
    'bend',
    'redmond',
    'sisters',
    'madras',
    'prineville',
    'lapine',
    'centraloregon',
];

$location = strtolower((string) ($_GET['location'] ?? 'redmond'));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'redmond';
}

$data = [
    'website' => $website,
    'websiteId' => $websiteId,
    'title' => 'Central Oregon Bicycle Resources',
    'location' => $location,
];

echo $twig->render('bicycle-resources.html', $data);
return;
