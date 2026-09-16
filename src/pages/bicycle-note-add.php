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
    $_SESSION['return_to'] = DOC_ROOT . 'bicycle-note-add?location=' . urlencode($location);
    $_SESSION['member_required_reason'] = 'Adding a Community Note requires a free TFOL account.';

    header('Location: ' . DOC_ROOT . 'login/44');
    exit();
}

/*
 * Require a Website 44 TFOL account. This is separate from the
 * inactive COBC club-membership application.
 */
$isUberMember = $viewerId === 1;

if (!$isUberMember && !$cms->getMember()->isMemberOfWebsite($viewerId, 44)) {
    $cms->getSession()->resetToGuest(44, true);

    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 44;
    $_SESSION['return_to'] = DOC_ROOT . 'bicycle-note-add?location=' . urlencode($location);
    $_SESSION['member_required_reason'] =
        'Adding a Community Note requires a Website 44 TFOL account.';

    header('Location: ' . DOC_ROOT . 'member-required/44');
    exit();
}

require_once APP_ROOT . '/src/security/guard.php';
include APP_ROOT . '/src/pages/menu-path.php';

$locations = [
    'centraloregon' => 'Central Oregon',
    'bend' => 'Bend',
    'redmond' => 'Redmond',
    'sisters' => 'Sisters',
    'madras' => 'Madras',
    'prineville' => 'Prineville',
    'lapine' => 'La Pine',
];

$data = [
    'mode' => 'add',
    'websiteId' => 44,
    'location' => $location,
    'locationName' => $locations[$location],
    'locations' => $locations,
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
