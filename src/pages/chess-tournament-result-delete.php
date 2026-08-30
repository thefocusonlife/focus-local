<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/chess-leaderboard-access.php';
require_once APP_ROOT . '/src/security/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index/51');
    exit();
}

$tournamentId = filter_input(INPUT_POST, 'tournament_id', FILTER_VALIDATE_INT);

$resultId = filter_input(INPUT_POST, 'result_id', FILTER_VALIDATE_INT);

if (!is_int($tournamentId) || $tournamentId < 1 || !is_int($resultId) || $resultId < 1) {
    $_SESSION['flash_failure'] = 'A valid tournament result is required.';

    redirect('index/51');
    exit();
}

$returnUrl = 'chess-tournament-results/' . $tournamentId;

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

if ($viewerId < 1 || $viewerId === 2 || $role === 'guest') {
    $_SESSION['return_website'] = 51;
    $_SESSION['return_to'] = DOC_ROOT . $returnUrl;

    header('Location: ' . DOC_ROOT . 'login/51');
    exit();
}

if (!isChessLeaderboardAdmin($viewerId)) {
    $_SESSION['flash_failure'] = 'You do not have permission to delete Chess tournament results.';

    redirect('index/51');
    exit();
}

$csrfFormKey = 'chess_tournament_result_delete_' . $tournamentId;

$submittedCsrf = $_POST['csrf_token'] ?? '';

if (!csrf_validate($csrfFormKey, is_string($submittedCsrf) ? $submittedCsrf : null)) {
    csrf_rotate($csrfFormKey);

    $_SESSION['flash_failure'] =
        'Your form session expired or failed security validation. Please try again.';

    redirect($returnUrl);
    exit();
}

$resultStmt = $cms->getDb()->runSql(
    "
    SELECT ctr.id
    FROM chess_tournament_result ctr
    INNER JOIN chess_tournament ct
        ON ct.id = ctr.tournament_id
    WHERE ctr.id = :result_id
      AND ctr.tournament_id = :tournament_id
      AND ct.website_id = :website_id
    LIMIT 1
    ",
    [
        'result_id' => $resultId,
        'tournament_id' => $tournamentId,
        'website_id' => 51,
    ],
);

$result = $resultStmt instanceof PDOStatement ? $resultStmt->fetch(PDO::FETCH_ASSOC) : false;

if (!$result) {
    $_SESSION['flash_failure'] = 'Tournament result not found.';

    redirect($returnUrl);
    exit();
}

try {
    $cms->getDb()->runSql(
        "
        DELETE FROM chess_tournament_result
        WHERE id = :id
          AND tournament_id = :tournament_id
        LIMIT 1
        ",
        [
            'id' => $resultId,
            'tournament_id' => $tournamentId,
        ],
    );

    csrf_rotate($csrfFormKey);

    $_SESSION['flash_success'] = 'Tournament result deleted successfully.';

    redirect($returnUrl);
    exit();
} catch (Throwable $e) {
    error_log('[CHESS TOURNAMENT RESULT DELETE] ' . $e->getMessage());

    $_SESSION['flash_failure'] = 'Unable to delete the tournament result. Please try again.';

    redirect($returnUrl);
    exit();
}
