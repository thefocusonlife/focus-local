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

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

$isLoggedIn = $viewerId > 0 && $role !== 'guest';
/*
 * Step 1: Send guests through the Member Required gateway.
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

/*
 * Step 2: Require Website 44 membership.
 */
if (!$cms->getMember()->isMemberOfWebsite($viewerId, 44)) {
    $_SESSION['flash_failure'] =
        'This feature requires Central Oregon Bicycle Community membership. ' .
        'Please Log Out, then click Register for a FREE COBC account.';

    header('Location: ' . DOC_ROOT . 'index/44?location=' . urlencode($location));
    exit();
}

/*
 * Step 3: Retain the existing manager-only authorization.
 */
$groupRideManagerId = 339;

if ($viewerId !== 1 && $viewerId !== $groupRideManagerId) {
    $_SESSION['flash_failure'] = 'You do not have permission to manage group rides.';

    header('Location: ' . DOC_ROOT . 'index/44?location=' . urlencode($location));
    exit();
}

include APP_ROOT . '/src/pages/menu-path.php';

$data = [
    'mode' => 'add',
    'websiteId' => 44,
    'location' => $location,
    'locationName' => ucfirst($location),
    'schedule' => [
        'id' => null,
        'title' => '',
        'ride_type' => '',
        'day_of_week' => '',
        'start_time' => '',
        'start_location' => '',
        'description' => '',
        'is_active' => 1,
        'sort_order' => 0,
    ],
];

if (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

echo $twig->render('bicycle-schedule-form.html', $data);
return;
