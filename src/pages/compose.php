<?php
declare(strict_types=1);

use PhpBook\Validate\Validate;

require_once APP_ROOT . '/src/security/guard.php';
guardMember();

$memberId = (int) ($_SESSION['id'] ?? 0);

if ($memberId <= 0) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    return;
}

$member = $cms->getMember()->get($memberId);

if (!$member) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    return;
}

$errors = [
    'warning' => '',
    'to_id' => '',
    'request' => '',
];

$message = [
    'to_id' => 0,
    'request' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message['to_id'] = (int) ($_POST['to_id'] ?? 0);
    $message['request'] = trim((string) ($_POST['request'] ?? ''));

    $toMember = $cms->getMember()->get($message['to_id']);

    if (!$toMember) {
        $errors['to_id'] = 'Please choose a valid member.';
    }

    $errors['request'] = Validate::isText($message['request'], 1, 1000)
        ? ''
        : 'Message should be between 1 and 1000 characters.';

    $invalid = implode($errors);

    if ($invalid) {
        $errors['warning'] = 'Please correct the form errors.';
    } else {
        $fromName = trim(($member['forename'] ?? '') . ' ' . ($member['surname'] ?? ''));
        $toName = trim(($toMember['forename'] ?? '') . ' ' . ($toMember['surname'] ?? ''));

        $newMessage = [
            'website' => (int) ($member['website'] ?? 1),
            'from_id' => (int) $member['id'],
            'from_name' => $fromName,
            'to_id' => (int) $toMember['id'],
            'to_name' => $toName,
            'family_id' => (int) ($member['account_id'] ?? 0),
            'to_family_id' => (int) ($toMember['account_id'] ?? 0),
            'request' => $message['request'],
        ];

        $created = $cms->getNote()->createMessage($newMessage);

        if ($created) {
            redirect('sent/', ['success' => 'Message sent']);
        }

        $errors['warning'] = 'Unable to send message.';
    }
}

$data['member'] = $member;
$data['members'] = $cms->getMember()->getMessageRecipients();
$data['message'] = $message;
$data['errors'] = $errors;
$data['website'] = $cms->getWebsite()->getById((int) $member['website']);

echo $twig->render('compose.html', $data);
