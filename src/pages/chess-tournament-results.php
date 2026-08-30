<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/chess-leaderboard-access.php';
require_once APP_ROOT . '/src/security/csrf.php';

$tournamentId = isset($id) && is_numeric($id) ? (int) $id : (int) ($_GET['tournament_id'] ?? 0);

if ($tournamentId < 1) {
    $_SESSION['flash_failure'] = 'A valid Chess tournament is required.';

    redirect('index/51');
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

if ($viewerId < 1 || $viewerId === 2 || $role === 'guest') {
    $_SESSION['return_website'] = 51;
    $_SESSION['return_to'] = DOC_ROOT . 'chess-tournament-results/' . $tournamentId;

    header('Location: ' . DOC_ROOT . 'login/51');
    exit();
}

if (!isChessLeaderboardAdmin($viewerId)) {
    $_SESSION['flash_failure'] = 'You do not have permission to manage Chess tournament results.';

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

/*
 * Eligible leaderboard participants:
 * - active Website 51 membership;
 * - age 13 or older when DOB is recorded;
 * - parental authorization for recorded minors.
 */
$playersStmt = $cms->getDb()->runSql(
    "
    SELECT
        member_id,
        first_name,
        last_name
    FROM club_members
    WHERE website_id = :website_id
      AND membership_status = 'active'
      AND (
          date_of_birth IS NULL
          OR date_of_birth <= DATE_SUB(CURDATE(), INTERVAL 13 YEAR)
      )
      AND (
          date_of_birth IS NULL
          OR date_of_birth <= DATE_SUB(CURDATE(), INTERVAL 18 YEAR)
          OR parental_authorization_accepted = 1
      )
    ORDER BY last_name ASC, first_name ASC
    ",
    [
        'website_id' => 51,
    ],
);

$players = $playersStmt instanceof PDOStatement ? $playersStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$resultsStmt = $cms->getDb()->runSql(
    "
    SELECT
        ctr.id,
        ctr.tournament_id,
        ctr.member_id,
        ctr.wins,
        ctr.draws,
        ctr.losses,
        ctr.final_place,
        ctr.created,
        ctr.updated,
        cm.first_name,
        cm.last_name,
        (
            ctr.wins +
            (ctr.draws * 0.5)
        ) AS points
    FROM chess_tournament_result ctr
    INNER JOIN club_members cm
        ON cm.member_id = ctr.member_id
       AND cm.website_id = :website_id
    WHERE ctr.tournament_id = :tournament_id
    ORDER BY
        points DESC,
        ctr.wins DESC,
        cm.last_name ASC,
        cm.first_name ASC
    ",
    [
        'website_id' => 51,
        'tournament_id' => $tournamentId,
    ],
);

$results = $resultsStmt instanceof PDOStatement ? $resultsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$resultId = filter_input(INPUT_GET, 'result', FILTER_VALIDATE_INT);

$editingResult = null;

if (is_int($resultId) && $resultId > 0) {
    $editingStmt = $cms->getDb()->runSql(
        "
        SELECT *
        FROM chess_tournament_result
        WHERE id = :id
          AND tournament_id = :tournament_id
        LIMIT 1
        ",
        [
            'id' => $resultId,
            'tournament_id' => $tournamentId,
        ],
    );

    $editingResult =
        $editingStmt instanceof PDOStatement ? $editingStmt->fetch(PDO::FETCH_ASSOC) : false;

    if (!$editingResult) {
        $_SESSION['flash_failure'] = 'Tournament result not found.';

        redirect('chess-tournament-results/' . $tournamentId);
        exit();
    }
}

if (!$editingResult) {
    $editingResult = [
        'id' => null,
        'member_id' => '',
        'wins' => 0,
        'draws' => 0,
        'losses' => 0,
        'final_place' => '',
    ];
}

include APP_ROOT . '/src/pages/menu-path.php';

$csrfFormKey = 'chess_tournament_result_save_' . $tournamentId;
$deleteCsrfFormKey = 'chess_tournament_result_delete_' . $tournamentId;

$data = [
    'website' => $cms->getWebsite()->getById(51),
    'navigation' => $cms->getMenu()->getAll2(51, 1),
    'tournament' => $tournament,
    'players' => $players,
    'results' => $results,
    'editingResult' => $editingResult,
    'csrf_token' => csrf_token($csrfFormKey),
    'delete_csrf_token' => csrf_token($deleteCsrfFormKey),
];

if (!empty($_SESSION['flash_success'])) {
    $data['success'] = (string) $_SESSION['flash_success'];

    unset($_SESSION['flash_success']);
}

if (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];

    unset($_SESSION['flash_failure']);
}

echo $twig->render('chess-tournament-results.html', $data);

return;
