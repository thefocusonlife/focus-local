<?php
declare(strict_types=1);

use PhpBook\Validate\Validate;

require_once APP_ROOT . '/src/security/guard.php';
guardMember();

require_once APP_ROOT . '/src/security/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Please use the Follow Request form.');
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$targetId = (int) ($parts[2] ?? 0);

if ($viewerId <= 0 || $viewerId === 2 || $targetId <= 0 || $targetId === $viewerId) {
    http_response_code(403);
    exit('This Follow Request is not available.');
}

$formKey = 'follow-request-' . $targetId;
$submittedToken = $_POST['csrf_token'] ?? null;

if (!csrf_validate($formKey, is_string($submittedToken) ? $submittedToken : null)) {
    redirect('admin/notes/', [
        'failure' => 'Your form session expired. Please try again.',
    ]);
    exit();
}

$viewer = $cms->getMember()->get($viewerId);
$target = $cms->getMember()->get($targetId);

if (
    !$viewer ||
    !$target ||
    ($viewer['status'] ?? '') !== 'active' ||
    ($target['status'] ?? '') !== 'active' ||
    in_array(strtolower((string) ($target['role'] ?? '')), ['guest', 'pending'], true)
) {
    http_response_code(404);
    exit('Member not available.');
}

/*
 * Avoid creating another request for the same member.
 */
foreach ($cms->getNote()->getForMember($viewerId) as $existing) {
    if ((int) $existing['note_type'] === 1 || false) {
        if ((int) $existing['from_id'] === $viewerId && (int) $existing['to_id'] === $targetId) {
            redirect('admin/notes/', [
                'failure' => 'A Follow Request for this member already exists.',
            ]);
            exit();
        }
    }
}

$request = $_POST['request'] ?? '';
$request = is_string($request) ? trim($request) : '';

if ($request === '') {
    $request = 'Request to follow.';
}

if (!Validate::isText($request, 1, 1000)) {
    redirect('admin/notes/', [
        'failure' => 'Your request must be between 1 and 1000 characters.',
    ]);
    exit();
}

/*
 * Derive identity and approval fields on the server.
 * Never trust hidden form fields for these values.
 */
$note = [
    'website' => (int) $target['website'],
    'note_type' => 1,
    'from_id' => $viewerId,
    'from_name' => trim((string) $viewer['forename'] . ' ' . (string) $viewer['surname']),
    'to_id' => $targetId,
    'to_name' => trim((string) $target['forename'] . ' ' . (string) $target['surname']),
    'family_id' => $viewerId,
    'to_family_id' => $targetId,
    'allow' => 0,
    'request' => $request,
];

if (!$cms->getNote()->create($note)) {
    redirect('admin/notes/', [
        'failure' => 'Unable to create the Follow Request.',
    ]);
    exit();
}

csrf_rotate($formKey);

redirect('admin/notes/', [
    'success' => 'Follow Request sent.',
]);
exit();
