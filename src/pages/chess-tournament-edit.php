<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/chess-leaderboard-access.php';
require_once APP_ROOT . '/src/security/csrf.php';

$tournamentId = isset($id) && is_numeric($id) ? (int) $id : (int) ($_GET['id'] ?? 0);

if ($tournamentId < 1) {
    $_SESSION['flash_failure'] = 'A valid Chess tournament is required.';

    redirect('index/51');
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

if ($viewerId < 1 || $viewerId === 2 || $role === 'guest') {
    $_SESSION['return_website'] = 51;
    $_SESSION['return_to'] = DOC_ROOT . 'chess-tournament-edit/' . $tournamentId;

    header('Location: ' . DOC_ROOT . 'login/51');
    exit();
}

if (!isChessLeaderboardAdmin($viewerId)) {
    $_SESSION['flash_failure'] = 'You do not have permission to manage Chess tournaments.';

    redirect('index/51');
    exit();
}

$tournamentStmt = $cms->getDb()->runSql(
    "
    SELECT *
    FROM chess_tournament
    WHERE id = :id
      AND website_id = :website_id
    LIMIT 1
    ",
    [
        'id' => $tournamentId,
        'website_id' => 51,
    ],
);

$tournament =
    $tournamentStmt instanceof PDOStatement ? $tournamentStmt->fetch(PDO::FETCH_ASSOC) : false;

if (!$tournament) {
    $_SESSION['flash_failure'] = 'Chess tournament not found.';

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
    'mode' => 'edit',
    'websiteId' => 51,
    'areas' => $areas,
    'statuses' => $statuses,
    'csrf_token' => csrf_token($csrfFormKey),
    'tournament' => $tournament,
];

if (!empty($_SESSION['flash_success'])) {
    $data['success'] = (string) $_SESSION['flash_success'];

    unset($_SESSION['flash_success']);
}

if (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];

    unset($_SESSION['flash_failure']);
}

echo $twig->render('chess-tournament-form.html', $data);

return;
