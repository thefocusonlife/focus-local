<?php
declare(strict_types=1);

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? ''));

if ($viewerId <= 0 || $role === 'guest') {
    $_SESSION['return_to'] = '/index/44';
    $_SESSION['flash_failure'] = 'You must be logged in to manage group rides.';
    redirect('login');
    exit();
}
$groupRideManagerId = 339;

if ($viewerId !== 1 && $viewerId !== $groupRideManagerId) {
    $_SESSION['flash_failure'] = 'You do not have permission to manage group rides.';
    redirect('index/44');
    exit();
}

include APP_ROOT . '/src/pages/menu-path.php';
$data = [
    'mode' => 'add',
    'websiteId' => 44,
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
