<?php
declare(strict_types=1);

// resolve location
$allowedLocations = ['bend', 'redmond', 'sisters'];

$location = strtolower((string) ($_POST['location'] ?? 'redmond'));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'redmond';
}
error_log('NOTE SAVE resolved location=' . $location);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index/44');
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? ''));

if ($viewerId <= 0 || $role === 'guest') {
    $_SESSION['return_to'] = '/index/44';
    //$_SESSION['flash_failure'] = 'You must be logged in to manage club notes.';
    redirect('login');
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
    $sql = "
        UPDATE ride_note
        SET
            title = :title,
            note_text = :note_text,
            note_date = :note_date,
            is_active = :is_active,
            member_id = :member_id,
            location = :location
        WHERE id = :id
          AND website_id = :website_id
    ";

    $cms->getDb()->runSql($sql, [
        'title' => $title,
        'note_text' => $noteText,
        'note_date' => $noteDate !== '' ? $noteDate : null,
        'is_active' => $isActive,
        'member_id' => $viewerId,
        'id' => $id,
        'website_id' => $websiteId,
        'location' => $location,
    ]);

    $_SESSION['flash_success'] = 'Club note updated.';
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

    $_SESSION['flash_success'] = 'Club note added.';
}

redirect('index/44?location=' . urlencode($location));
exit();
