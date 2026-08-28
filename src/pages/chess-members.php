<?php
declare(strict_types=1);

$websiteId = 51;

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

$isLoggedIn = $viewerId > 0 && $viewerId !== 2 && $role !== 'guest';

if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = $websiteId;
    $_SESSION['return_to'] = DOC_ROOT . 'chess-members';
    $_SESSION['member_required_reason'] =
        'The Chess Membership List is available to active Central Oregon Chess members.';

    header('Location: ' . DOC_ROOT . 'login/51');
    exit();
}

$isUberAdmin = $viewerId === 1;

if (!$isUberAdmin) {
    $belongsToChessWebsite = $cms->getMember()->isMemberOfWebsite($viewerId, $websiteId);

    $viewerMembership = $cms->getClubMembers()->getByMemberId($viewerId, $websiteId);

    $hasActiveMembership =
        $viewerMembership !== null &&
        (string) ($viewerMembership['membership_status'] ?? '') === 'active';

    if (!$belongsToChessWebsite || !$hasActiveMembership) {
        $_SESSION['flash_failure'] =
            'The Membership List is available only to active Central Oregon Chess members.';

        redirect('index/51');
        exit();
    }
}

$_SESSION['website'] = $websiteId;
$_SESSION['websiteid'] = $websiteId;
$_SESSION['menu_website'] = $websiteId;

$membersStmt = $cms->getClubMembers()->getActiveDirectory($websiteId);

$members = $membersStmt instanceof PDOStatement ? $membersStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$website = $cms->getWebsite()->getById($websiteId);

$data = [];
$data['navigation'] = $cms->getMenu()->getAll2($websiteId, $viewerId);
$data['website'] = $website;
$data['website_id'] = $websiteId;
$data['members'] = $members;
$data['session'] = $_SESSION;
$data['doc_root'] = $config['doc_root'] ?? DOC_ROOT;

echo $twig->render('chess-members.html', $data);
exit();
