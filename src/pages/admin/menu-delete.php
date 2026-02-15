<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/security/guard.php';
is_admin($session->role); // admin only

$menuId = (int) ($id ?? 0);
if ($menuId <= 0) {
    redirect('admin/menus/', ['failure' => 'Menu not found']);
}

$sessionId = (int) ($_SESSION['id'] ?? 0);

// Website context must be valid. No fallbacks.
$websiteId = (int) ($_SESSION['website'] ?? 0);
if ($websiteId <= 0) {
    redirect('admin/menus/', ['failure' => 'Invalid website context']);
}

// Load menu using tenant-safe fetch
$menu = $cms->getMenu()->getForWebsite($menuId, $websiteId);
if (!$menu || !isset($menu['id'])) {
    // Hide cross-tenant existence as "not found"
    redirect('admin/menus/', ['failure' => 'Menu not found']);
}

// Ownership guard (UberAdmin bypass)
if ($sessionId !== 1) {
    $member = $cms->getMember()->get($sessionId);
    $memAccountId = (int) ($member['account_id'] ?? 0);

    $menuAccountId = (int) ($menu['account_id'] ?? 0);

    // Website already guaranteed by getForWebsite(); now enforce account ownership
    if ($menuAccountId !== $memAccountId) {
        redirect('admin/menus/', ['failure' => 'Not allowed']);
    }
}

$deleted = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Tenant-safe delete
    $deleted = $cms->getMenu()->deleteForWebsite($menuId, $websiteId);

    if ($deleted === true) {
        redirect('admin/menus/', ['success' => 'Menu deleted']);
    }

    // If row wasn't deleted because of FK constraint, deleteForWebsite returns false (1451)
    // If row wasn't deleted because it didn't match tenant, we already blocked earlier.
    redirect('admin/menus/', [
        'failure' =>
            'Menu contains stories that must be moved or deleted before you can delete the menu',
    ]);
}

$data = [];
$data['menu'] = $menu;

// Website is already known-good via websiteId, no reassignment.
$data['website'] = $cms->getWebsite()->getById($websiteId);

echo $twig->render('admin/menu-delete.html', $data);
