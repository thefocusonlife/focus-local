<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';
guardMember();

require_once APP_ROOT . '/src/security/csrf.php';

$memberId = (int) ($_SESSION['id'] ?? 0);
$noteId = (int) ($parts[2] ?? 0);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$formKey = 'follow-delete';

if ($memberId <= 0 || $memberId === 2 || $noteId <= 0) {
    http_response_code(404);
    exit('Follow Request not found.');
}

$note = $cms->getNote()->getById($noteId);

/*
 * Only participants can access this confirmation page.
 * Ordinary messages cannot be deleted through this route.
 */
if (
    !$note ||
    (int) $note['note_type'] !== 1 ||
    ((int) $note['from_id'] !== $memberId && (int) $note['to_id'] !== $memberId)
) {
    http_response_code(404);
    exit('Follow Request not found.');
}

if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method not allowed.');
}

if ($method === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;

    if (!csrf_validate($formKey, is_string($submittedToken) ? $submittedToken : null)) {
        redirect('admin/notes/', [
            'failure' => 'Your form session expired. Please try again.',
        ]);
        exit();
    }

    $deleted = $cms->getNote()->delete($noteId);

    if (!$deleted) {
        redirect('admin/notes/', [
            'failure' => 'Unable to delete this Follow Request.',
        ]);
        exit();
    }

    csrf_rotate($formKey);

    redirect('admin/notes/', [
        'success' => 'Follow Request deleted.',
    ]);
    exit();
}

/*
 * GET displays confirmation only; it does not delete anything.
 */
$member = $cms->getMember()->get($memberId);

if (!$member) {
    http_response_code(404);
    exit('Member not found.');
}

$data['member'] = $member;
$data['note'] = $note;
$data['delete_csrf_token'] = csrf_token($formKey);
$data['website'] = $cms->getWebsite()->getById((int) $member['website']);

echo $twig->render('admin/note-delete.html', $data);
