<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Identify Yourself
|--------------------------------------------------------------------------
|
| Ask guest for email after story draft has already been saved in session.
|
*/

if (empty($_SESSION['guest_story_draft'])) {
    header('Location: ' . DOC_ROOT . 'guest-story');
    exit();
}

$websiteId = 1;
$website = $cms->getWebsite()->get($websiteId);
$data = [
    'website_id' => $websiteId,
    'website' => $website,
    'draft' => $_SESSION['guest_story_draft'],
    'errors' => $_SESSION['identify_errors'] ?? [],
];

unset($_SESSION['identify_errors']);

echo $twig->render('identify-yourself.html', $data);
