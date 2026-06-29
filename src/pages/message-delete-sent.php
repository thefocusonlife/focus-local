<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';
guardMember();

$memberId = (int) ($_SESSION['id'] ?? 0);
$messageId = !empty($parts[1]) ? (int) $parts[1] : 0;

if ($memberId <= 0 || $messageId <= 0) {
    redirect('inbox/', ['failure' => 'Message not found.']);
}
$message = $cms->getNote()->getMessageForMember($messageId, $memberId);

if (!$message) {
    redirect('sent/', ['failure' => 'Message not found.']);
}

if ((int) $message['from_id'] === $memberId) {
    $cms->getNote()->deleteFromSent($messageId);
}

redirect('sent/', ['success' => 'Message deleted.']);
