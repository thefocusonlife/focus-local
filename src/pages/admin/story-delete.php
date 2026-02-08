<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/security/guard.php';
include APP_ROOT . '/src/pages/menu-path.php'; // get path for website and menus

is_admin($session->role);
if (!empty($parts[2])) {
    $id = intval($parts[2]); // If valid id
    $story = $cms->getStory()->get($id, false); // Get story data
    if (!$story) {
        // If story empty
        redirect('admin/stories/', ['failure' => 'Story not found']); // Redirect
    }
}
$storyId = (int) ($id ?? 0);
if ($storyId <= 0) {
    redirect('admin/stories/', ['failure' => 'Story not found']);
}

// Load story (use your real getter; keep false if you need drafts/unpublished)
$story = $cms->getStory()->get($storyId, false);
if (!$story || !isset($story['id'])) {
    redirect('admin/stories/', ['failure' => 'Story not found']);
}

// Ownership guard (Uber bypass)
$sessionId = (int) ($_SESSION['id'] ?? 0);
$ownerId = (int) ($story['member_id'] ?? 0);

if ($sessionId !== 1 && $ownerId !== $sessionId) {
    redirect('admin/stories/', ['failure' => 'Not allowed']);
}

// POST = actually delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If story has an image, delete it first (matches your work-delete pattern)
    if (!empty($story['image_id']) && !empty($story['image_file'])) {
        $imageId = (int) $story['image_id'];
        $path = APP_ROOT . '/public/uploads/' . basename((string) $story['image_file']);

        $cms->getStory()->imageDelete($imageId, $path, $storyId);
    }

    // Delete comments (your Comment->delete currently deletes by story_id)
    $cms->getComment()->delete($storyId);

    // Delete story
    $cms->getStory()->delete($storyId);

    redirect('admin/stories/', ['success' => 'Story deleted']);
}

// GET = render confirm page
$data = [];
$data['story'] = $story;
$data['website'] = $cms->getWebsite()->getById($story['website']);
echo $twig->render('admin/story-delete.html', $data);
