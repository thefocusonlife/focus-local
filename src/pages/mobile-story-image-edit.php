<?php

declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';
require_once APP_ROOT . '/src/security/ownership.php';
require_once APP_ROOT . '/src/security/redirects.php';

guardMember();

$storyId = (int) ($_GET['id'] ?? 0);

if ($storyId < 1) {
    redirect(DOC_ROOT);
}

$story = $cms->getStory()->get($storyId, false);

if (!$story || (int) $story['member_id'] !== (int) $_SESSION['id']) {
    redirect(DOC_ROOT);
}

echo $twig->render('mobile-story-image-edit.html', [
    'story' => $story,
]);
