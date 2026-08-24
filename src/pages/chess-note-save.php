<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index/51');
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));
$isLoggedIn = $viewerId > 0 && $role !== 'guest';

if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 51;
    $_SESSION['return_to'] = DOC_ROOT . 'chess-note-add';
    $_SESSION['member_required_reason'] =
        'Managing Club Notes requires Central Oregon Chess administrator access.';

    header('Location: ' . DOC_ROOT . 'login/51');
    exit();
}

if ($viewerId !== 1) {
    $_SESSION['flash_failure'] = 'You do not have permission to manage Chess Club Notes.';

    redirect('index/51');
    exit();
}

$id = (int) ($_POST['id'] ?? 0);
$websiteId = 51;

$allowedAreas = ['Bend', 'Redmond', 'Prineville', 'Central Oregon', 'Online'];

$title = trim((string) ($_POST['title'] ?? ''));
$area = trim((string) ($_POST['area'] ?? ''));
$noteDate = trim((string) ($_POST['note_date'] ?? ''));
$noteText = trim((string) ($_POST['note_text'] ?? ''));
$isActive = isset($_POST['is_active']) ? 1 : 0;

$formUrl = $id > 0 ? 'chess-note-edit/' . $id : 'chess-note-add';

if ($title === '' || $noteText === '') {
    $_SESSION['flash_failure'] = 'Title and Club Note are required.';

    redirect($formUrl);
    exit();
}

if (!in_array($area, $allowedAreas, true)) {
    $_SESSION['flash_failure'] = 'Please select a valid Chess area.';

    redirect($formUrl);
    exit();
}

if ($noteDate !== '') {
    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $noteDate);

    $dateErrors = DateTimeImmutable::getLastErrors();

    $dateIsValid =
        $dateObject !== false &&
        ($dateErrors === false ||
            ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0)) &&
        $dateObject->format('Y-m-d') === $noteDate;

    if (!$dateIsValid) {
        $_SESSION['flash_failure'] = 'Please enter a valid event or effective date.';

        redirect($formUrl);
        exit();
    }
}

if ($id > 0) {
    $existingStmt = $cms->getDb()->runSql(
        'SELECT id
           FROM chess_note
          WHERE id = :id
            AND website_id = :website_id
          LIMIT 1',
        [
            'id' => $id,
            'website_id' => $websiteId,
        ],
    );

    if (!$existingStmt->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['flash_failure'] = 'Club Note not found.';
        redirect('index/51');
        exit();
    }

    $cms->getDb()->runSql(
        'UPDATE chess_note
            SET title = :title,
                area = :area,
                note_date = :note_date,
                note_text = :note_text,
                is_active = :is_active,
                member_id = :member_id
          WHERE id = :id
            AND website_id = :website_id',
        [
            'title' => $title,
            'area' => $area,
            'note_date' => $noteDate !== '' ? $noteDate : null,
            'note_text' => $noteText,
            'is_active' => $isActive,
            'member_id' => $viewerId,
            'id' => $id,
            'website_id' => $websiteId,
        ],
    );

    $_SESSION['flash_success'] = 'Club Note updated.';
    redirect('index/51');
    exit();
}

$cms->getDb()->runSql(
    'INSERT INTO chess_note (
        website_id,
        member_id,
        title,
        area,
        note_date,
        note_text,
        is_active
    ) VALUES (
        :website_id,
        :member_id,
        :title,
        :area,
        :note_date,
        :note_text,
        :is_active
    )',
    [
        'website_id' => $websiteId,
        'member_id' => $viewerId,
        'title' => $title,
        'area' => $area,
        'note_date' => $noteDate !== '' ? $noteDate : null,
        'note_text' => $noteText,
        'is_active' => $isActive,
    ],
);

$_SESSION['flash_success'] = 'Club Note added.';

redirect('index/51');
exit();
