<?php

declare(strict_types=1);

$websiteId = 51;

$website = $cms->getWebsite()->get($websiteId);

if (!$website) {
    $_SESSION['flash_failure'] = 'Central Oregon Chess website not found.';
    redirect('index/51');
    exit();
}

$data = [
    'website' => $website,
    'websiteId' => $websiteId,
    'title' => 'Central Oregon Chess Resources',
];

echo $twig->render('chess-resources.html', $data);
return;
