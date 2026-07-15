<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index/44');
    exit();
}

$allowedLocations = [
    'bend',
    'redmond',
    'sisters',
    'madras',
    'prineville',
    'lapine',
    'centraloregon',
];

$location = strtolower(trim((string) ($_POST['location'] ?? 'redmond')));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'redmond';
}

$noteId = (int) ($_POST['id'] ?? 0);

if ($noteId <= 0) {
    $_SESSION['flash_failure'] = 'Invalid Community Note.';

    redirect('index/44?location=' . urlencode($location));
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

$isLoggedIn = $viewerId > 0 && $role !== 'guest';

/*
 * Step 1: Send guests through Member Required.
 *
 * The original POST cannot be resumed after login, so return the
 * member to the note's edit page.
 */
if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 44;

    $_SESSION['return_to'] =
        DOC_ROOT . 'bicycle-note-edit/' . $noteId . '?location=' . urlencode($location);

    $_SESSION['member_required_reason'] =
        'Deleting a Community Note requires membership in the ' .
        'Central Oregon Bicycle Community.';

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

/*
 * Step 3: Load the existing record.
 * Ownership must come from the database, not from POST data.
 */
$statement = $cms->getDb()->runSql(
    'SELECT id, member_id
       FROM ride_note
      WHERE id = :id
        AND website_id = 44
      LIMIT 1',
    [
        'id' => $noteId,
    ],
);

$note = $statement->fetch(PDO::FETCH_ASSOC);

if (!$note) {
    $_SESSION['flash_failure'] = 'Community Note not found.';

    redirect('index/44?location=' . urlencode($location));
    exit();
}

/*
 * Step 4: Only the owner or member 1 may delete.
 */
$isOwner = (int) $note['member_id'] === $viewerId;
$isUberMember = $viewerId === 1;

if (!$isOwner && !$isUberMember) {
    $_SESSION['flash_failure'] = 'You do not have permission to delete this Community Note.';

    redirect('index/44?location=' . urlencode($location));
    exit();
}

/*
 * Step 5: Delete the note.
 */
$cms->getDb()->runSql(
    'DELETE FROM ride_note
      WHERE id = :id
        AND website_id = 44',
    [
        'id' => $noteId,
    ],
);

$_SESSION['flash_success'] = 'Community Note deleted.';

redirect('index/44?location=' . urlencode($location));
exit();
