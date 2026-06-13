<?php
declare(strict_types=1);
$allowedLocations = ['bend', 'redmond', 'sisters'];

$location = strtolower((string) ($_GET['location'] ?? 'redmond'));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'redmond';
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? ''));

if ($viewerId <= 0 || $role === 'guest') {
    $_SESSION['return_to'] = DOC_ROOT . 'bicycle-note-add?location=' . urlencode($location);

    redirect('login');
    exit();
}

require_once APP_ROOT . '/src/security/guard.php';
include APP_ROOT . '/src/pages/menu-path.php';
$data = [
    'mode' => 'add',
    'websiteId' => 44,
    'location' => $location,
    'locationName' => ucfirst($location),
    'note' => [
        'id' => null,
        'title' => '',
        'note_text' => '',
        'note_date' => '',
        'is_active' => 1,
    ],
];

if (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

echo $twig->render('bicycle-note-form.html', $data);
return;
