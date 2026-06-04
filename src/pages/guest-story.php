<?php
declare(strict_types=1);

$websiteId = 1;

$website = $cms->getWebsite()->get($websiteId);

$data = [
    'website_id' => $websiteId,
    'website' => $website,
];

echo $twig->render('guest-story.html', $data);
