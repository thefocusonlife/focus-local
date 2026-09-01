<?php

declare(strict_types=1);

use PhpBook\Validate\Validate;

require_once APP_ROOT . '/src/security/guard.php';
require_once APP_ROOT . '/src/security/csrf.php';
require_once APP_ROOT . '/src/security/redirects.php';

include APP_ROOT . '/src/pages/menu-path.php';

guardMember();

$errors = [];
$storyId = (int) ($parts[2] ?? 0);
$sessionId = (int) ($_SESSION['id'] ?? 0);
$isUberAdmin = !empty($_SESSION['isUberAdmin']) || $sessionId === 1;

if ($storyId <= 0) {
    redirect('admin/stories/', ['failure' => 'Story not found']);
    exit();
}

$story = $cms->getStory()->get($storyId, false);

if (!$story || !isset($story['id'])) {
    redirect('admin/stories/', ['failure' => 'Story not found']);
    exit();
}

$ownerId = (int) ($story['member_id'] ?? 0);

if (!$isUberAdmin && $ownerId !== $sessionId) {
    redirect('admin/stories/', ['failure' => 'Not allowed']);
    exit();
}

if (empty($story['image_id'])) {
    redirect('work/' . $storyId, ['failure' => 'This story does not have an image.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf'] ?? '');

    if ($token === '' || !verify_csrf($token)) {
        $errors['warning'] = 'Invalid request. Please reload the page and try again.';
    } else {
        $story['image_alt'] = trim((string) ($_POST['image_alt'] ?? ''));

        $errors['alt'] = Validate::isText($story['image_alt'], 1, 254)
            ? ''
            : 'Alt text for the image should be 1–254 characters.';

        if (empty($errors['alt'])) {
            $updated = $cms->getStory()->altUpdate((int) $story['image_id'], $story['image_alt']);

            if ($updated) {
                redirect('work/' . $storyId, ['success' => 'Alt text updated.']);
                exit();
            }

            $errors['warning'] = 'Alt text could not be updated.';
        }
    }
}

$data['story'] = $story;
$data['errors'] = $errors;
$data['csrf_token'] = generate_csrf_token();

echo $twig->render('admin/alt-text-edit.html', $data);
