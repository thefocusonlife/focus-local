<?php
declare(strict_types=1);
$websiteId = 1;
$website = $cms->getWebsite()->get($websiteId);

$data = [
    'website' => $website,
    'website_id' => $websiteId,
];

echo $twig->render('stories-worth-saving-sienna.html', $data);
