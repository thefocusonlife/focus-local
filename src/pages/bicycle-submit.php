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

$location = strtolower((string) ($_GET['location'] ?? 'centraloregon'));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'centraloregon';
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

$isLoggedIn = $viewerId > 0 && $role !== 'guest';
/*
 * Require a signed-in TFOL account.
 */
if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 44;
    $_SESSION['return_to'] = DOC_ROOT . 'bicycle-submit?location=' . urlencode($location);
    $_SESSION['member_required_reason'] = 'Logging a completed ride requires a free TFOL account.';
    header('Location: ' . DOC_ROOT . 'login/44');
    exit();
}

/*
 * Require a Website 44 TFOL account.
 */
if ($viewerId !== 1 && !$cms->getMember()->isMemberOfWebsite($viewerId, 44)) {
    $cms->getSession()->resetToGuest(44, true);

    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 44;
    $_SESSION['return_to'] = DOC_ROOT . 'bicycle-submit?location=' . urlencode($location);
    $_SESSION['member_required_reason'] =
        'Logging a completed ride requires a Website 44 TFOL account.';

    header('Location: ' . DOC_ROOT . 'member-required/44');
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

$location = strtolower((string) ($_GET['location'] ?? 'centraloregon'));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'centraloregon';
}

$data['locations'] = [
    'centraloregon' => 'Central Oregon',
    'bend' => 'Bend',
    'redmond' => 'Redmond',
    'sisters' => 'Sisters',
    'madras' => 'Madras',
    'prineville' => 'Prineville',
    'lapine' => 'La Pine',
];

$data['location'] = $location;
$data['locationName'] = $data['locations'][$location];
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
