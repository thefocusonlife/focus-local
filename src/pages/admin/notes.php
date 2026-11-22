<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';
guardMember();

require_once APP_ROOT . '/src/security/csrf.php';

$memberId = (int) ($_SESSION['id'] ?? 0);
$noteId = (int) ($parts[2] ?? 0);
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($memberId <= 0 || $memberId === 2) {
    http_response_code(403);
    exit('Please log in with a member account.');
}

$member = $cms->getMember()->get($memberId);

if (!$member) {
    http_response_code(404);
    include APP_ROOT . '/src/pages/page-not-found.php';
    return;
}

$approvalFormKey = 'follow-approve';

/*
 * A note ID identifies an approval action.
 * Approval is permitted only through POST.
 */
if ($noteId > 0) {
    if ($requestMethod !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Please use the Allow button on Notifications.');
    }

    $submittedToken = $_POST['csrf_token'] ?? null;

    if (!csrf_validate($approvalFormKey, is_string($submittedToken) ? $submittedToken : null)) {
        redirect('admin/notes/', [
            'failure' => 'Your form session expired. Please try again.',
        ]);
        exit();
    }

    $note = $cms->getNote()->getById($noteId);

    /*
     * Only the recipient can approve a Follow Request.
     * Messages and unrelated requests cannot be approved here.
     */
    if (
        !$note ||
        (int) $note['note_type'] !== 1 ||
        (int) $note['to_id'] !== $memberId ||
        (int) $note['from_id'] === $memberId
    ) {
        http_response_code(403);
        exit('This Follow Request is not available for approval.');
    }

    if ((int) $note['allow'] !== 0) {
        redirect('admin/notes/', [
            'failure' => 'This Follow Request has already been processed.',
        ]);
        exit();
    }

    $approved = $cms->getNote()->approve($noteId);

    if (!$approved) {
        redirect('admin/notes/', [
            'failure' => 'Unable to approve this Follow Request.',
        ]);
        exit();
    }

    csrf_rotate($approvalFormKey);

    redirect('admin/notes/', [
        'success' => 'Follow Request approved.',
    ]);
    exit();
}

/*
 * The list page itself is read-only.
 */
if ($requestMethod !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit('Please open Notifications to select a request.');
}

/*
 * Load this member's notes, then retain only Follow Requests.
 * Explicit participant checks also protect against unrelated rows.
 */
$memberNotes = $cms->getNote()->getForMember($memberId);

$followRequests = array_values(
    array_filter($memberNotes, static function (array $note) use ($memberId): bool {
        return (int) ($note['note_type'] ?? 0) === 1 &&
            ((int) ($note['from_id'] ?? 0) === $memberId ||
                (int) ($note['to_id'] ?? 0) === $memberId);
    }),
);

$success = $_GET['success'] ?? '';
$failure = $_GET['failure'] ?? '';

$data['member'] = $member;
$data['notes'] = $followRequests;
$data['success'] = is_string($success) ? $success : '';
$data['failure'] = is_string($failure) ? $failure : '';

$data['approval_csrf_token'] = csrf_token($approvalFormKey);
$data['follow_csrf_token'] = csrf_token('follow-account');

$data['website'] = $cms->getWebsite()->getById((int) $member['website']);

echo $twig->render('admin/notes.html', $data);
