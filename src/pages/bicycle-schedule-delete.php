<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index/44');
    exit();
}

$allowedLocations = [
    'bend',
    'redmond',
    'sisters',
    'madras',
    'prineville',
    'lapine',
    'centraloregon',
];

$location = strtolower(trim((string) ($_POST['location'] ?? 'redmond')));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'redmond';
}

$scheduleId = (int) ($_POST['id'] ?? 0);

if ($scheduleId <= 0) {
    $_SESSION['flash_failure'] = 'Invalid Group Ride schedule.';

    redirect('index/44?location=' . urlencode($location));
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

$isLoggedIn = $viewerId > 0 && $role !== 'guest';

/*
 * Step 1: Send guests through Member Required.
 *
 * The delete POST cannot be resumed after login, so return to the
 * schedule edit page.
 */
if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 44;

    $_SESSION['return_to'] =
        DOC_ROOT . 'bicycle-schedule-edit/' . $scheduleId . '?location=' . urlencode($location);

    $_SESSION['member_required_reason'] =
        'Deleting a Group Ride requires membership in the ' . 'Central Oregon Bicycle Community.';

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

/*
 * Step 3: Load the existing schedule.
 */
$statement = $cms->getDb()->runSql(
    'SELECT id, member_id
       FROM ride_schedule
      WHERE id = :id
        AND website_id = 44
      LIMIT 1',
    [
        'id' => $scheduleId,
    ],
);

$schedule = $statement->fetch(PDO::FETCH_ASSOC);

if (!$schedule) {
    $_SESSION['flash_failure'] = 'Group Ride schedule not found.';

    redirect('index/44?location=' . urlencode($location));
    exit();
}

/*
 * Step 4: Only the owner or member 1 may delete.
 */
$isOwner = (int) $schedule['member_id'] === $viewerId;
$isUberMember = $viewerId === 1;

if (!$isOwner && !$isUberMember) {
    $_SESSION['flash_failure'] = 'You do not have permission to delete this Group Ride.';

    redirect('index/44?location=' . urlencode($location));
    exit();
}

/*
 * Step 5: Delete the schedule.
 */
$cms->getDb()->runSql(
    'DELETE FROM ride_schedule
      WHERE id = :id
        AND website_id = 44',
    [
        'id' => $scheduleId,
    ],
);

$_SESSION['flash_success'] = 'Group Ride deleted.';

redirect('index/44?location=' . urlencode($location));
exit();
