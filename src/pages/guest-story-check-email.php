<?php
declare(strict_types=1);

$websiteId = 1;

$data = [
    'website_id' => 1,
    'website' => 1,
    'email' => $_SESSION['guest_story_email'] ?? '',
];
echo $twig->render('guest-story-check-email.html', $data);
