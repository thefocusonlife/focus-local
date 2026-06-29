<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';
guardMember();

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
    $cms->getNote()->markRead((int) $message['id']);
    $message['allow'] = 1; // Keep local copy in sync
}

$data['member'] = $member;
$data['message'] = $message;
$data['website'] = $cms->getWebsite()->getById((int) $member['website']);

echo $twig->render('message.html', $data);
