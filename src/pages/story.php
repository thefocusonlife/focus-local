<?php
declare(strict_types=1); // Use strict types
use PhpBook\Validate\Validate; // Import Validate namespace
include APP_ROOT . '/src/pages/menu-path.php';
// get path for website and menus

if (!empty($parts[1])) {
    $id = intval($parts[1]); // If valid id
    $story = $cms->getStory()->get($id, false); // Get story data
    if (!$story) {
        // If story empty
        // Redirect
    }
}
if (!$id) {
    // If no valid id
    include APP_ROOT . '/src/pages/page-not-found.php'; // Page not found
}

$story = $cms->getStory()->get($id, false); // Get story data

if (!$story) {
    // If story array is empty
    include APP_ROOT . '/src/pages/page-not-found.php'; // Page not found
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // If form submitted
    // Always normalize comment to a string to avoid PHP warnings
    // Normalize comment first
    $comment = isset($_POST['comment']) ? trim((string) $_POST['comment']) : '';

    // Configure HTMLPurifier with custom cache directory
    $config = HTMLPurifier_Config::createDefault();
    $config->set('HTML.Allowed', 'br,b,i,a[href]');

    // NEW: Tell HTMLPurifier where to store serialized definitions
    $cacheDir = $_SERVER['DOCUMENT_ROOT'] . '/focus-local/var/log/htmlpurifier';
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
        $arguments = [1, $comment, $story['id'], $cms->getSession()->id];
        $cms->getComment()->create($arguments); // Create comment
        redirect($path); // Reload page
    }
}

$website = $cms->getWebsite()->getById(intval($story['website']));

if (empty($_SESSION) or $_SESSION['id'] == 2) {
    if ($website['id'] > 1 and $website['non_members'] == 0) {
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
$data['story'] = $story; // Story
$data['section'] = $story['menu_id']; // Current menu
$data['comments'] = $cms->getComment()->getAll($id); // Get comments
$data['website'] = $cms->getWebsite()->getById($story['website']);
// Image panel: default minimized, remember per session
$data['image_panel_minimized'] = (bool) ($_SESSION['ui']['image_panel_minimized'] ?? true);

if ($cms->getSession()->id > 0) {
    // If user logged in
    $data['liked'] = $cms->getLike()->get([$id, $cms->getSession()->id]); // Did user like?
    $data['error'] = $error ?? null; // Comment error
}

echo $twig->render('story.html', $data); // Render Twig template
