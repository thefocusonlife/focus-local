<?php
declare(strict_types=1);
use PhpBook\CMS\Exceptions\ForeignKeyConstraintException;

// ------------------------------------------------------------
// 1) Session + Auth Enforcement
// ------------------------------------------------------------

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function publicSafePath(): string
{
    $websiteId = (int) ($_SESSION['website'] ?? 1);
    if ($websiteId <= 0) {
        $websiteId = 1;
    }
    return 'index/' . $websiteId; // or 'menu/' if that's your public-safe route
}

function logDeny(string $reason, array $ctx = []): void
{
    // Keep it simple and consistent with Week 3 mindset
    $row = [
        'ts' => date('c'),
        'event' => 'DENY',
        'reason' => $reason,
        'member_id' => $_SESSION['id'] ?? null,
        'role' => $_SESSION['role'] ?? 'guest',
        'website' => $_SESSION['website'] ?? null,
        'path' => $_SERVER['REQUEST_URI'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'ctx' => $ctx,
    ];
    error_log(json_encode($row, JSON_UNESCAPED_SLASHES));
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

require_once APP_ROOT . '/src/security/guard.php';

guardAdmin();

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
    logDeny('menu_delete_not_found_or_cross_tenant', [
        'menu_id' => $menuId,
        'website_id' => $websiteId,
    ]);
    $_SESSION['flash_failure'] = 'Access denied.';
    redirect(publicSafePath(), ['failure' => 'Access denied']);
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
    // Treat as a security denial to avoid cross-tenant enumeration
    $_SESSION['flash_failure'] = 'Access denied.';
    // If you have your central security log, call it; otherwise error_log is fine.
    error_log(
        json_encode(
            [
                'ts' => date('c'),
                'event' => 'DENY',
                'reason' => 'menu_delete_not_found_or_cross_tenant',
                'menu_id' => $menuId,
                'website' => $websiteId,
                'member_id' => $_SESSION['member_id'] ?? ($_SESSION['id'] ?? null),
                'role' => $_SESSION['role'] ?? 'guest',
                'path' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ],
            JSON_UNESCAPED_SLASHES,
        ),
    );

    // IMPORTANT: redirect to a public-safe page, not admin
    $sessionWebsiteId = (int) ($_SESSION['website'] ?? 1);
    if ($sessionWebsiteId <= 0) {
        $sessionWebsiteId = 1;
    }

    $_SESSION['flash_failure'] = 'Access denied.';
    error_log('DENY cross-tenant ...'); // keep your log

    redirect('index/' . $sessionWebsiteId);
    exit();
}

// ------------------------------------------------------------
// 4) Ownership Logic (creator-based, not account)
// ------------------------------------------------------------

$sessionId = (int) ($_SESSION['id'] ?? 0);

// UberAdmin bypass
$role = (string) ($_SESSION['role'] ?? 'guest');
$uberBypass = $role === 'uber'; // or use your helper if you have one

if (!$uberBypass) {
    $member = $cms->getMember()->get($sessionId);
    $memberId = (int) ($member['id'] ?? 0);
    $menuCreatedBy = (int) ($menu['account_id'] ?? 0);

    if ($menuCreatedBy !== $memberId) {
        $_SESSION['flash_failure'] = 'Access denied.';
        logDeny('menu_delete_not_owner', [
            'menu_id' => $menuId,
            'created_by' => $menuCreatedBy,
            'member_id' => $memberId,
        ]);
        redirect(publicSafePath(), ['failure' => 'Access denied']);
        exit();
    }
}

// ------------------------------------------------------------
// 5) Delete logic (POST only)
// ------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fail closed: CSRF must exist and verify helper must be available
    if (!function_exists('verify_csrf')) {
        $_SESSION['flash_failure'] = 'Invalid request (CSRF unavailable).';
        redirect('admin/menus/');
        exit();
    }

    $token = (string) ($_POST['csrf'] ?? '');
    if ($token === '' || !verify_csrf($token)) {
        $_SESSION['flash_failure'] = 'Invalid request. Please try again.';
        redirect('admin/menus/');
        exit();
    }

    try {
        error_log('MENU DELETE EXECUTING: menuId=' . $menuId . ' csrf=' . ($_POST['csrf'] ?? ''));
        $rowsDeleted = $cms->getMenu()->deleteForWebsite($menuId, $websiteId);

        if ($rowsDeleted > 0) {
            $_SESSION['flash_success'] = 'Menu deleted.';
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
        $_SESSION['flash_failure'] = $e;
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
$data['csrf_token'] = generate_csrf_token();
echo $twig->render('admin/menu-delete.html', $data);
