<?php
declare(strict_types=1); // Use strict types
use PhpBook\Validate\Validate; // Import Validate namespace

require_once APP_ROOT . '/src/security/redirects.php';
require_once APP_ROOT . '/src/security/guard.php';
guardPublic();

// get path for website and menus
include APP_ROOT . '/src/pages/menu-path.php';

// Always derive storyId safely
$storyId = (int) ($parts[1] ?? 0);

if ($storyId <= 0) {
    http_response_code(404);
    $failure = 'Sorry! We cannot find that page.';
    include __DIR__ . '/page-not-found.php';
    return;
}

// Fetch story (true = include private? depends on your method)
$story = $cms->getStory()->get($storyId, true);

if (!$story || !is_array($story)) {
    http_response_code(404);
    $failure = 'Sorry! We cannot find that page.';
    include __DIR__ . '/page-not-found.php';
    return;
}

// Safe website id for later calls
$websiteId = (int) ($_SESSION['website'] ?? 1);
if ($websiteId <= 0) {
    $websiteId = 1;
}
$website = $cms->getWebsite()->getById($websiteId);

// Original full-size image
$originalImageFile = trim((string) ($story['original_image_file'] ?? ''));

$originalImagePath = '';
$originalImageExists = false;

if ($originalImageFile !== '') {
    // Filename only - no paths allowed
    $originalImageFile = basename($originalImageFile);

    $originalImagePath = APP_ROOT . '/public/originals/' . $originalImageFile;

    $originalImageExists = is_file($originalImagePath);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $viewerId = (int) ($_SESSION['id'] ?? 0);

    if ($viewerId <= 2) {
        http_response_code(403);
        exit('You must be logged in to make a comment.');
    }

    if ((int) ($story['allow_comment'] ?? 0) !== 1) {
        http_response_code(403);
        exit('Comments are closed for this story.');
    }

    // existing comment code continues here...

    $comment = isset($_POST['comment']) ? trim((string) $_POST['comment']) : '';

    // Configure HTMLPurifier with custom cache directory
    $config = HTMLPurifier_Config::createDefault();
    $config->set('HTML.Allowed', 'br,b,i,a[href]');

    // NEW: Tell HTMLPurifier where to store serialized definitions
    $cacheDir = APP_ROOT . '/var/cache/htmlpurifier';

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    $config->set('Cache.SerializerPath', $cacheDir);
    $config->set('Cache.SerializerPath', $cacheDir);

    $purifier = new HTMLPurifier($config);
    $comment = $purifier->purify($comment);

    // Validate comment: keep your existing user-facing message
    $error = Validate::isText($comment, 1, 2000)
        ? ''
        : 'Your comment must be between 1 and 2000 characters.
            It can contain <b>, <i>, <a>, and <br> tags.'; // Validate comment

    if ($error === '') {
        // If no error, save
        $arguments = [
            'website' => (int) $story['website'],
            'comment' => $comment,
            'story_id' => (int) $story['id'],
            'member_id' => $viewerId,
        ];
        $cms->getComment()->create($arguments); // Create comment
        redirect($path); // Reload page
    }
}

$website = $cms->getWebsite()->getById(intval($story['website']));

$viewerId = (int) ($_SESSION['id'] ?? 0); // 0 = not logged in / unknown
$isGuestOrAnon = $viewerId === 0 || $viewerId === 2;

if ($isGuestOrAnon) {
    if ((int) ($website['id'] ?? 0) > 1 && (int) ($website['non_members'] ?? 0) === 0) {
        redirect('index/99999', [
            'failure' => 'You must register as a member to access GET FOCUSED websites.
Click the "Register" link on top of this page to see pricing.',
        ]);
        exit();
    }
}

$member = $cms->getMember()->get(intval($story['member_id']));

$mem = intval($member['account_id']);

if (empty($_SESSION)) {
    $cms->getSession()->create($member['id'], $website['id']);
}

$data['navigation'] = $cms->getMenu()->getAll2($member['id'], $mem); // Get menus
$data['story'] = $story;
$data['originalImageFile'] = $originalImageFile;
$data['originalImageExists'] = $originalImageExists;
$data['section'] = $story['menu_id']; // Current menu
$data['comments'] = $cms->getComment()->getAll($storyId);
$data['website'] = $cms->getWebsite()->getById($story['website']);
// Image panel: default minimized, remember per session
$data['image_panel_minimized'] = (bool) ($_SESSION['ui']['image_panel_minimized'] ?? true);

if ($cms->getSession()->id > 0) {
    // If user logged in
    $data['liked'] = $cms->getLike()->get([$storyId, $cms->getSession()->id]);
    $data['error'] = $error ?? null; // Comment error
}

echo $twig->render('story.html', $data); // Render Twig template
