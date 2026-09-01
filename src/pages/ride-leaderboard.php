<?php
declare(strict_types=1);

$websiteId = 44;
$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

$isLoggedIn = $viewerId > 0 && $role !== 'guest';

/*
 * Step 1: Send guests through the Member Required gateway.
 */
if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 44;
    $_SESSION['return_to'] = DOC_ROOT . 'ride-leaderboard';
    $_SESSION['member_required_reason'] =
        'Viewing the ride leaderboard requires membership in the Central Oregon Bicycle Community.';

    header('Location: ' . DOC_ROOT . 'login/44');
    exit();
}

/*
 * Step 2: Require Website 44 membership.
 */
if (!$cms->getMember()->isMemberOfWebsite($viewerId, 44)) {
    $_SESSION['flash_failure'] =
        'This feature requires Central Oregon Bicycle Community membership. ' .
        'Please Log Out, then click Register for a FREE COBC account.';

    header('Location: ' . DOC_ROOT . 'index/44');
    exit();
}

$month = trim((string) ($_GET['month'] ?? date('Y-m')));

$start = DateTime::createFromFormat('!Y-m-d', $month . '-01');

if (
    !preg_match('/^\d{4}-\d{2}$/', $month) ||
    $start === false ||
    $start->format('Y-m') !== $month
) {
    $_SESSION['flash_failure'] = 'Please select a valid leaderboard month.';
    redirect('ride-leaderboard');
    exit();
}

$monthLabel = $start->format('F Y');

$end = clone $start;
$end->modify('+1 month');

$sql = "
    SELECT
        ranked.rank_miles,
        ranked.member_id,
        ranked.member_name,
        ranked.total_miles,
        ranked.total_elevation,
        ranked.ride_count
    FROM (
        SELECT
            r.member_id,
            CONCAT(m.forename, ' ', m.surname) AS member_name,
            ROUND(SUM(r.distance_miles), 1) AS total_miles,
            ROUND(SUM(r.elevation_gain_ft), 0) AS total_elevation,
            COUNT(*) AS ride_count,
            RANK() OVER (ORDER BY SUM(r.distance_miles) DESC) AS rank_miles
        FROM ride r
        INNER JOIN member m
            ON m.id = r.member_id
        WHERE r.website_id = :website_id
          AND r.distance_miles >= 2
          AND COALESCE(m.public_ride_leaderboard, 1) = 1
          AND r.ride_date >= :start_date
          AND r.ride_date < :end_date
          AND r.status = 'published'
        GROUP BY r.member_id, m.forename, m.surname
    ) ranked
    ORDER BY ranked.rank_miles ASC, ranked.member_name ASC
";

$leaderboard = $cms
    ->getDb()
    ->runSql($sql, [
        'website_id' => $websiteId,
        'start_date' => $start->format('Y-m-d'),
        'end_date' => $end->format('Y-m-d'),
    ])
    ->fetchAll();

echo $twig->render('ride-leaderboard.html', [
    'websiteId' => $websiteId,
    'month' => $month,
    'monthLabel' => $monthLabel,
    'leaderboard' => $leaderboard,
]);
