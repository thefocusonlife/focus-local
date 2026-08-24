<?php

declare(strict_types=1);

$noteId = isset($id) && is_numeric($id) ? (int) $id : (int) ($_GET['id'] ?? 0);

if ($noteId <= 0) {
    $_SESSION['flash_failure'] = 'Invalid Club Note id.';
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
        'Editing a Club Note requires Central Oregon Chess administrator access.';

    header('Location: ' . DOC_ROOT . 'login/51');
    exit();
}

if ($viewerId !== 1) {
    $_SESSION['flash_failure'] = 'You do not have permission to manage Chess Club Notes.';

    redirect('index/51');
    exit();
}

include APP_ROOT . '/src/pages/menu-path.php';

$stmt = $cms->getDb()->runSql(
    'SELECT *
       FROM chess_note
      WHERE id = :id
        AND website_id = :website_id
      LIMIT 1',
    [
        'id' => $noteId,
        'website_id' => 51,
    ],
);

$note = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$note) {
    $_SESSION['flash_failure'] = 'Club Note not found.';
    redirect('index/51');
    exit();
}

$chessWebsite = $cms->getWebsite()->getById(51);
$chessNavigation = $cms->getMenu()->getAll2(51, 1);

$data = [
    'website' => $chessWebsite,
    'navigation' => $chessNavigation,
    'mode' => 'edit',
    'websiteId' => 51,
    'note' => $note,
];

if (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

echo $twig->render('chess-note-form.html', $data);
return;
