<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';
guardMember();
require_once APP_ROOT . '/src/security/csrf.php';
$memberId = (int) ($_SESSION['id'] ?? 0);
$messageId = !empty($parts[1]) ? (int) $parts[1] : 0;

if ($memberId <= 0 || $messageId <= 0) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    return;
}

$member = $cms->getMember()->get($memberId);

if (!$member) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    return;
}

$message = $cms->getNote()->getMessageForMember($messageId, $memberId);

if (!$message) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    return;
}

// Mark message as read
if ((int) $message['to_id'] === $memberId && (int) $message['allow'] === 0) {
    if ($cms->getNote()->markRead($messageId, $memberId)) {
        $message['allow'] = 1;
    }
}

$data['member'] = $member;
$data['message'] = $message;
$data['website'] = $cms->getWebsite()->getById((int) $member['website']);
$isRecipient = (int) $message['to_id'] === $memberId;

$data['delete_csrf_token'] = csrf_token(
    $isRecipient ? 'message-delete-inbox' : 'message-delete-sent',
);

$data['back_href'] = $isRecipient ? 'inbox/' : 'sent/';
$data['back_label'] = $isRecipient ? 'Back to Inbox' : 'Back to Sent';

echo $twig->render('message.html', $data);
