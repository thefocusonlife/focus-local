<?php
declare(strict_types=1);

// resolve location
$allowedLocations = [
    'bend',
    'redmond',
    'sisters',
    'madras',
    'prineville',
    'lapine',
    'centraloregon',
];

$location = strtolower((string) ($_POST['location'] ?? 'centraloregon'));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'centraloregon';
}

error_log('NOTE SAVE resolved location=' . $location);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index/44');
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? ''));

if ($viewerId <= 0 || $role === 'guest') {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 44;
    $_SESSION['return_to'] = DOC_ROOT . 'index/44';
    $_SESSION['member_required_reason'] = 'Saving a Community Note requires a free TFOL account.';

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
    $_SESSION['return_to'] = DOC_ROOT . 'index/44';
    $_SESSION['member_required_reason'] =
        'Saving a Community Note requires a Website 44 TFOL account.';

    header('Location: ' . DOC_ROOT . 'member-required/44');
    exit();
}

$id = (int) ($_POST['id'] ?? 0);
$websiteId = 44;

$title = trim((string) ($_POST['title'] ?? ''));
$noteText = trim((string) ($_POST['note_text'] ?? ''));
$noteDate = trim((string) ($_POST['note_date'] ?? ''));
$isActive = isset($_POST['is_active']) ? 1 : 0;

if ($title === '' || $noteText === '') {
    $_SESSION['flash_failure'] = 'Title and note text are required.';
    redirect($id > 0 ? 'bicycle-note-edit/' . $id : 'bicycle-note-add');
    exit();
}

if ($id > 0) {
    /*
     * Load the existing record so ownership comes from the database.
     */
    $statement = $cms->getDb()->runSql(
        'SELECT id, member_id
           FROM ride_note
          WHERE id = :id
            AND website_id = :website_id
          LIMIT 1',
        [
            'id' => $id,
            'website_id' => $websiteId,
        ],
    );

    $existingNote = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$existingNote) {
        $_SESSION['flash_failure'] = 'Community Note not found.';
        redirect('index/44');
        exit();
    }

    $isOwner = (int) $existingNote['member_id'] === $viewerId;
    $isUberMember = $viewerId === 1;

    if (!$isOwner && !$isUberMember) {
        $_SESSION['flash_failure'] = 'You do not have permission to update this Community Note.';

        redirect('index/44');
        exit();
    }

    $sql = "
        UPDATE ride_note
        SET
            title = :title,
            note_text = :note_text,
            note_date = :note_date,
            is_active = :is_active,
            location = :location
        WHERE id = :id
          AND website_id = :website_id
    ";

    $cms->getDb()->runSql($sql, [
        'title' => $title,
        'note_text' => $noteText,
        'note_date' => $noteDate !== '' ? $noteDate : null,
        'is_active' => $isActive,
        'id' => $id,
        'website_id' => $websiteId,
        'location' => $location,
    ]);

    $_SESSION['flash_success'] = 'Community note updated.';
} else {
    $sql = "
        INSERT INTO ride_note (
            website_id,
            member_id,
            title,
            note_text,
            note_date,
            is_active,
            location
        ) VALUES (
            :website_id,
            :member_id,
            :title,
            :note_text,
            :note_date,
            :is_active,
            :location
        )
    ";

    $cms->getDb()->runSql($sql, [
        'website_id' => $websiteId,
        'member_id' => $viewerId,
        'title' => $title,
        'note_text' => $noteText,
        'note_date' => $noteDate !== '' ? $noteDate : null,
        'is_active' => $isActive,
        'location' => $location,
    ]);

    $_SESSION['flash_success'] = 'Community note added.';
}

redirect('index/44?location=' . urlencode($location));
exit();
