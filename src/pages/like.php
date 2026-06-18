<?php
declare(strict_types=1);

$storyId = (int) ($id ?? 0);
$viewerId = (int) ($_SESSION['id'] ?? 0);

if ($storyId <= 0) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    return;
}

if ($viewerId <= 2) {
    redirect('login');
    return;
}

$liked = $cms->getLike()->get([$storyId, $viewerId]);

if ($liked) {
    $cms->getLike()->delete([$storyId, $viewerId]);
} else {
    $cms->getLike()->create([$storyId, $viewerId]);
}

redirect('story/' . $storyId);
