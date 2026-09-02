<?php
declare(strict_types=1);

// resolve location
$allowedLocations = [
    'bend',
    'redmond',
    'sisters',
    'madras',
    'prineville',
    'lapine',
    'centraloregon',
];

$location = strtolower((string) ($_POST['location'] ?? 'redmond'));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'redmond';
}
error_log('NOTE SAVE resolved location=' . $location);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index/44');
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? ''));

if ($viewerId <= 0 || $role === 'guest') {
    $_SESSION['return_to'] = '/index/44';
    //$_SESSION['flash_failure'] = 'You must be logged in to manage Community Notes.';
    redirect('login');
    exit();
}

$memberId = (int) ($_SESSION['id'] ?? 0);

if ($memberId < 1) {
    redirect('login');
}

$location = trim($_POST['location'] ?? 'redmond');

$service = trim($_POST['service'] ?? '');
$activityUrl = trim($_POST['activity_url'] ?? '');
$title = trim($_POST['title'] ?? '');
$notes = trim($_POST['notes'] ?? '');

$errors = [];

if (!isset($_POST['share_authorized'])) {
    $errors['share_authorized'] = 'You must authorize sharing this activity with COBC members.';
}

$allowedServices = ['strava', 'rwgps', 'garmin'];

if (!in_array($service, $allowedServices, true)) {
    $errors['service'] = 'Please select a service.';
}

if (!filter_var($activityUrl, FILTER_VALIDATE_URL)) {
    $errors['activity_url'] = 'Please enter a valid URL.';
}

if (mb_strlen($title) > 120) {
    $errors['title'] = 'Title is too long.';
}

if (!empty($errors)) {
    $_SESSION['ride_activity_errors'] = $errors;

    $_SESSION['ride_activity_form'] = [
        'service' => $service,
        'activity_url' => $activityUrl,
        'title' => $title,
        'notes' => $notes,
    ];

    redirect('ride-activities?location=' . urlencode($location));
}

$sql = "
    INSERT INTO ride_activity_link
    (
        website_id,
        member_id,
        location,
        service,
        activity_url,
        title,
        notes,
        published,
        created
    )
    VALUES
    (
        :website_id,
        :member_id,
        :location,
        :service,
        :activity_url,
        :title,
        :notes,
        1,
        NOW()
    )
";

$cms->getDb()->runSql($sql, [
    'website_id' => 44,
    'member_id' => $memberId,
    'location' => $location,
    'service' => $service,
    'activity_url' => $activityUrl,
    'title' => $title,
    'notes' => $notes,
]);

redirect('ride-activities?location=' . urlencode($location), ['success' => 'Ride activity saved.']);
