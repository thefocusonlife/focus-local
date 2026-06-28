<?php
declare(strict_types=1);

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

$data['member'] = $member;
$data['messages'] = $cms->getNote()->getSent($memberId);
$data['success'] = $_GET['success'] ?? null;
$data['failure'] = $_GET['failure'] ?? null;
$data['website'] = $cms->getWebsite()->getById((int) $member['website']);

echo $twig->render('sent.html', $data);
