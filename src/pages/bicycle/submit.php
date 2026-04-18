<?php
declare(strict_types=1);

$data = [];
$websiteId = (int) ($_GET['website'] ?? 44);
$data['websiteId'] = $websiteId;

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
    $_SESSION['return_to'] = '/index/44';
    $_SESSION['flash_failure'] = 'You must be logged in to submit a ride.';
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
