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
$id = (int) ($id ?? ($_GET['id'] ?? 0));
if ($id <= 0) {
    $_SESSION['flash_failure'] = 'Invalid schedule id.';
    redirect('index/44');
    exit();
}

$stmt = $cms
    ->getDb()
    ->runSql('SELECT * FROM ride_schedule WHERE id = :id AND website_id = 44 LIMIT 1', [
        'id' => $id,
    ]);
$schedule = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$schedule) {
    $_SESSION['flash_failure'] = 'Schedule not found.';
    redirect('index/44');
    exit();
}

$data = [
    'mode' => 'edit',
    'websiteId' => 44,
    'schedule' => $schedule,
];

if (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

echo $twig->render('bicycle-schedule-form.html', $data);
return;
