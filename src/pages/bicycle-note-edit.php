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

/*
 * Capture the schedule ID from the route:
 * bicycle-schedule-edit/7?location=redmond
 */
$scheduleId = isset($id) && is_numeric($id) ? (int) $id : (int) ($_GET['id'] ?? 0);

if ($scheduleId <= 0) {
    $_SESSION['flash_failure'] = 'Invalid schedule id.';
    redirect('index/44?location=' . urlencode($location));
    exit();
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

    $_SESSION['return_to'] =
        DOC_ROOT . 'bicycle-note-edit/' . $scheduleId . '?location=' . urlencode($location);

    $_SESSION['member_required_reason'] = 'Editing a Community Note requires a free TFOL account.';

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
    $_SESSION['return_to'] =
        DOC_ROOT . 'bicycle-note-edit/' . $scheduleId . '?location=' . urlencode($location);
    $_SESSION['member_required_reason'] =
        'Editing a Community Note requires a Website 44 TFOL account.';

    header('Location: ' . DOC_ROOT . 'member-required/44');
    exit();
}

include APP_ROOT . '/src/pages/menu-path.php';
$id = (int) ($id ?? ($_GET['id'] ?? 0));
if ($id <= 0) {
    $_SESSION['flash_failure'] = 'Invalid note id.';
    redirect('index/44');
    exit();
}

$stmt = $cms
    ->getDb()
    ->runSql('SELECT * FROM ride_note WHERE id = :id AND website_id = 44 LIMIT 1', ['id' => $id]);
$note = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$note) {
    $_SESSION['flash_failure'] = 'Note not found.';
    redirect('index/44');
    exit();
}

/*
 * Only the note owner or Uber Admin may edit it.
 */
$isOwner = (int) $note['member_id'] === $viewerId;
$isUberMember = $viewerId === 1;

if (!$isOwner && !$isUberMember) {
    $_SESSION['flash_failure'] = 'You do not have permission to edit this Community Note.';

    redirect('index/44');
    exit();
}

$locations = [
    'centraloregon' => 'Central Oregon',
    'bend' => 'Bend',
    'redmond' => 'Redmond',
    'sisters' => 'Sisters',
    'madras' => 'Madras',
    'prineville' => 'Prineville',
    'lapine' => 'La Pine',
];

/*
 * Use the location saved with the note rather than trusting the URL.
 */
$savedLocation = strtolower((string) ($note['location'] ?? ''));

if (array_key_exists($savedLocation, $locations)) {
    $location = $savedLocation;
} else {
    $location = 'centraloregon';
}

$data = [
    'mode' => 'edit',
    'websiteId' => 44,
    'note' => $note,
    'location' => $location,
    'locationName' => $locations[$location],
    'locations' => $locations,
];

if (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

echo $twig->render('bicycle-note-form.html', $data);
return;
