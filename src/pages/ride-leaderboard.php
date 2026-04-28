<?php
declare(strict_types=1);

$websiteId = 44;
$month = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $_SESSION['flash_failure'] = 'Invalid month format.';
    redirect('index/44');
    exit();
}

$start = DateTime::createFromFormat('Y-m-d', $month . '-01');
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
    'leaderboard' => $leaderboard,
]);
