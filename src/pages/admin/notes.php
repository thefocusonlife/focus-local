<?php

require_once APP_ROOT . '/src/security/guard.php';
guardMember();

$id = !empty($parts[2]) ? (int) $parts[2] : 0;

if ($id > 0) {
    $note = $cms->getNote()->getById($id);

    if (!$note) {
        redirect('admin/notes/', ['failure' => 'Note not found']);
    }

    $cms->getNote()->approve($id);

    redirect('member/' . $note['from_id'] . '/', ['success' => 'FOLLOW ALLOWED']);
}
//is_admin($session->role);
$member = intval($_SESSION['id']);
$member2 = $cms->getMember()->get($member);
$data['success'] = $_GET['success'] ?? null; // Check for success message
$data['failure'] = $_GET['failure'] ?? null; // Check for failure message
$data['member'] = $member2;
$data['members'] = $cms->getMember()->getAll();
$data['notes'] = $cms->getNote()->getAll($member2['id']);
$data['website'] = $cms->getWebsite()->getById($member2['website']);

echo $twig->render('admin/notes.html', $data);
