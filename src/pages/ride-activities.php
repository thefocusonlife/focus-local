<?php
declare(strict_types=1);

$websiteId =
    isset($id) && is_numeric($id)
        ? (int) $id
        : (int) ($_GET['website'] ?? ($_SESSION['website'] ?? 1));

$isLoggedIn =
    !empty($_SESSION['id']) && strtolower((string) ($_SESSION['role'] ?? 'guest')) !== 'guest';

if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 44;
    $_SESSION['return_to'] = DOC_ROOT . 'ride-activities?website=44';
    $_SESSION['member_required_reason'] =
        'Sharing a ride activity requires membership in the Central Oregon Bicycle Community.';

    header('Location: ' . DOC_ROOT . 'login/44');
    exit();
}

$memberId = (int) ($_SESSION['id'] ?? 0);

if ($memberId < 1 || !$cms->getMember()->isMemberOfWebsite($memberId, 44)) {
    $_SESSION['flash_failure'] =
        'This feature requires Central Oregon Bicycle Community membership. Please Log Out then click Register for a FREE COBC account.';

    header('Location: ' . DOC_ROOT . 'index/44');
    exit();
}
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
