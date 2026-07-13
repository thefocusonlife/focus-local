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
 * Step 1: Send guests through Member Required.
 */
if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 44;

    $_SESSION['return_to'] =
        DOC_ROOT . 'bicycle-note-edit/' . $scheduleId . '?location=' . urlencode($location);

    $_SESSION['member_required_reason'] =
        'Editing a Community Note requires membership in the Central Oregon Bicycle Community.';

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

    redirect('index/44?location=' . urlencode($location));
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

$data = [
    'mode' => 'edit',
    'websiteId' => 44,
    'note' => $note,
    'location' => $location,
    'locationName' => ucfirst($location),
];

if (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

echo $twig->render('bicycle-note-form.html', $data);
return;
