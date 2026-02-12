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
// Admin route: /admin/menu/{menuId}
// ------------------------------------------------------------
$menuId = 0;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$path = trim($path, '/');
$parts = explode('/', $path);

// If your app is under /focus-local/public, strip those segments if present
// (only needed if your $parts includes 'focus-local' and 'public')
if (!empty($parts[0]) && $parts[0] === 'focus-local') {
    array_shift($parts); // focus-local
}
if (!empty($parts[0]) && $parts[0] === 'public') {
    array_shift($parts); // public
}

// Now expect: ['admin', 'menu', '{id}']
if (!empty($parts[0]) && $parts[0] === 'admin' && !empty($parts[1]) && $parts[1] === 'menu') {
    if (!empty($parts[2]) && ctype_digit($parts[2])) {
        $menuId = (int) $parts[2];
    }
}

// Optional: also allow /admin/menu?id=9
if ($menuId <= 0 && !empty($_GET['id']) && ctype_digit((string) $_GET['id'])) {
    $menuId = (int) $_GET['id'];
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
    // menuId from POST wins (edit submit)
    if (!empty($_POST['menu_id']) && ctype_digit((string) $_POST['menu_id'])) {
        $menuId = (int) $_POST['menu_id'];
    }
    $existing = null;

    if ($menuId > 0) {
        $existing = $cms->getMenu()->get($menuId);
    }
    $isEdit = $menuId > 0;

    if ($isEdit) {
        // EDIT MODE
        $menu = $existing; // baseline for template + for update
        $data['menu'] = $menu;

        // Freeze immutable fields (defensive)
        $menu['id'] = (int) $existing['id'];
        $menu['website'] = (int) $existing['website'];
        $menu['account_id'] = (int) $existing['account_id'];
    } else {
        // CREATE MODE
        // $menu = [];
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

    $defaultSorttypeId = (int) ($_POST['default_sorttype_id'] ?? 0);
    $menu['default_sorttype_id'] = $defaultSorttypeId;

    if ($defaultSorttypeId <= 0) {
        $errors['default_sorttype_id'] = 'Please select a default sort type.';
    } elseif (!$cms->getSorttype()->exists($defaultSorttypeId)) {
        $errors['default_sorttype_id'] = 'Invalid sort type selected.';
    }

    $invalid = false;
    foreach ($errors as $msg) {
        if ($msg !== '') {
            $invalid = true;
            break;
        }
    }

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
                'default_sorttype_id' => $defaultSorttypeId,
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
$menu = $cms->getMenu()->get($menuId);

/*if (!$menu) {
    require __DIR__ . '/../page-not-found.php';
    exit();
}*/

$data['menu'] = $menu;

// ------------------------------------------------------------
// Sorttypes for menu default selection
// ------------------------------------------------------------
$data['sorttypes'] = $cms->getSorttype()->getAll();

$data['errors'] = $errors;
$data['sort_menu_id'] = (int) $menuId;
echo $twig->render('admin/menu.html', $data);
