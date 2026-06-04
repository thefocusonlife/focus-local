<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Guest Story Save
|--------------------------------------------------------------------------
|
| Save guest story draft into session.
| User identification (login/register) happens next.
|
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . DOC_ROOT . 'guest-story');
    exit();
}

/*
|--------------------------------------------------------------------------
| Collect Input
|--------------------------------------------------------------------------
*/

$title = trim($_POST['title'] ?? '');
$summary = trim($_POST['summary'] ?? '');
$content = trim($_POST['content'] ?? '');

/*
|--------------------------------------------------------------------------
| Basic Validation
|--------------------------------------------------------------------------
*/

$errors = [];

if ($title === '') {
    $errors[] = 'Title is required.';
}

if ($content === '') {
    $errors[] = 'Story content is required.';
}

if (!empty($errors)) {
    $_SESSION['guest_story_errors'] = $errors;

    $_SESSION['guest_story_draft'] = [
        'title' => $title,
        'summary' => $summary,
        'content' => $content,
    ];

    header('Location: ' . DOC_ROOT . 'guest-story');
    exit();
}

/*
|--------------------------------------------------------------------------
| Save Draft
|--------------------------------------------------------------------------
*/

$_SESSION['guest_story_draft'] = [
    'title' => $title,
    'summary' => $summary,
    'content' => $content,
    'created_at' => date('Y-m-d H:i:s'),
];

/*
|--------------------------------------------------------------------------
| Continue To Identification Step
|--------------------------------------------------------------------------
*/

header('Location: ' . DOC_ROOT . 'identify-yourself');
exit();
