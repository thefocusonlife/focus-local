<?php
declare(strict_types=1);

$websiteId = 1;

$website = $cms->getWebsite()->get($websiteId);

$data = [
    'website_id' => $websiteId,
    'website' => $website,
];

$draft = $_SESSION['guest_story_draft'] ?? [];

$data['story'] = [
    'title' => $draft['title'] ?? '',
    'summary' => $draft['summary'] ?? '',
    'content' => $draft['content'] ?? '',
];

echo $twig->render('guest-story.html', $data);
exit();
