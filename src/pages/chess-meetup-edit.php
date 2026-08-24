<?php

declare(strict_types=1);

$meetupId = isset($id) && is_numeric($id) ? (int) $id : (int) ($_GET['id'] ?? 0);

if ($meetupId <= 0) {
    $_SESSION['flash_failure'] = 'Invalid Chess Game Meetup id.';
    redirect('index/51');
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));
$isLoggedIn = $viewerId > 0 && $role !== 'guest';

if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 51;
    $_SESSION['return_to'] = DOC_ROOT . 'chess-meetup-edit/' . $meetupId;
    $_SESSION['member_required_reason'] =
        'Editing a Chess Game Meetup requires Central Oregon Chess membership.';

    header('Location: ' . DOC_ROOT . 'login/51');
    exit();
}

$isChessMember = $viewerId === 1 || $cms->getMember()->isMemberOfWebsite($viewerId, 51);

if (!$isChessMember) {
    $_SESSION['flash_failure'] = 'Editing a Meetup requires Central Oregon Chess membership.';

    redirect('index/51');
    exit();
}

$stmt = $cms->getDb()->runSql(
    'SELECT *
       FROM chess_meetup
      WHERE id = :id
        AND website_id = :website_id
      LIMIT 1',
    [
        'id' => $meetupId,
        'website_id' => 51,
    ],
);

$meetup = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$meetup) {
    $_SESSION['flash_failure'] = 'Chess Game Meetup not found.';
    redirect('index/51');
    exit();
}

$isOwner = (int) $meetup['member_id'] === $viewerId;
$isUberAdmin = $viewerId === 1;

if (!$isOwner && !$isUberAdmin) {
    $_SESSION['flash_failure'] = 'You do not have permission to edit this Meetup.';

    redirect('index/51');
    exit();
}

include APP_ROOT . '/src/pages/menu-path.php';

$chessWebsite = $cms->getWebsite()->getById(51);
$chessNavigation = $cms->getMenu()->getAll2(51, 1);

$data = [
    'website' => $chessWebsite,
    'navigation' => $chessNavigation,
    'mode' => 'edit',
    'websiteId' => 51,
    'meetup' => $meetup,
];

if (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

echo $twig->render('chess-meetup-form.html', $data);
return;
