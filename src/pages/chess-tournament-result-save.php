<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/chess-leaderboard-access.php';
require_once APP_ROOT . '/src/security/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index/51');
    exit();
}

$tournamentId = filter_input(INPUT_POST, 'tournament_id', FILTER_VALIDATE_INT);

if (!is_int($tournamentId) || $tournamentId < 1) {
    $_SESSION['flash_failure'] = 'A valid Chess tournament is required.';

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
    $_SESSION['flash_failure'] = 'You do not have permission to manage Chess tournament results.';

    redirect('index/51');
    exit();
}

$csrfFormKey = 'chess_tournament_result_save_' . $tournamentId;

$submittedCsrf = $_POST['csrf_token'] ?? '';

if (!csrf_validate($csrfFormKey, is_string($submittedCsrf) ? $submittedCsrf : null)) {
    csrf_rotate($csrfFormKey);

    $_SESSION['flash_failure'] =
        'Your form session expired or failed security validation. Please try again.';

    redirect($returnUrl);
    exit();
}

$tournamentStmt = $cms->getDb()->runSql(
    "
    SELECT id
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

$resultId = filter_input(INPUT_POST, 'result_id', FILTER_VALIDATE_INT);

$resultId = is_int($resultId) && $resultId > 0 ? $resultId : 0;

$memberId = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT);

$wins = filter_var($_POST['wins'] ?? null, FILTER_VALIDATE_INT, [
    'options' => [
        'min_range' => 0,
        'max_range' => 999,
    ],
]);

$draws = filter_var($_POST['draws'] ?? null, FILTER_VALIDATE_INT, [
    'options' => [
        'min_range' => 0,
        'max_range' => 999,
    ],
]);

$losses = filter_var($_POST['losses'] ?? null, FILTER_VALIDATE_INT, [
    'options' => [
        'min_range' => 0,
        'max_range' => 999,
    ],
]);

$finalPlaceInput = trim((string) ($_POST['final_place'] ?? ''));

$finalPlace = null;

if ($finalPlaceInput !== '') {
    $validatedPlace = filter_var($finalPlaceInput, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
            'max_range' => 999,
        ],
    ]);

    if ($validatedPlace === false) {
        $_SESSION['flash_failure'] = 'Final place must be a whole number between 1 and 999.';

        redirect($returnUrl);
        exit();
    }

    $finalPlace = (int) $validatedPlace;
}

if (!is_int($memberId) || $memberId < 1) {
    $_SESSION['flash_failure'] = 'Please select an active Chess member.';

    redirect($returnUrl);
    exit();
}

if ($wins === false || $draws === false || $losses === false) {
    $_SESSION['flash_failure'] = 'Wins, draws, and losses must be whole numbers between 0 and 999.';

    redirect($returnUrl);
    exit();
}

$wins = (int) $wins;
$draws = (int) $draws;
$losses = (int) $losses;

if ($wins + $draws + $losses < 1) {
    $_SESSION['flash_failure'] = 'A participant result must include at least one completed game.';

    redirect($returnUrl);
    exit();
}

/*
 * Confirm that the selected player is eligible for the leaderboard.
 */
$playerStmt = $cms->getDb()->runSql(
    "
    SELECT member_id
    FROM club_members
    WHERE member_id = :member_id
      AND website_id = :website_id
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
    LIMIT 1
    ",
    [
        'member_id' => $memberId,
        'website_id' => 51,
    ],
);

$eligiblePlayer =
    $playerStmt instanceof PDOStatement ? $playerStmt->fetch(PDO::FETCH_ASSOC) : false;

if (!$eligiblePlayer) {
    $_SESSION['flash_failure'] = 'The selected player is not eligible for the Chess leaderboard.';

    redirect($returnUrl);
    exit();
}

/*
 * A participant may appear only once in a tournament.
 */
$duplicateStmt = $cms->getDb()->runSql(
    "
    SELECT id
    FROM chess_tournament_result
    WHERE tournament_id = :tournament_id
      AND member_id = :member_id
      AND id <> :result_id
    LIMIT 1
    ",
    [
        'tournament_id' => $tournamentId,
        'member_id' => $memberId,
        'result_id' => $resultId,
    ],
);

$duplicateResult =
    $duplicateStmt instanceof PDOStatement ? $duplicateStmt->fetch(PDO::FETCH_ASSOC) : false;

if ($duplicateResult) {
    $_SESSION['flash_failure'] = 'That player already has a result for this tournament.';

    redirect($returnUrl);
    exit();
}

try {
    if ($resultId > 0) {
        $existingStmt = $cms->getDb()->runSql(
            "
            SELECT id
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

        $existingResult =
            $existingStmt instanceof PDOStatement ? $existingStmt->fetch(PDO::FETCH_ASSOC) : false;

        if (!$existingResult) {
            $_SESSION['flash_failure'] = 'Tournament result not found.';

            redirect($returnUrl);
            exit();
        }

        $cms->getDb()->runSql(
            "
            UPDATE chess_tournament_result
            SET member_id = :member_id,
                wins = :wins,
                draws = :draws,
                losses = :losses,
                final_place = :final_place
            WHERE id = :id
              AND tournament_id = :tournament_id
            ",
            [
                'member_id' => $memberId,
                'wins' => $wins,
                'draws' => $draws,
                'losses' => $losses,
                'final_place' => $finalPlace,
                'id' => $resultId,
                'tournament_id' => $tournamentId,
            ],
        );

        csrf_rotate($csrfFormKey);

        $_SESSION['flash_success'] = 'Tournament result updated successfully.';

        redirect($returnUrl);
        exit();
    }

    $cms->getDb()->runSql(
        "
        INSERT INTO chess_tournament_result (
            tournament_id,
            member_id,
            wins,
            draws,
            losses,
            final_place,
            created_by
        ) VALUES (
            :tournament_id,
            :member_id,
            :wins,
            :draws,
            :losses,
            :final_place,
            :created_by
        )
        ",
        [
            'tournament_id' => $tournamentId,
            'member_id' => $memberId,
            'wins' => $wins,
            'draws' => $draws,
            'losses' => $losses,
            'final_place' => $finalPlace,
            'created_by' => $viewerId,
        ],
    );

    csrf_rotate($csrfFormKey);

    $_SESSION['flash_success'] = 'Tournament result added successfully.';

    redirect($returnUrl);
    exit();
} catch (Throwable $e) {
    error_log('[CHESS TOURNAMENT RESULT SAVE] ' . $e->getMessage());

    $_SESSION['flash_failure'] = 'Unable to save the tournament result. Please try again.';

    redirect($returnUrl);
    exit();
}
