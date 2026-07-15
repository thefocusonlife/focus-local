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
 * Expected route:
 * bicycle-edit/123?location=redmond
 */
$rideId = isset($id) && is_numeric($id) ? (int) $id : (int) ($_GET['id'] ?? 0);

if ($rideId <= 0) {
    $_SESSION['flash_failure'] = 'Invalid ride id.';
    redirect('index/44?location=' . urlencode($location));
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

$isLoggedIn = $viewerId > 0 && $role !== 'guest';

/*
 * Step 1: Member Required gateway.
 */
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

/*
 * Step 2: Website 44 membership.
 */
if (!$cms->getMember()->isMemberOfWebsite($viewerId, 44)) {
    $_SESSION['flash_failure'] =
        'This feature requires Central Oregon Bicycle Community membership. ' .
        'Please Log Out, then click Register for a FREE COBC account.';

    redirect('index/44?location=' . urlencode($location));
    exit();
}

/*
 * Step 3: Retrieve the ride.
 */
$ride = $cms->getRide()->getOne($rideId, 44);

if (!$ride) {
    $_SESSION['flash_failure'] = 'Ride not found.';
    redirect('index/44?location=' . urlencode($location));
    exit();
}

/*
 * Step 4: Owner or administrator only.
 */
//$isAdmin = in_array($role, ['admin', 'uber'], true);
$isOwner = (int) $ride['member_id'] === $viewerId;

if (!$isOwner) {
    $_SESSION['flash_failure'] = 'You do not have permission to edit this ride.';

    redirect('index/44?location=' . urlencode($location));
    exit();
}

/*
 * Prefer the ride's saved COBC location.
 */
$savedLocation = strtolower((string) ($ride['location'] ?? ''));

if (in_array($savedLocation, $allowedLocations, true)) {
    $location = $savedLocation;
}

$websiteId = 44;
$website = $cms->getWebsite()->get($websiteId);

if (!$website) {
    $_SESSION['flash_failure'] = 'Website not found.';
    redirect('index/44?location=' . urlencode($location));
    exit();
}

$data = [
    'website' => $website,
    'websiteId' => $websiteId,
    'title' => 'Edit Shared Ride',
    'ride' => $ride,
    'rideId' => $rideId,
    'location' => $location,
    'locationName' => $location === 'centraloregon' ? 'Central Oregon' : ucfirst($location),
];

if (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

if (!empty($_SESSION['flash_success'])) {
    $data['success'] = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

echo $twig->render('bicycle-edit.html', $data);
return;
