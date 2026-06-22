<?php
declare(strict_types=1);
$websiteId = 44;
$memberId = (int) ($_SESSION['id'] ?? 0);

$sql = "SELECT
    ral.*,
    m.forename,
    m.surname
FROM ride_activity_link ral
JOIN member m ON m.id = ral.member_id
ORDER BY ral.created DESC
LIMIT 25";
$activities = $cms->getDb()->runSQL($sql)->fetchAll();

$data = [
    'website' => $websiteId,
    'memberId' => $memberId,
    'title' => 'Ride Activities',
    'activities' => $activities,
];

echo $twig->render('ride-activities.html', $data);
