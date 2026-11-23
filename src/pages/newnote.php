<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';
guardMember();

require_once APP_ROOT . '/src/security/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit('Please submit requests through the Follow Request form.');
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$targetId = (int) ($parts[1] ?? 0);

if ($viewerId <= 0 || $viewerId === 2 || $targetId <= 0 || $targetId === $viewerId) {
    http_response_code(404);
    include APP_ROOT . '/src/pages/page-not-found.php';
    return;
}

$viewer = $cms->getMember()->get($viewerId);
$target = $cms->getMember()->get($targetId);

if (
    !$viewer ||
    !$target ||
    ($viewer['status'] ?? '') !== 'active' ||
    ($target['status'] ?? '') !== 'active' ||
    $targetId === 2 ||
    in_array(strtolower((string) ($target['role'] ?? '')), ['guest', 'pending'], true)
) {
    http_response_code(404);
    include APP_ROOT . '/src/pages/page-not-found.php';
    return;
}

/*
 * Require an intent created by newnote-gate.php
 * for this viewer and target.
 */
$key = $_GET['k'] ?? '';
$key = is_string($key) ? $key : '';

$intent = $key !== '' ? $_SESSION['newnote_intents'][$key] ?? null : null;

$validIntent =
    is_array($intent) &&
    (int) ($intent['viewer_id'] ?? 0) === $viewerId &&
    (int) ($intent['target_id'] ?? 0) === $targetId &&
    (int) ($intent['target_website'] ?? 0) === (int) $target['website'] &&
    (int) ($intent['exp'] ?? 0) >= time();

if (!$validIntent) {
    http_response_code(403);
    exit('Please start a new request from the Follow page.');
}

// Consume the intent after validation.
// The submitted form uses its own CSRF token.
unset($_SESSION['newnote_intents'][$key]);

$data['member'] = $viewer;
$data['to_member'] = $targetId;
$data['from_member'] = $viewerId;

$data['to_membername'] = trim((string) $target['forename'] . ' ' . (string) $target['surname']);

$data['from_membername'] = trim((string) $viewer['forename'] . ' ' . (string) $viewer['surname']);

$data['family_id'] = $viewerId;
$data['to_family_id'] = $targetId;
$data['noteid'] = 1;
$data['description'] = 'Request to Follow';
$data['request'] = 'Request to follow.';
$data['allow'] = 0;
$data['errors'] = [];
$data['success'] = '';

$data['request_csrf_token'] = csrf_token('follow-request-' . $targetId);

$data['website'] = $cms->getWebsite()->getById((int) $viewer['website']);

echo $twig->render('newnote.html', $data);
