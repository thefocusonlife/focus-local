<?php
declare(strict_types=1);

require_once __DIR__ . '/../security/guard.php';
guardMember();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

is_admin($session->role);

$menuId = (int) ($id ?? 0);
if ($menuId <= 0) {
    redirect('admin/menus/', ['failure' => 'Menu not found']);
}

// Load menu (this is the record we will update)
$menu = $cms->getMenu()->get($menuId);
if (!$menu || !isset($menu['id'])) {
    redirect('admin/menus/', ['failure' => 'Menu not found']);
}

// Ownership/website guard (Uber bypass)
$sessionId = (int) ($_SESSION['id'] ?? 0);
if ($sessionId !== 1) {
    $member = $cms->getMember()->get($sessionId);
    if (!$member || !isset($member['id'])) {
        redirect('admin/menus/', ['failure' => 'Not allowed']);
    }

    $memAccountId = (int) ($member['account_id'] ?? 0);

    $sessionWebsiteId = (int) ($_SESSION['website'] ?? 1);
    if ($sessionWebsiteId <= 0) {
        $sessionWebsiteId = 1;
        $_SESSION['website'] = 1;
    }

    $menuWebsiteId = (int) ($menu['website'] ?? 0);
    $menuAccountId = (int) ($menu['account_id'] ?? 0);

    // Normal admins can only edit menus in their current website + their family/account
    if ($menuWebsiteId !== $sessionWebsiteId || $menuAccountId !== $memAccountId) {
        redirect('admin/menus/', ['failure' => 'Not allowed']);
    }
}

// Website data for header/context
$websiteId = (int) ($_SESSION['website'] ?? 1);
if ($websiteId <= 0) {
    $websiteId = 1;
    $_SESSION['website'] = 1;
}
$website = $cms->getWebsite()->getById($websiteId);

// Families list (same list you were already using)
$accountId = (int) ($_SESSION['account_id'] ?? 0);
$families = $cms->getMember()->getAll3($accountId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((int) ($_SESSION['id'] ?? 0) !== 1) {
        redirect('admin/menus/', ['failure' => 'Not allowed']);
    }

    // Your form appears to post a member.id (e.g., 4), not an account_id
    $selectedMemberId = (int) ($_POST['member_id'] ?? 0);
    if ($selectedMemberId <= 0) {
        redirect('admin/menu-family/' . $menuId, ['failure' => 'Invalid selection']);
    }

    $selectedMember = $cms->getMember()->get($selectedMemberId);
    $newAccountId = (int) ($selectedMember['id'] ?? 0);
    if ($newAccountId <= 0) {
        redirect('admin/menu-family/' . $menuId, ['failure' => 'Invalid family selection']);
    }

    $ok = $cms->getMenu()->updateAccountId($menuId, $newAccountId);
    if (!$ok) {
        redirect('admin/menu-family/' . $menuId, ['failure' => 'Update failed']);
    }

    redirect('admin/menus/', ['success' => 'Family updated']);
}

$data = [];
$data['menu'] = $menu;
$data['website'] = $website;
$data['families'] = $families;
$data['members'] = $families; // keep your template name stable
$data['member'] = $cms->getMember()->get($sessionId); // current logged-in user (for header, if used)

echo $twig->render('admin/menu-family.html', $data);
