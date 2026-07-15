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

$rideId = (int) ($_POST['ride_id'] ?? 0);

$location = strtolower(trim((string) ($_POST['location'] ?? 'redmond')));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'redmond';
}

if ($rideId <= 0) {
    $_SESSION['flash_failure'] = 'Invalid ride id.';
    redirect('index/44?location=' . urlencode($location));
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

$isLoggedIn = $viewerId > 0 && $role !== 'guest';

if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 44;

    $_SESSION['return_to'] =
        DOC_ROOT . 'bicycle-edit/' . $rideId . '?location=' . urlencode($location);

    $_SESSION['member_required_reason'] =
        'Editing a shared ride requires membership in the ' . 'Central Oregon Bicycle Community.';

    header('Location: ' . DOC_ROOT . 'login/44');
    exit();
}

if (!$cms->getMember()->isMemberOfWebsite($viewerId, 44)) {
    $_SESSION['flash_failure'] =
        'This feature requires Central Oregon Bicycle Community membership.';

    redirect('index/44?location=' . urlencode($location));
    exit();
}

$ride = $cms->getRide()->getOne($rideId, 44);

if (!$ride) {
    $_SESSION['flash_failure'] = 'Ride not found.';
    redirect('index/44?location=' . urlencode($location));
    exit();
}

//$isAdmin = in_array($role, ['admin', 'uber'], true);
$isOwner = (int) $ride['member_id'] === $viewerId;

if (!$isOwner) {
    $_SESSION['flash_failure'] = 'You do not have permission to update this ride.';

    redirect('index/44?location=' . urlencode($location));
    exit();
}

$rideDate = trim((string) ($_POST['ride_date'] ?? ''));
$title = trim((string) ($_POST['title'] ?? ''));
$rideType = trim((string) ($_POST['ride_type'] ?? ''));
$startLocation = trim((string) ($_POST['start_location'] ?? ''));
$manualStartTime = trim((string) ($_POST['manual_start_time'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? ''));

$distanceMiles = trim((string) ($_POST['distance_miles'] ?? ''));
$elapsedMinutes = trim((string) ($_POST['elapsed_minutes'] ?? ''));
$elevationGainFt = trim((string) ($_POST['elevation_gain_ft'] ?? ''));
$avgSpeedMph = trim((string) ($_POST['avg_speed_mph'] ?? ''));
$avgPowerWatts = trim((string) ($_POST['avg_power_watts'] ?? ''));
$avgHeartRate = trim((string) ($_POST['avg_heart_rate'] ?? ''));

if ($rideDate === '' || $rideType === '') {
    $_SESSION['flash_failure'] = 'Ride date and ride type are required.';

    redirect('bicycle-edit/' . $rideId . '?location=' . urlencode($location));
    exit();
}

$startTimeValue = null;

if ($manualStartTime !== '') {
    $startTimeValue = $rideDate . ' ' . $manualStartTime . ':00';
}

$rideData = [
    'ride_date' => $rideDate,
    'start_time' => $startTimeValue,
    'title' => $title !== '' ? $title : null,
    'ride_type' => $rideType,
    'start_location' => $startLocation !== '' ? $startLocation : null,
    'distance_miles' => $distanceMiles !== '' ? (float) $distanceMiles : null,
    'elapsed_minutes' => $elapsedMinutes !== '' ? (int) $elapsedMinutes : null,
    'elevation_gain_ft' => $elevationGainFt !== '' ? (int) $elevationGainFt : null,
    'avg_speed_mph' => $avgSpeedMph !== '' ? (float) $avgSpeedMph : null,
    'avg_power_watts' => $avgPowerWatts !== '' ? (int) $avgPowerWatts : null,
    'avg_heart_rate' => $avgHeartRate !== '' ? (int) $avgHeartRate : null,
    'notes' => $notes !== '' ? $notes : null,
    'location' => $location,
];

$cms->getRide()->update($rideId, 44, $rideData);

$_SESSION['flash_success'] = 'Shared ride updated.';

redirect('index/44?location=' . urlencode($location));
exit();
