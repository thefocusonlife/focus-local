<?php
declare(strict_types=1);

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? ''));

if ($viewerId <= 0 || $role === 'guest') {
    $_SESSION['return_to'] = '/index/44';
    $_SESSION['flash_failure'] = 'You must be logged in to manage club notes.';
    redirect('login');
    exit();
}

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
];

if (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

echo $twig->render('bicycle-note-form.html', $data);
return;
