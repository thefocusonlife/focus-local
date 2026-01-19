<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/security/guard.php';

// Must be logged in (member/admin). If you have a specific helper, use it.
// Otherwise, at least require a session id.
$sessionId = (int) ($_SESSION['id'] ?? 0);
if ($sessionId <= 0) {
    redirect('login/', ['failure' => 'Please log in']);
}

// Validate story id from route
$storyId = (int) ($id ?? 0);
if ($storyId <= 0) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

// Load story (false = include unpublished/drafts? keep your existing behavior)
$story = $cms->getStory()->get($storyId, false);
if (!$story || !isset($story['id'])) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

// Ownership check (delete must be author-only; Uber bypass optional if you want)
$ownerId = (int) ($story['member_id'] ?? 0);
if ($ownerId !== $sessionId) {
    // If you want Uber to be able to delete any story, replace with:
    // if ($sessionId !== 1 && $ownerId !== $sessionId) { ... }
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Delete image (if present)
    if (!empty($story['image_id']) && !empty($story['image_file'])) {
        $imageId = (int) $story['image_id'];
        $path = APP_ROOT . '/public/uploads/' . basename((string) $story['image_file']);

        $cms->getStory()->imageDelete($imageId, $path, $storyId);
    }

    // 2) Delete comments belonging to this story
    $comments = $cms->getComment()->getAll($storyId);

    if (is_array($comments) && count($comments) > 0) {
        foreach ($comments as $item) {
            // Belt & suspenders: ensure we only delete comments for this story
            if ((int) ($item['story_id'] ?? 0) !== $storyId) {
                continue;
            }

            $commentId = (int) ($item['id'] ?? 0);
            if ($commentId > 0) {
                $cms->getComment()->delete($commentId);
            }
        }
    }

    // 3) Delete story
    $cms->getStory()->delete($storyId);

    redirect('member/' . $sessionId . '/', ['success' => 'Story deleted']);
}

// Navigation (if you truly need all menus here; otherwise consider scoping later)
$data = [];
$data['navigation'] = $cms->getMenu()->getAll();
$data['story'] = $story;

echo $twig->render('work-delete.html', $data);
