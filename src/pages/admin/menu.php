<?php
declare(strict_types=1);
use PhpBook\Validate\Validate;

include APP_ROOT . '/src/pages/menu-path.php';

// ------------------------------------------------------------
// 0) Must be logged in + load member
// ------------------------------------------------------------
$viewerId = (int) ($_SESSION['id'] ?? 0);
if ($viewerId <= 0) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

$member = $cms->getMember()->get($viewerId);
if (!$member) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

$isUber = $viewerId === 1;
$role = (string) ($member['role'] ?? '');

// You can tighten this later (e.g., status checks). For today:
if (!$isUber && $role !== 'admin') {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

// ------------------------------------------------------------
// 1) Resolve target menu id (edit vs create)
// ------------------------------------------------------------
$menuId = (int) ($id ?? 0);
$isEdit = $menuId > 0;

$menu = null;
$menuWebsiteId = 0;

if ($isEdit) {
    $menu = $cms->getMenu()->get($menuId);
    error_log(
        '[admin/menu GET] route menuId=' .
            $menuId .
            ' loaded menu.id=' .
            (int) ($menu['id'] ?? 0) .
            ' name=' .
            ($menu['name'] ?? 'NA'),
    );

    if (!$menu) {
        include APP_ROOT . '/src/pages/page-not-found.php';
        exit();
    }

    // Authoritative website context comes from the menu itself
    $menuWebsiteId = (int) ($menu['website'] ?? 0);
    if ($menuWebsiteId <= 0) {
        $menuWebsiteId = 1;
    }

    // ------------------------------------------------------------
    // 2) Authorization: member website must match menu website (unless uber)
    // ------------------------------------------------------------
    $memberWebsiteId = (int) ($member['website'] ?? 0);
    if (!$isUber && $memberWebsiteId !== $menuWebsiteId) {
        include APP_ROOT . '/src/pages/page-not-found.php';
        exit();
    }
} else {
    // Create mode: website context comes from member (or session if you prefer),
    // but DO NOT allow arbitrary website selection for normal admins.
    $menuWebsiteId = (int) ($member['website'] ?? 1);
    if ($menuWebsiteId <= 0) {
        $menuWebsiteId = 1;
    }
}

// Safe to align session now (prevents “wrong-website nav” issues)
$_SESSION['website'] = $menuWebsiteId;

// Load website (for template context)
$website = $cms->getWebsite()->getById($menuWebsiteId);
if (!$website) {
    $menuWebsiteId = 1;
    $_SESSION['website'] = 1;
    $website = $cms->getWebsite()->getById(1);
}

// ------------------------------------------------------------
// 3) POST handling (freeze website + other immutables)
// ------------------------------------------------------------
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Re-load existing menu for edit to prevent forged POST / session bleed
    $existing = null;
    if ($isEdit) {
        $existing = $cms->getMenu()->get($menuId);
        if (!$existing) {
            include APP_ROOT . '/src/pages/page-not-found.php';
            exit();
        }

        // Re-authorize using existing menu website (not session)
        $existingWebsiteId = (int) ($existing['website'] ?? 0);
        if ($existingWebsiteId <= 0) {
            $existingWebsiteId = 1;
        }

        if (!$isUber && (int) ($member['website'] ?? 0) !== $existingWebsiteId) {
            include APP_ROOT . '/src/pages/page-not-found.php';
            exit();
        }
    }

    // Build $menu from POST (adjust keys to match your form fields)
    $menu = [
        'id' => $menuId,
        'name' => trim((string) ($_POST['name'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'navigation' => isset($_POST['navigation']) ? 1 : 0,
        'position' => (int) ($_POST['position'] ?? 0),
        // do NOT take website/account_id from session here; they get frozen below
    ];
    // ------------------------------------------------------------
    // Finalize menu fields (EDIT vs CREATE)
    // ------------------------------------------------------------

    // Always required (already validated earlier)
    $menu['seo_name'] = create_seo_name($menu['name']);

    if ($isEdit && $existing) {
        // ----- EDIT MODE -----
        // Freeze immutable fields from existing record
        $menu['id'] = (int) $existing['id'];
        $menu['website'] = (int) $existing['website'];
        $menu['account_id'] = (int) $existing['account_id'];
    } else {
        // ----- CREATE MODE -----
        // Assign from current member context
        // $menu['id'] = 0; // auto-increment
        $menu['website'] = (int) ($member['website'] ?? 1);
        $menu['account_id'] = (int) ($member['account_id'] ?? $viewerId);
    }

    // Validate (minimal today)
    if ($menu['name'] === '') {
        $errors['name'] = 'Name is required.';
    }
    if ($menu['position'] <= 0) {
        $errors['position'] = 'Position must be 1 or greater.';
    }
    $invalid = false;
    foreach ($errors as $msg) {
        if ($msg !== '') {
            $invalid = true;
            break;
        }
    }
    assert(
        count(
            array_diff(
                [
                    'id',
                    'website',
                    'name',
                    'description',
                    'navigation',
                    'position',
                    'seo_name',
                    'account_id',
                ],
                array_keys($menu),
            ),
        ) === 0,
    );

    if (!$invalid) {
        if ($isEdit) {
            $saved = $cms->getMenu()->update($menu);
        } else {
            $createParams = [
                'website' => (int) $menu['website'],
                'name' => (string) $menu['name'],
                'description' => (string) $menu['description'],
                'navigation' => (int) $menu['navigation'],
                'account_id' => (int) $menu['account_id'],
                'seo_name' => (string) $menu['seo_name'],
                'position' => (int) $menu['position'],
            ];

            $saved = $cms->getMenu()->create($createParams);
        }

        if ($saved) {
            redirect('admin/menus/', ['success' => 'Menu saved']);
        } else {
            $errors['message'] = 'Save failed.';
        }
    }
}

// ------------------------------------------------------------
// 4) Template context + render
// ------------------------------------------------------------
$data = [];
$data['session'] = $_SESSION;
$data['member'] = $member;
$data['website'] = $website;
$data['menu'] = $menu;
$data['errors'] = $errors;
$data['sort_menu_id'] = (int) $menuId;
echo $twig->render('admin/menu.html', $data);
