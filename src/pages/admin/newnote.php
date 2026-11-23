<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';
guardMember();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit('Please start a Follow Request from the Follow page.');
}

$targetId = (int) ($parts[2] ?? 0);
$viewerId = (int) ($_SESSION['id'] ?? 0);

$destination = $targetId > 0 ? 'newnote-gate/' . $targetId : 'admin/follow/' . $viewerId;

header('Location: ' . DOC_ROOT . $destination, true, 303);
exit();
