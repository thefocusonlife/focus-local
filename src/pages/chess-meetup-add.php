<?php

declare(strict_types=1);

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));
$isLoggedIn = $viewerId > 0 && $role !== 'guest';

if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 51;
    $_SESSION['return_to'] = DOC_ROOT . 'chess-meetup-add';
    $_SESSION['member_required_reason'] =
        'Posting a Chess Game Meetup requires Central Oregon Chess membership.';

    header('Location: ' . DOC_ROOT . 'login/51');
    exit();
}

$isChessMember = $viewerId === 1 || $cms->getMember()->isMemberOfWebsite($viewerId, 51);

if (!$isChessMember) {
    $_SESSION['flash_failure'] = 'Posting a Meetup requires Central Oregon Chess membership.';

    redirect('index/51');
    exit();
}

include APP_ROOT . '/src/pages/menu-path.php';

$chessWebsite = $cms->getWebsite()->getById(51);
$chessNavigation = $cms->getMenu()->getAll2(51, 1);

$data = [
    'website' => $chessWebsite,
    'navigation' => $chessNavigation,
    'mode' => 'add',
    'websiteId' => 51,
    'meetup' => [
        'id' => null,
        'title' => '',
        'area' => 'Central Oregon',
        'meetup_type' => 'In Person',
        'meetup_date' => date('Y-m-d'),
        'start_time' => '',
        'end_time' => '',
        'location' => '',
        'player_level' => '',
        'description' => '',
        'status' => 'Open',
        'is_active' => 1,
    ],
];

if (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

echo $twig->render('chess-meetup-form.html', $data);
return;
