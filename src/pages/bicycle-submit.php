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
    $_SESSION['return_to'] = DOC_ROOT . 'bicycle-submit?location=' . urlencode($location);
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

require_once APP_ROOT . '/src/security/guard.php';
include APP_ROOT . '/src/pages/menu-path.php';
$data = [];
$websiteId = (int) ($_GET['website'] ?? 44);
$data['websiteId'] = $websiteId;
$allowedLocations = [
    'bend',
    'redmond',
    'sisters',
    'madras',
    'prineville',
    'lapine',
    'centraloregon',
];

$location = strtolower((string) ($_GET['location'] ?? 'bend'));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'bend';
}

$data['location'] = $location;
$data['locationName'] = ucfirst($location);

// Keep Phase 1 limited to Bicycle Club site
if ($websiteId !== 44) {
    $websiteId = 44;
    $data['websiteId'] = 44;
}

if (!empty($_SESSION['flash_success'])) {
    $data['success'] = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

if (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

// Prefill member context if logged in
$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? ''));

if ($viewerId <= 0 || $role === 'guest') {
    $_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
    if (!empty($_SESSION['flash_failure'])) {
        if ($_SESSION['flash_failure'] !== 'You must be logged in to submit a ride.') {
            $data['failure'] = (string) $_SESSION['flash_failure'];
        }
        unset($_SESSION['flash_failure']);
    }
    redirect('login');
    exit();
}

$data['viewerId'] = $viewerId;

if ($viewerId > 0) {
    $member = $cms->getMember()->get($viewerId);
    if ($member) {
        $data['member'] = $member;
        $data['rider_name'] = trim(($member['forename'] ?? '') . ' ' . ($member['surname'] ?? ''));
    }
}

echo $twig->render('bicycle-submit.html', $data);
return;
