<?php
declare(strict_types=1);

$allowedLocations = [
    'bend',
    'redmond',
    'sisters',
    'madras',
    'prineville',
    'lapine',
    'centraloregon',
];

$location = strtolower((string) ($_GET['location'] ?? 'redmond'));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'redmond';
}

/*
 * Capture the schedule ID from the route:
 * bicycle-schedule-edit/7?location=redmond
 */
$scheduleId = isset($id) && is_numeric($id) ? (int) $id : (int) ($_GET['id'] ?? 0);

if ($scheduleId <= 0) {
    $_SESSION['flash_failure'] = 'Invalid schedule id.';
    redirect('index/44?location=' . urlencode($location));
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));
$isLoggedIn = $viewerId > 0 && $role !== 'guest';

/*
 * Step 1: Send guests through Member Required.
 */
if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 44;

    $_SESSION['return_to'] =
        DOC_ROOT . 'bicycle-schedule-edit/' . $scheduleId . '?location=' . urlencode($location);

    $_SESSION['member_required_reason'] =
        'Editing a group ride requires membership in the Central Oregon Bicycle Community.';

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

    redirect('index/44?location=' . urlencode($location));
    exit();
}

$groupRideManagerIds = [339, 500];

if ($viewerId !== 1 && !in_array($viewerId, $groupRideManagerIds, true)) {
    $_SESSION['flash_failure'] = 'You do not have permission to manage group rides.';

    redirect('index/44?location=' . urlencode($location));
    exit();
}

/*
 * menu-path.php can now run without being relied upon
 * for the schedule ID.
 */
include APP_ROOT . '/src/pages/menu-path.php';

$stmt = $cms->getDb()->runSql(
    'SELECT *
       FROM ride_schedule
      WHERE id = :id
        AND website_id = 44
      LIMIT 1',
    [
        'id' => $scheduleId,
    ],
);

$schedule = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$schedule) {
    $_SESSION['flash_failure'] = 'Schedule not found.';
    redirect('index/44');
    exit();
}

$data = [
    'mode' => 'edit',
    'websiteId' => 44,
    'schedule' => $schedule,
    'location' => $location,
    'locationName' => ucfirst($location),
];

if (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

echo $twig->render('bicycle-schedule-form.html', $data);
return;
