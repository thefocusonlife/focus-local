<?php

declare(strict_types=1);

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));
$isLoggedIn = $viewerId > 0 && $role !== 'guest';

/*
 * Step 1: Send guests through login and return them to this form.
 */
if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 51;
    $_SESSION['return_to'] = DOC_ROOT . 'chess-schedule-add';
    $_SESSION['member_required_reason'] =
        'Adding a Chess Night requires Central Oregon Chess administrator access.';

    header('Location: ' . DOC_ROOT . 'login/51');
    exit();
}

/*
 * Step 2: Uber Admin is the only Chess schedule manager for now.
 */
if ($viewerId !== 1) {
    $_SESSION['flash_failure'] = 'You do not have permission to manage scheduled Chess Nights.';

    redirect('index/51');
    exit();
}

include APP_ROOT . '/src/pages/menu-path.php';

$chessWebsite = $cms->getWebsite()->getById(51);
$chessNavigation = $cms->getMenu()->getAll2(51, 1);

$data = [
    'website' => $chessWebsite,
    'navigation' => $chessNavigation,
    'mode' => 'add',
    'websiteId' => 51,
    'schedule' => [
        'id' => null,
        'title' => '',
        'area' => 'Central Oregon',
        'event_date' => date('Y-m-d'),
        'start_time' => '',
        'end_time' => '',
        'location' => '',
        'description' => '',
        'is_active' => 1,
    ],
];

if (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

echo $twig->render('chess-schedule-form.html', $data);
return;
