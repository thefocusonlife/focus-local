<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';
guardMember();

require_once APP_ROOT . '/src/security/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Please use the Delete button to delete a message.');
}

$memberId = (int) ($_SESSION['id'] ?? 0);
$messageId = (int) ($parts[1] ?? 0);
$csrfFormKey = 'message-delete-inbox';
$submittedToken = $_POST['csrf_token'] ?? null;

if ($memberId <= 0 || $memberId === 2 || $messageId <= 0) {
    redirect('inbox/', ['failure' => 'Message not found.']);
    exit();
}

if (!csrf_validate($csrfFormKey, is_string($submittedToken) ? $submittedToken : null)) {
    redirect('inbox/', [
        'failure' => 'Your form session expired. Please try again.',
    ]);
    exit();
}

$deleted = $cms->getNote()->deleteFromInbox($messageId, $memberId);

if (!$deleted) {
    redirect('inbox/', ['failure' => 'Message not found or already deleted.']);
    exit();
}

csrf_rotate($csrfFormKey);

redirect('inbox/', ['success' => 'Message deleted.']);
exit();
