<?php
declare(strict_types=1);

use PhpBook\Validate\Validate;

require_once APP_ROOT . '/src/security/guard.php';
guardMember();

$memberId = (int) ($_SESSION['id'] ?? 0);

if ($memberId <= 0 || $memberId === 2) {
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
    'person_id' => '',
    'to_id' => '',
    'request' => '',
];

$message = [
    'person_id' => 0,
    'to_id' => 0,
    'request' => '',
];

/*
 * Load eligible accounts across all websites.
 * email_master is used only on the server.
 */
$recipientRows = $cms->getMember()->getMessageRecipients();

// Temp logs
error_log(
    'Message recipient query includes ID 3: ' .
        (in_array(3, array_map('intval', array_column($recipientRows, 'id')), true) ? 'YES' : 'NO'),
);
error_log('Compose sender memberId=' . $memberId . '; session id=' . (int) ($_SESSION['id'] ?? 0));

$grouped = [];

foreach ($recipientRows as $recipient) {
    $recipientId = (int) $recipient['id'];

    // Exclude only the account currently sending the message.
    if ($recipientId === $memberId) {
        continue;
    }

    $master = mb_strtolower(trim((string) ($recipient['email_master'] ?? '')), 'UTF-8');

    // Never combine unrelated accounts with blank master identities.
    $groupKey = $master !== '' ? 'master:' . $master : 'member:' . $recipientId;

    if (!isset($grouped[$groupKey])) {
        $grouped[$groupKey] = [
            'id' => $recipientId,
            'name' => trim((string) $recipient['forename'] . ' ' . (string) $recipient['surname']),
            'surname' => (string) $recipient['surname'],
            'forename' => (string) $recipient['forename'],
            'accounts' => [],
        ];
    }

    $grouped[$groupKey]['accounts'][] = [
        'id' => $recipientId,
        'name' => trim((string) $recipient['forename'] . ' ' . (string) $recipient['surname']),
        'website_name' => (string) $recipient['website_name'],
    ];
}

$people = array_values($grouped);

foreach ($people as &$person) {
    usort($person['accounts'], static function (array $a, array $b): int {
        return strnatcasecmp($a['website_name'], $b['website_name']) ?:
            strnatcasecmp($a['name'], $b['name']) ?:
            $a['id'] <=> $b['id'];
    });
}
unset($person);

usort($people, static function (array $a, array $b): int {
    return strnatcasecmp($a['surname'], $b['surname']) ?:
        strnatcasecmp($a['forename'], $b['forename']) ?:
        $a['id'] <=> $b['id'];
});

/*
 * Index eligible accounts for POST validation and reply selection.
 */
$accountToPerson = [];

foreach ($people as $person) {
    foreach ($person['accounts'] as $account) {
        $accountToPerson[$account['id']] = $person['id'];
    }
}

$replyToId = (int) ($_GET['reply_to'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $replyToId > 0) {
    $replyToMessage = $cms->getNote()->getMessageForMember($replyToId, $memberId);

    if ($replyToMessage) {
        // Also handle replying from a message in Sent.
        $otherMemberId =
            (int) $replyToMessage['from_id'] === $memberId
                ? (int) $replyToMessage['to_id']
                : (int) $replyToMessage['from_id'];

        if (isset($accountToPerson[$otherMemberId])) {
            $message['to_id'] = $otherMemberId;
            $message['person_id'] = $accountToPerson[$otherMemberId];
        } else {
            $errors['warning'] =
                'The original recipient is no longer available. ' .
                'Please choose another recipient.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message['person_id'] = (int) ($_POST['person_id'] ?? 0);
    $message['to_id'] = (int) ($_POST['to_id'] ?? 0);
    $message['request'] = trim((string) ($_POST['request'] ?? ''));

    $personIds = array_column($people, 'id');

    if (!in_array($message['person_id'], $personIds, true)) {
        $errors['person_id'] = 'Please choose a valid person.';
    }

    $validRecipient =
        isset($accountToPerson[$message['to_id']]) &&
        $accountToPerson[$message['to_id']] === $message['person_id'];

    $toMember = false;

    if ($validRecipient) {
        $toMember = $cms->getMember()->get($message['to_id']);
    }

    if (!$validRecipient || !$toMember) {
        $errors['to_id'] = 'Please choose an available account for this person.';
    }

    $errors['request'] = Validate::isText($message['request'], 1, 1000)
        ? ''
        : 'Message should be between 1 and 1000 characters.';

    if (implode($errors) !== '') {
        $errors['warning'] = 'Please correct the form errors.';
    } else {
        $fromName = trim(($member['forename'] ?? '') . ' ' . ($member['surname'] ?? ''));

        $toName = trim(($toMember['forename'] ?? '') . ' ' . ($toMember['surname'] ?? ''));

        $newMessage = [
            // Preserve the existing sender-website meaning.
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
            exit();
        }

        $errors['warning'] = 'Unable to send message.';
    }
}

/*
 * Build browser data explicitly: no email/master identity included.
 */
$recipientPeople = [];

foreach ($people as $person) {
    $recipientPeople[] = [
        'id' => $person['id'],
        'name' => $person['name'],
        'accounts' => $person['accounts'],
    ];
}
//temp test
foreach ([3, 4] as $checkId) {
    $found = false;

    foreach ($recipientPeople as $person) {
        foreach ($person['accounts'] as $account) {
            if ((int) $account['id'] === $checkId) {
                $found = true;

                error_log(
                    'Recipient check: account=' .
                        $checkId .
                        '; first selector=' .
                        $person['name'] .
                        '; person_id=' .
                        $person['id'] .
                        '; second selector=' .
                        $account['name'] .
                        ' — ' .
                        $account['website_name'],
                );
            }
        }
    }

    if (!$found) {
        error_log('Recipient check: account=' . $checkId . ' MISSING; sender=' . $memberId);
    }
}

$data['member'] = $member;
$data['recipient_people'] = $recipientPeople;
$data['message'] = $message;
$data['errors'] = $errors;
$data['cancel_href'] = $replyToId > 0 ? 'message/' . $replyToId : 'inbox';

$data['website'] = $cms->getWebsite()->getById((int) $member['website']);

echo $twig->render('compose.html', $data);
