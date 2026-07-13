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

$location = strtolower((string) ($_POST['location'] ?? ($_GET['location'] ?? 'redmond')));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'redmond';
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index/44');
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

$isLoggedIn = $viewerId > 0 && $role !== 'guest';

/*
 * Protect direct POST requests as well as the form controller.
 */
if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 44;
    $_SESSION['return_to'] = DOC_ROOT . 'bicycle-schedule-add?location=' . urlencode($location);
    $_SESSION['member_required_reason'] =
        'Adding a group ride requires membership in the Central Oregon Bicycle Community.';

    header('Location: ' . DOC_ROOT . 'login/44');
    exit();
}

if (!$cms->getMember()->isMemberOfWebsite($viewerId, 44)) {
    $_SESSION['flash_failure'] =
        'This feature requires Central Oregon Bicycle Community membership. ' .
        'Please Log Out, then click Register for a FREE COBC account.';

    header('Location: ' . DOC_ROOT . 'index/44?location=' . urlencode($location));
    exit();
}

$groupRideManagerId = 339;

if ($viewerId !== 1 && $viewerId !== $groupRideManagerId) {
    $_SESSION['flash_failure'] = 'You do not have permission to manage group rides.';

    header('Location: ' . DOC_ROOT . 'index/44?location=' . urlencode($location));
    exit();
}

$id = (int) ($_POST['id'] ?? 0);
$websiteId = 44;

$title = trim((string) ($_POST['title'] ?? ''));
$rideType = trim((string) ($_POST['ride_type'] ?? ''));
$dayOfWeek = trim((string) ($_POST['day_of_week'] ?? ''));
$startTime = trim((string) ($_POST['start_time'] ?? ''));
$startLocation = trim((string) ($_POST['start_location'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$isActive = isset($_POST['is_active']) ? 1 : 0;
$sortOrder = (int) ($_POST['sort_order'] ?? 0);

if ($title === '' || $dayOfWeek === '' || $startTime === '') {
    $_SESSION['flash_failure'] = 'Title, day, and start time are required.';

    $formUrl = $id > 0 ? 'bicycle-schedule-edit/' . $id : 'bicycle-schedule-add';

    redirect($formUrl . '?location=' . urlencode($location));
    exit();
}
if ($id > 0) {
    $sql = "
        UPDATE ride_schedule
        SET
            title = :title,
            ride_type = :ride_type,
            day_of_week = :day_of_week,
            start_time = :start_time,
            start_location = :start_location,
            description = :description,
            is_active = :is_active,
            sort_order = :sort_order,
            member_id = :member_id,
            location = :location
        WHERE id = :id
          AND website_id = :website_id
    ";

    $cms->getDb()->runSql($sql, [
        'title' => $title,
        'ride_type' => $rideType !== '' ? $rideType : null,
        'day_of_week' => $dayOfWeek,
        'start_time' => $startTime,
        'start_location' => $startLocation !== '' ? $startLocation : null,
        'description' => $description !== '' ? $description : null,
        'is_active' => $isActive,
        'sort_order' => $sortOrder,
        'member_id' => $viewerId,
        'id' => $id,
        'website_id' => $websiteId,
        'location' => $location,
    ]);

    $_SESSION['flash_success'] = 'Group ride updated.';
    redirect('index/44?location=' . urlencode($location));
    exit();
} else {
    $sql = "
        INSERT INTO ride_schedule (
            website_id,
            member_id,
            title,
            ride_type,
            day_of_week,
            start_time,
            start_location,
            description,
            is_active,
            sort_order,
            location

        ) VALUES (
            :website_id,
            :member_id,
            :title,
            :ride_type,
            :day_of_week,
            :start_time,
            :start_location,
            :description,
            :is_active,
            :sort_order,
            :location
        )
    ";

    $cms->getDb()->runSql($sql, [
        'website_id' => $websiteId,
        'member_id' => $viewerId,
        'title' => $title,
        'ride_type' => $rideType !== '' ? $rideType : null,
        'day_of_week' => $dayOfWeek,
        'start_time' => $startTime,
        'start_location' => $startLocation !== '' ? $startLocation : null,
        'description' => $description !== '' ? $description : null,
        'is_active' => $isActive,
        'sort_order' => $sortOrder,
        'location' => $location,
    ]);

    $_SESSION['flash_success'] = 'Group ride added.';
    redirect('index/44?location=' . urlencode($location));
    exit();
}

redirect('index/44');
exit();
