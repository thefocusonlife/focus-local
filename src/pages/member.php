<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/security/guard.php';
require_once APP_ROOT . '/src/security/redirects.php';
require_once APP_ROOT . '/src/security/csrf.php';

guardMember();

$viewerId = (int) ($_SESSION['id'] ?? 0);

if ($viewerId <= 0 || $viewerId === 2) {
    redirect('login');
    exit();
}

$viewer = $cms->getMember()->get($viewerId);

if (!$viewer || empty($viewer['id'])) {
    http_response_code(404);
    include APP_ROOT . '/src/pages/page-not-found.php';
    return;
}

$ownerId = (int) ($viewer['account_id'] ?? 0);

if ($ownerId <= 0) {
    $ownerId = $viewerId;
}

$isFollowing = $ownerId !== $viewerId;
$ownerMember = $viewer;

if ($isFollowing) {
    $approved = false;

    foreach ($cms->getNote()->getApprovedFollowsFromMember($viewerId) as $request) {
        if ((int) $request['to_id'] === $ownerId) {
            $approved = true;
            break;
        }
    }

    $ownerMember = $cms->getMember()->get($ownerId);

    if (!$approved || !$ownerMember || ($ownerMember['status'] ?? '') !== 'active') {
        redirect('admin/notes/', [
            'failure' => 'This Follow connection is unavailable. Return to your account.',
        ]);
        exit();
    }
}

/*
 * Display context is separate from authenticated session context.
 * Following does not change session.id or session.website.
 */
$websiteId = $isFollowing
    ? (int) $ownerMember['website']
    : (int) ($_SESSION['website'] ?? $viewer['website']);

if ($websiteId <= 0) {
    $websiteId = (int) $viewer['website'];
}

$website = $cms->getWebsite()->getById($websiteId);

if (!$website) {
    http_response_code(404);
    include APP_ROOT . '/src/pages/page-not-found.php';
    return;
}

$sorttypeId = (int) ($_SESSION['sort_override_global'][$websiteId] ?? 0);

if ($sorttypeId <= 0) {
    $sorttypeId = (int) ($viewer['sorttype'] ?? 0);
}

if ($sorttypeId <= 0) {
    $sorttypeId = (int) ($website['sorttype'] ?? 1);
}

$sorttypeId = max(1, $sorttypeId);

$data = [];
$data['session'] = $_SESSION;

/*
 * Keep member as the authenticated viewer for layout compatibility.
 * profile_member identifies the person whose grid is displayed.
 */
$data['member'] = $viewer;
$data['viewer'] = $viewer;
$data['profile_member'] = $ownerMember;
$data['follow_owner'] = $ownerMember;
$data['is_following'] = $isFollowing;
$data['read_only_grid'] = $isFollowing;

$data['website'] = $website;
$data['sorttype'] = $cms->getSorttype()->get($sorttypeId);
$data['current_path'] = 'member/' . $viewerId;
$data['current_menu_id'] = null;
$data['follow_csrf_token'] = csrf_token('follow-account');

foreach (['success', 'failure'] as $key) {
    $value = $_GET[$key] ?? '';
    $data[$key] = is_string($value) ? $value : '';
}

$data['navigation'] = $cms->getMenu()->getAll2($websiteId, $ownerId);

/*
 * A followed grid always stays scoped to the followed member.
 * Story visibility remains enforced by getAll2().
 */
if ($isFollowing) {
    $data['stories'] = $cms->getStory()->getAll2($websiteId, 0, null, $ownerId, 150, $sorttypeId);
} elseif ($viewerId === 1) {
    $data['stories'] = $cms->getStory()->getAll($websiteId, null, null);
} elseif ((int) ($parts[2] ?? 0) === 1) {
    $data['stories'] = $cms->getStory()->getAll3($websiteId, true, null, null);
} else {
    $data['stories'] = $cms->getStory()->getAll2($websiteId, 0, null, $viewerId, 150, $sorttypeId);
}

$sortMenuId = 0;

foreach ($cms->getMenu()->getAll2($websiteId, 1) as $menu) {
    $name = strtolower(trim((string) ($menu['name'] ?? '')));

    if ($name === 'focus' || $name === 'sort') {
        $sortMenuId = (int) $menu['id'];
        break;
    }
}

if ($sortMenuId <= 0 && $websiteId === 1) {
    $sortMenuId = 2;
}

$data['sort_menu_id'] = $sortMenuId;

echo $twig->render('member.html', $data);
