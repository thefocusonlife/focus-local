<?php

include APP_ROOT . '/src/pages/menu-path.php';

require_once APP_ROOT . '/src/security/guard.php';
guardMember();

is_admin($session->role); // Check if admin
$member = $cms->getMember()->get($_SESSION['id']);
if (!$_SESSION['id']) {
    $website = $cms->getWebsite()->getByID(1);
} else {
    $website = $cms->getWebsite()->getByID($member['website']);
}
$data['success'] = $_GET['success'] ?? null; // Check for success message
$data['failure'] = $_GET['failure'] ?? null; // Check for failure message

$visibility = strtolower(trim((string) ($_GET['visibility'] ?? 'all')));

if (!in_array($visibility, ['all', 'review', 'public', 'private'], true)) {
    $visibility = 'all';
}

$published = match ($visibility) {
    'public' => 1,
    'private', 'review' => 0,
    default => null,
};

$reviewRequested = $visibility === 'review';

$data['visibility'] = $visibility;
if ((int) $_SESSION['id'] === 1) {
    // Uber Admin: retrieve stories across all active websites.
    $data['stories'] = $cms
        ->getStory()
        ->getAll3((int) $website['id'], $published, null, null, 300, null, true, $reviewRequested);
} else {
    // Other administrators: remain restricted to their website and authorship.
    $data['stories'] = $cms
        ->getStory()
        ->getAll3(
            (int) $website['id'],
            $published,
            null,
            (int) $_SESSION['id'],
            300,
            null,
            false,
            $reviewRequested,
        );
}
$data['website'] = $website;

echo $twig->render('admin/stories.html', $data); // Render Twig template
