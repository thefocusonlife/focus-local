<?php
declare(strict_types=1);
use PhpBook\CMS\Exceptions\ForeignKeyConstraintException;

// ------------------------------------------------------------
// 1) Session + Auth Enforcement
// ------------------------------------------------------------

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$data = $data ?? []; // ensure array exists

// Load flash failure if set
if (!empty($_SESSION['flash_failure'])) {
    $data['flash_failure'] = $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

// Load flash success if set
if (!empty($_SESSION['flash_success'])) {
    $data['flash_success'] = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Must be logged in
if (!isset($_SESSION['id']) || !$_SESSION['id']) {
    redirect('login/', ['failure' => 'Please log in to continue']);
    exit();
}

// Must be admin or uber
if (!is_admin($_SESSION['role'])) {
    redirect('admin/menus/', ['failure' => 'Not allowed']);
    exit();
}

// ------------------------------------------------------------
// 2) Extract menuId from route parts
// ------------------------------------------------------------

$menuId = (int) ($id ?? 0);

if ($menuId <= 0) {
    $p0 = (string) ($parts[0] ?? '');
    $p1 = (string) ($parts[1] ?? '');
    $p2 = (string) ($parts[2] ?? '');

    if ($p0 === 'admin' && $p1 === 'menu-delete' && $p2 !== '' && ctype_digit($p2)) {
        $menuId = (int) $p2;
    }
}

if ($menuId <= 0) {
    redirect('admin/menus/', ['failure' => 'Menu not found']);
    exit();
}

// ------------------------------------------------------------
// 3) Load Menu (tenant-safe)
// ------------------------------------------------------------

$websiteId = (int) ($_SESSION['website'] ?? 0);
if ($websiteId <= 0) {
    redirect('admin/menus/', ['failure' => 'Invalid website context']);
    exit();
}

$menu = $cms->getMenu()->getForWebsite($menuId, $websiteId);
if (!$menu || !isset($menu['id'])) {
    redirect('admin/menus/', ['failure' => 'Menu not found']);
    exit();
}

// ------------------------------------------------------------
// 4) Ownership Logic (creator-based, not account)
// ------------------------------------------------------------

$sessionId = (int) ($_SESSION['id'] ?? 0);

// UberAdmin bypass
if ($sessionId !== 1) {
    $member = $cms->getMember()->get($sessionId);
    $memberId = (int) ($member['id'] ?? 0);
    $menuCreatedBy = (int) ($menu['account_id'] ?? 0);

    if ($menuCreatedBy !== $memberId) {
        redirect('admin/menus/', ['failure' => 'Not allowed']);
        exit();
    }
}

// ------------------------------------------------------------
// 5) Delete logic (POST only)
// ------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection optional but recommended if you have a helper.
    if (isset($_POST['csrf']) && function_exists('verify_csrf') && !verify_csrf($_POST['csrf'])) {
        redirect('admin/menus/', ['failure' => 'Invalid request']);
        exit();
    }
    /*
    try {
        $rowsDeleted = $cms->getMenu()->deleteForWebsite($menuId, $websiteId);
        if ($rowsDeleted > 0) {
            redirect('admin/menus/', ['success' => 'Menu deleted']);
            exit();
        }
        redirect('admin/menus/', ['success' => 'Menu was already deleted']);
        exit();
    } catch (\Throwable $e) {
        error_log('Caught exception class: ' . get_class($e));
        error_log('Exception message: ' . $e->getMessage());
        die('DEBUG: exception caught — check error.log for details.');
    }
}
*/

    try {
        $rowsDeleted = $cms->getMenu()->deleteForWebsite($menuId, $websiteId);

        if ($rowsDeleted > 0) {
            redirect('admin/menus/', ['success' => 'Menu deleted']);
            exit();
        }

        redirect('admin/menus/', ['success' => 'Menu was already deleted']);
        exit();
    } catch (ForeignKeyConstraintException $e) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['flash_failure'] = 'Menu cannot be deleted: it contains dependent stories.';

        // Check if headers already sent
        if (headers_sent($file, $line)) {
            error_log("HEADERS ALREADY SENT at $file:$line");
        } else {
            error_log('HEADERS NOT SENT — safe to redirect');
        }
        error_log('MENU-DELETE SESSION ID: ' . session_id());

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['flash_failure'] =
            'Menu cannot be deleted: it contains stories that must be moved or removed first.';

        error_log(
            'MENU-DELETE SET FLASH: ' .
                session_id() .
                ' => ' .
                print_r($_SESSION['flash_failure'], true),
        );

        header('Location: ' . DOC_ROOT . 'admin/menus/');
        exit();
    } catch (\PDOException $e) {
        redirect('admin/menus/', ['failure' => 'Database error during delete']);
        exit();
    }
}

// ------------------------------------------------------------
// 6) Render Confirmation Form
// ------------------------------------------------------------

$data = [];
$data['menu'] = $menu;
$data['website'] = $cms->getWebsite()->getById($websiteId);

echo $twig->render('admin/menu-delete.html', $data);
