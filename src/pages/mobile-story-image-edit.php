<?php

declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';
require_once APP_ROOT . '/src/security/ownership.php';
require_once APP_ROOT . '/src/security/redirects.php';
require_once APP_ROOT . '/src/security/csrf.php';

guardMember();
$viewerId = (int) ($_SESSION['id'] ?? 0);
$viewer = $cms->getMember()->get($viewerId);

if ($viewerId <= 0 || $viewerId === 2 || !$viewer) {
    http_response_code(403);
    exit('Please log in with a member account.');
}

$selectedAccountId = (int) ($viewer['account_id'] ?? 0);

if ($selectedAccountId > 0 && $selectedAccountId !== $viewerId) {
    redirect('admin/notes/', [
        'failure' => 'Return to My Account before adding or editing stories.',
    ]);
    exit();
}
$storyId = (int) ($_GET['id'] ?? 0);

if ($storyId < 1) {
    redirect(DOC_ROOT);
}

$story = $cms->getStory()->get($storyId, false);

if (!$story || (int) $story['member_id'] !== (int) $_SESSION['id']) {
    redirect(DOC_ROOT);
}
$data['story'] = $story; // Story
$data['website'] = $cms->getWebsite()->getById($story['website']);
$data['image_csrf_token'] = csrf_token('mobile-story-image');
echo $twig->render('mobile-story-image-edit.html', $data);
