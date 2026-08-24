<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index/51');
    exit();
}

$noteId = (int) ($_POST['id'] ?? 0);

if ($noteId <= 0) {
    $_SESSION['flash_failure'] = 'Invalid Club Note.';
    redirect('index/51');
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));
$isLoggedIn = $viewerId > 0 && $role !== 'guest';

if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 51;
    $_SESSION['return_to'] = DOC_ROOT . 'chess-note-edit/' . $noteId;
    $_SESSION['member_required_reason'] =
        'Deleting a Club Note requires Central Oregon Chess administrator access.';

    header('Location: ' . DOC_ROOT . 'login/51');
    exit();
}

if ($viewerId !== 1) {
    $_SESSION['flash_failure'] = 'You do not have permission to delete Chess Club Notes.';

    redirect('index/51');
    exit();
}

$stmt = $cms->getDb()->runSql(
    'SELECT id
       FROM chess_note
      WHERE id = :id
        AND website_id = :website_id
      LIMIT 1',
    [
        'id' => $noteId,
        'website_id' => 51,
    ],
);

if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
    $_SESSION['flash_failure'] = 'Club Note not found.';
    redirect('index/51');
    exit();
}

$cms->getDb()->runSql(
    'DELETE FROM chess_note
      WHERE id = :id
        AND website_id = :website_id',
    [
        'id' => $noteId,
        'website_id' => 51,
    ],
);

$_SESSION['flash_success'] = 'Club Note deleted.';

redirect('index/51');
exit();
