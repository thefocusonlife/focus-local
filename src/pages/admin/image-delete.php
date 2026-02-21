<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/bootstrap.php';

require_once APP_ROOT . '/src/security/guard.php';
require_once APP_ROOT . '/src/security/redirects.php';
include APP_ROOT . '/src/pages/menu-path.php';

guardMember();

function publicSafePath(): string
{
    $websiteId = (int) ($_SESSION['website'] ?? 1);
    if ($websiteId <= 0) {
        $websiteId = 1;
    }
    return 'index/' . $websiteId; // or 'menu/' if that's your public-safe route
}

function logDeny(string $reason, array $ctx = []): void
{
    // Keep it simple and consistent with Week 3 mindset
    $row = [
        'ts' => date('c'),
        'event' => 'DENY',
        'reason' => $reason,
        'member_id' => $_SESSION['id'] ?? null,
        'role' => $_SESSION['role'] ?? 'guest',
        'website' => $_SESSION['website'] ?? null,
        'path' => $_SERVER['REQUEST_URI'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'ctx' => $ctx,
    ];
    error_log(json_encode($row, JSON_UNESCAPED_SLASHES));
}
// Load flash failure if set
if (!empty($_SESSION['flash_failure'])) {
    $data['flash_failure'] = $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

// Load flash success if set
if (!empty($_SESSION['flash_success'])) {
    $data['flash_success'] = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
$websiteId = (int) ($_SESSION['website'] ?? 0);
if ($websiteId <= 0) {
    logDeny('cross_tenant', [
        'story_id' => $story['id'],
        'website_id' => $websiteId,
    ]);
    $_SESSION['flash_failure'] = 'Access denied.';
    redirect(publicSafePath(), ['failure' => 'Access denied']);
    exit();
}

$website = $cms->getWebsite()->getById($websiteId);
if (!$website) {
    $_SESSION['flash_failure'] = 'Access denied.';
    redirect(publicSafePath(), ['failure' => 'Access denied']);
    exit();
}

$data = $data ?? []; // ensure array exists

$story = [];
$id = intval($parts[2]);

// Initialize story array
if (!$id) {
    // If no id
    redirect('admin/stories/', ['failure' => 'Story not found']); // Redirect
}
$story = $cms->getStory()->get($id, false); // Get story

if (!$story) {
    // If no image
    redirect('admin/stories/', ['failure' => 'Story not found']); // Redirect
}
// Ownership check (delete must be author-only; Uber bypass optional if you want)
$ownerId = (int) ($story['member_id'] ?? 0);
if ($ownerId !== $_SESSION['member_id']) {
    // If you want Uber to be able to delete any story, replace with:
    // if ($sessionId !== 1 && $ownerId !== $sessionId) { ... }
    $_SESSION['flash_failure'] = 'Access denied.';
    redirect(publicSafePath(), ['failure' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Fail closed: CSRF must exist and verify helper must be available
    if (!function_exists('verify_csrf')) {
        $_SESSION['flash_failure'] = 'Invalid request (CSRF unavailable).';
        redirect('admin/stories/');
        exit();
    }

    $token = (string) ($_POST['csrf'] ?? '');
    if ($token === '' || !verify_csrf($token)) {
        $_SESSION['flash_failure'] = 'Invalid request. Please try again.';
        redirect('admin/stories/');
        exit();
    }

    $path = APP_ROOT . '/public/uploads/' . $story['image_file']; // Path to file
    $cms->getStory()->imageDelete($story['image_id'], $path, $id); // Delete image

    // OLD:
    // redirect('admin/story/' . $id);

    // NEW: go back to the admin stories list
    $_SESSION['flash_success'] = 'Image deleted.';
    redirect('admin/stories/'); // adjust if your router expects a trailing slash or not
    exit();
}

$data['story'] = $story;
$data['website'] = $cms->getWebsite()->get($story['website']); // Story data for template
$data['csrf_token'] = generate_csrf_token();
echo $twig->render('admin/image-delete.html', $data); // Render Twig template
