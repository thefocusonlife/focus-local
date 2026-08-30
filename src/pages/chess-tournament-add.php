<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/chess-leaderboard-access.php';
require_once APP_ROOT . '/src/security/csrf.php';

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

if ($viewerId < 1 || $viewerId === 2 || $role === 'guest') {
    $_SESSION['return_website'] = 51;
    $_SESSION['return_to'] = DOC_ROOT . 'chess-tournament-add';

    header('Location: ' . DOC_ROOT . 'login/51');
    exit();
}

if (!isChessLeaderboardAdmin($viewerId)) {
    $_SESSION['flash_failure'] = 'You do not have permission to manage Chess tournaments.';

    redirect('index/51');
    exit();
}

include APP_ROOT . '/src/pages/menu-path.php';

$areas = [
    'Bend',
    'Redmond',
    'Sisters',
    'Madras',
    'Prineville',
    'La Pine',
    'Central Oregon',
    'Online',
];

$statuses = ['Draft', 'Published', 'Cancelled'];

$csrfFormKey = 'chess_tournament_save';

$data = [
    'website' => $cms->getWebsite()->getById(51),
    'navigation' => $cms->getMenu()->getAll2(51, 1),
    'mode' => 'add',
    'websiteId' => 51,
    'areas' => $areas,
    'statuses' => $statuses,
    'csrf_token' => csrf_token($csrfFormKey),
    'tournament' => [
        'id' => null,
        'name' => '',
        'tournament_date' => date('Y-m-d'),
        'area' => 'Central Oregon',
        'location' => '',
        'notes' => '',
        'status' => 'Draft',
        'is_active' => 1,
    ],
];

if (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

echo $twig->render('chess-tournament-form.html', $data);
return;
