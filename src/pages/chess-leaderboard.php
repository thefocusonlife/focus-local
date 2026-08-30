<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/chess-leaderboard-access.php';

$websiteId = 51;

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

$isLoggedIn = $viewerId > 0 && $viewerId !== 2 && $role !== 'guest';

if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = $websiteId;
    $_SESSION['return_to'] = DOC_ROOT . 'chess-leaderboard';
    $_SESSION['member_required_reason'] =
        'The Chess Leaderboard is available to active Central Oregon Chess members.';

    header('Location: ' . DOC_ROOT . 'login/51');
    exit();
}

$isUberAdmin = $viewerId === 1;

if (!$isUberAdmin) {
    $belongsToChessWebsite = $cms->getMember()->isMemberOfWebsite($viewerId, $websiteId);

    $viewerMembership = $cms->getClubMembers()->getByMemberId($viewerId, $websiteId);

    $hasActiveMembership =
        $viewerMembership !== null &&
        (string) ($viewerMembership['membership_status'] ?? '') === 'active';

    if (!$belongsToChessWebsite || !$hasActiveMembership) {
        $_SESSION['flash_failure'] =
            'The Leaderboard is available only to active Central Oregon Chess members.';

        redirect('index/51');
        exit();
    }
}

$_SESSION['website'] = $websiteId;
$_SESSION['websiteid'] = $websiteId;
$_SESSION['menu_website'] = $websiteId;

$filterAreas = [
    'Bend',
    'Redmond',
    'Sisters',
    'Madras',
    'Prineville',
    'La Pine',
    'Central Oregon',
    'Online',
];

$selectedArea = trim((string) ($_GET['area'] ?? ''));

$filterFailure = '';

if ($selectedArea !== '' && !in_array($selectedArea, $filterAreas, true)) {
    $filterFailure = 'The selected leaderboard area is not valid. Showing Overall results.';

    $selectedArea = '';
}

$leaderboardSql = "
    SELECT
        ctr.member_id,
        cm.first_name,
        cm.last_name,
        COUNT(DISTINCT ct.id) AS tournaments_played,
        SUM(
            ctr.wins +
            ctr.draws +
            ctr.losses
        ) AS games_played,
        SUM(ctr.wins) AS wins,
        SUM(ctr.draws) AS draws,
        SUM(ctr.losses) AS losses,
        SUM(
            ctr.wins +
            (ctr.draws * 0.5)
        ) AS points,
        CASE
            WHEN SUM(
                ctr.wins +
                ctr.draws +
                ctr.losses
            ) > 0
            THEN (
                SUM(
                    ctr.wins +
                    (ctr.draws * 0.5)
                ) /
                SUM(
                    ctr.wins +
                    ctr.draws +
                    ctr.losses
                )
            ) * 100
            ELSE 0
        END AS score_percentage
    FROM chess_tournament_result ctr
    INNER JOIN chess_tournament ct
        ON ct.id = ctr.tournament_id
    INNER JOIN club_members cm
        ON cm.member_id = ctr.member_id
       AND cm.website_id = ct.website_id
    WHERE ct.website_id = :website_id
      AND ct.status = 'Published'
      AND ct.is_active = 1
";

$leaderboardParams = [
    'website_id' => $websiteId,
];

if ($selectedArea !== '') {
    $leaderboardSql .= "
      AND ct.area = :area
    ";

    $leaderboardParams['area'] = $selectedArea;
}

$leaderboardSql .= "
    GROUP BY
        ctr.member_id,
        cm.first_name,
        cm.last_name
    ORDER BY
        points DESC,
        score_percentage DESC,
        wins DESC,
        cm.last_name ASC,
        cm.first_name ASC
";

$leaderboardStmt = $cms->getDb()->runSql($leaderboardSql, $leaderboardParams);

$leaderboard =
    $leaderboardStmt instanceof PDOStatement ? $leaderboardStmt->fetchAll(PDO::FETCH_ASSOC) : [];

/*
 * Apply competition ranking:
 * 1, 2, 2, 4 when leaderboard values are tied.
 */
$previousTieKey = null;
$previousRank = 0;

foreach ($leaderboard as $position => &$player) {
    $tieKey =
        number_format((float) $player['points'], 4, '.', '') .
        '|' .
        number_format((float) $player['score_percentage'], 4, '.', '') .
        '|' .
        (int) $player['wins'];

    if ($tieKey !== $previousTieKey) {
        $previousRank = $position + 1;
        $previousTieKey = $tieKey;
    }

    $player['rank'] = $previousRank;
}

unset($player);

$canManageLeaderboard = isChessLeaderboardAdmin($viewerId);

$tournaments = [];

if ($canManageLeaderboard) {
    $tournamentsStmt = $cms->getDb()->runSql(
        "
        SELECT
            ct.id,
            ct.name,
            ct.tournament_date,
            ct.area,
            ct.location,
            ct.status,
            ct.is_active,
            COUNT(ctr.id) AS participant_count
        FROM chess_tournament ct
        LEFT JOIN chess_tournament_result ctr
            ON ctr.tournament_id = ct.id
        WHERE ct.website_id = :website_id
        GROUP BY
            ct.id,
            ct.name,
            ct.tournament_date,
            ct.area,
            ct.location,
            ct.status,
            ct.is_active
        ORDER BY
            ct.tournament_date DESC,
            ct.id DESC
        ",
        [
            'website_id' => $websiteId,
        ],
    );

    $tournaments =
        $tournamentsStmt instanceof PDOStatement
            ? $tournamentsStmt->fetchAll(PDO::FETCH_ASSOC)
            : [];
}

$website = $cms->getWebsite()->getById($websiteId);

$data = [];
$data['navigation'] = $cms->getMenu()->getAll2($websiteId, $viewerId);
$data['website'] = $website;
$data['website_id'] = $websiteId;
$data['leaderboard'] = $leaderboard;
$data['filterAreas'] = $filterAreas;
$data['selectedArea'] = $selectedArea;
$data['canManageLeaderboard'] = $canManageLeaderboard;
$data['tournaments'] = $tournaments;
$data['session'] = $_SESSION;
$data['doc_root'] = $config['doc_root'] ?? DOC_ROOT;

if ($filterFailure !== '') {
    $data['failure'] = $filterFailure;
}

if (!empty($_SESSION['flash_success'])) {
    $data['success'] = (string) $_SESSION['flash_success'];

    unset($_SESSION['flash_success']);
}

if (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];

    unset($_SESSION['flash_failure']);
}

echo $twig->render('chess-leaderboard.html', $data);

exit();
