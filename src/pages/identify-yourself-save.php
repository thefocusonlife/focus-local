<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Identify Yourself Save
|--------------------------------------------------------------------------
|
| Checks whether the email belongs to an existing member.
| If yes: send to login.
| If no: send to register.
|
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . DOC_ROOT . 'identify-yourself');
    exit();
}

if (empty($_SESSION['guest_story_draft'])) {
    header('Location: ' . DOC_ROOT . 'guest-story');
    exit();
}

$email = strtolower(trim($_POST['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['identify_errors'] = ['Please enter a valid email address.'];

    header('Location: ' . DOC_ROOT . 'identify-yourself');
    exit();
}

/*
|--------------------------------------------------------------------------
| Save email with the guest story draft
|--------------------------------------------------------------------------
*/

$_SESSION['guest_story_email'] = $email;

/*
|--------------------------------------------------------------------------
| Check whether email already exists
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT id
    FROM member
    WHERE LOWER(email) = LOWER(:email)
    LIMIT 1
";

$stmt = $cms->getDb()->runSql($sql, [
    'email' => $email,
]);

$existingMember = $stmt->fetch();

/*
|--------------------------------------------------------------------------
| Route existing vs new member
|--------------------------------------------------------------------------
*/

if ($existingMember) {
    $_SESSION['guest_story_login_notice'] = 'Please sign in to save your story.';

    $_SESSION['guest_story_email'] = $email;

    header('Location: ' . DOC_ROOT . 'login?guest_story=1');
    exit();
}

$_SESSION['guest_story_register_email'] = $email;

header('Location: ' . DOC_ROOT . 'register?guest_story=1');
exit();
