<?php
declare(strict_types=1);

$websiteId = 1;

$data = [
    'website_id' => $websiteId,
    'website' => $websiteId,
    'email' => $_SESSION['guest_story_register_email'] ?? ($_SESSION['guest_story_email'] ?? ''),
];

echo $twig->render('guest-story-check-email.html', $data);
