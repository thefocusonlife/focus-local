<?php
declare(strict_types=1);

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? ''));

if ($viewerId <= 0 || $role === 'guest') {
    $_SESSION['return_to'] = '/index/44';
    $_SESSION['flash_failure'] = 'You must be logged in to manage club notes.';
    redirect('login');
    exit();
}

$data = [
    'mode' => 'add',
    'websiteId' => 44,
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
