<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';
guardMember();

require_once APP_ROOT . '/src/security/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Please use the Follow or Return to My Account button.');
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$routeMemberId = (int) ($parts[1] ?? 0);
$targetId = (int) ($parts[2] ?? 0);

if ($viewerId <= 0 || $viewerId === 2 || $routeMemberId !== $viewerId || $targetId <= 0) {
    http_response_code(403);
    exit('This account change is not available.');
}

$submittedToken = $_POST['csrf_token'] ?? null;

if (!csrf_validate('follow-account', is_string($submittedToken) ? $submittedToken : null)) {
    redirect('admin/notes/', [
        'failure' => 'Your form session expired. Please try again.',
    ]);
    exit();
}

$member = $cms->getMember()->get($viewerId);

if (!$member) {
    http_response_code(404);
    exit('Member not found.');
}

if ($targetId === $viewerId) {
    // Return to the member's own account.
    $accountId = $viewerId;
} else {
    $target = $cms->getMember()->get($targetId);

    if (!$target || ($target['status'] ?? '') !== 'active') {
        http_response_code(404);
        exit('Member not available.');
    }

    $approved = false;

    foreach ($cms->getNote()->getApprovedFollowsFromMember($viewerId) as $request) {
        if ((int) $request['to_id'] === $targetId) {
            $approved = true;
            break;
        }
    }

    if (!$approved) {
        http_response_code(403);
        exit('An approved Follow Request is required.');
    }

    // Follow the requested member's own account, not whoever they follow.
    $accountId = $targetId;
}

$member['account_id'] = $accountId;
$cms->getMember()->update($member);

csrf_rotate('follow-account');

redirect('member/' . $viewerId, [
    'success' => $targetId === $viewerId ? 'Returned to your account.' : 'Follow account selected.',
]);
exit();
