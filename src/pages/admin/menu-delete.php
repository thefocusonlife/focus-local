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

// Load menu
$menu = $cms->getMenu()->get($menuId);
if (!$menu || !isset($menu['id'])) {
    redirect('admin/menus/', ['failure' => 'Menu not found']);
}

// Ownership/website guard (Uber bypass)
$sessionId = (int) ($_SESSION['id'] ?? 0);
if ($sessionId !== 1) {
    $member = $cms->getMember()->get($sessionId);
    $memAccountId = (int) ($member['account_id'] ?? 0);

    $sessionWebsiteId = (int) ($_SESSION['website'] ?? 1);
    if ($sessionWebsiteId <= 0) {
        $sessionWebsiteId = 1;
        $_SESSION['website'] = 1;
    }

    $menuWebsiteId = (int) ($menu['website'] ?? 0);
    $menuAccountId = (int) ($menu['account_id'] ?? 0);

    if ($menuWebsiteId !== $sessionWebsiteId || $menuAccountId !== $memAccountId) {
        redirect('admin/menus/', ['failure' => 'Not allowed']);
    }
}

$deleted = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deleted = $cms->getMenu()->delete($menuId);

    if ($deleted === true) {
        redirect('admin/menus/', ['success' => 'Menu deleted']);
    }

    if ($deleted === false) {
        redirect('admin/menus/', [
            'failure' =>
                'Menu contains stories that must be moved or deleted before you can delete the menu',
        ]);
    }

    // Defensive fallback
    redirect('admin/menus/', ['failure' => 'Delete failed']);
}

$data = [];
$data['menu'] = $menu;
$data['website'] = $cms->getWebsite()->getById($menu['website']);
echo $twig->render('admin/menu-delete.html', $data);
