<?php

declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';
require_once APP_ROOT . '/src/security/ownership.php';
require_once APP_ROOT . '/src/security/redirects.php';

guardMember();

$sessionMemberId = (int) ($_SESSION['id'] ?? 0);
$isUberAdmin = !empty($_SESSION['isUberAdmin']);

$storyId = (int) ($_POST['story_id'] ?? 0);

if ($storyId < 1) {
    redirect(DOC_ROOT);
}

$story = $cms->getStory()->get($storyId, false);

if (!$story) {
    redirect(DOC_ROOT);
    exit();
}

assertStoryOwnership($cms, $storyId, $sessionMemberId, $isUberAdmin);

$hasUpload =
    isset($_FILES['image']) &&
    ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK &&
    is_uploaded_file($_FILES['image']['tmp_name'] ?? '');

if (!$hasUpload) {
    redirect('mobile-story-image-edit', [
        'id' => $storyId,
        'error' => 'no-image',
    ]);
}

$alt = trim((string) ($_POST['alt'] ?? ''));

if ($alt === '') {
    $name = (string) ($_FILES['image']['name'] ?? '');
    $alt = $name ? pathinfo($name, PATHINFO_FILENAME) : '';
}

$imageId = (int) ($story['image_id'] ?? 0);

if ($imageId <= 0) {
    $sql = 'INSERT INTO image (file, alt) VALUES (:file, :alt);';
    $cms->getDb()->runSql($sql, [
        'file' => '',
        'alt' => $alt,
    ]);

    $imageId = (int) $cms->getDb()->lastInsertId();
} else {
    $sql = 'UPDATE image SET alt = :alt WHERE id = :id;';
    $cms->getDb()->runSql($sql, [
        'alt' => $alt,
        'id' => $imageId,
    ]);
}

try {
    $result = $cms
        ->getImageService()
        ->saveUploadedStoryImage($_FILES['image'], $imageId, $story['title'] ?? '');

    $sql = 'UPDATE image SET file = :file, alt = :alt WHERE id = :id;';
    $cms->getDb()->runSql($sql, [
        'file' => $result['filename'],
        'alt' => $alt,
        'id' => $imageId,
    ]);

    $sql = 'UPDATE story
               SET image_id = :image_id,
                   landscape = :landscape
             WHERE id = :story_id
               AND member_id = :member_id
             LIMIT 1;';

    $cms->getDb()->runSql($sql, [
        'image_id' => $imageId,
        'landscape' => (int) ($result['landscape'] ?? 1),
        'story_id' => $storyId,
        'member_id' => $sessionMemberId,
    ]);
} catch (\Throwable $e) {
    error_log('[mobile-story-image-save] ' . $e->getMessage());

    redirect('mobile-story-image-edit', [
        'id' => $storyId,
        'error' => 'upload-failed',
    ]);
}

redirect('member/' . $sessionMemberId);
