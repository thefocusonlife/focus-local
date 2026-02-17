<?php
declare(strict_types=1);

use PhpBook\Validate\Validate;

include APP_ROOT . '/src/pages/menu-path.php';

// ------------------------------------------------------------
// 0) Auth + role
// ------------------------------------------------------------
$viewerId = (int) ($_SESSION['id'] ?? 0);
if ($viewerId <= 0) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

$member = $cms->getMember()->get($viewerId);
if (!$member || empty($member['id'])) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

$isUber = $viewerId === 1;
$role = (string) ($member['role'] ?? '');
if (!$isUber && $role !== 'admin') {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

// ------------------------------------------------------------
// 1) Tenant context (no fallbacks)
// ------------------------------------------------------------
$menuWebsiteId = (int) ($member['website'] ?? 0);
if ($menuWebsiteId <= 0) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

$website = $cms->getWebsite()->getById($menuWebsiteId);
if (!$website || empty($website['id'])) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

$_SESSION['website'] = $menuWebsiteId; // ok if you rely on it elsewhere

// ------------------------------------------------------------
// 2) Resolve menu id once
// ------------------------------------------------------------
$menuId = (int) ($id ?? 0); // /admin/menu/{id}
$errors = [];
$menu = null;

// ------------------------------------------------------------
// 3) Defaults helpers
// ------------------------------------------------------------
$selfPersonalId = (int) ($member['id'] ?? 0);
$selfCurrentAccountId = (int) ($member['account_id'] ?? 0);

$defaultAccountId = $selfCurrentAccountId > 0 ? $selfCurrentAccountId : $selfPersonalId;
if ($defaultAccountId <= 0) {
    $defaultAccountId = 1;
} // last resort (shouldn't happen)

// ------------------------------------------------------------
// 4) POST save
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // allow POST to supply id for edit submit
    $postedMenuId = (int) ($_POST['id'] ?? ($_POST['menu_id'] ?? 0));
    if ($postedMenuId > 0) {
        $menuId = $postedMenuId;
    }

    $isEdit = $menuId > 0;

    // editable fields from POST
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $navigation = isset($_POST['navigation']) ? 1 : 0;
    $position = (int) ($_POST['position'] ?? 0);
    $defaultSorttypeId = (int) ($_POST['default_sorttype_id'] ?? 0);

    // account_id (Family) is editable, but (for now) default to member context if missing
    $accountId = (int) ($_POST['account_id'] ?? $defaultAccountId);
    if ($accountId <= 0) {
        $accountId = $defaultAccountId;
    }

    // validate
    if ($name === '') {
        $errors['name'] = 'Name is required.';
    }

    if ($defaultSorttypeId <= 0) {
        $errors['default_sorttype_id'] = 'Please select a default sort type.';
    } elseif (!$cms->getSorttype()->exists($defaultSorttypeId)) {
        $errors['default_sorttype_id'] = 'Invalid sort type selected.';
    }

    // position default on create
    if (!$isEdit && $position <= 0) {
        $position = (int) $cms->getMenu()->getNextPositionForAccount($menuWebsiteId, $accountId, 5);
        if ($position <= 0) {
            $position = 5;
        }
    }
    if ($position <= 0) {
        $errors['position'] = 'Position must be 1 or greater.';
    }

    $invalid = false;
    foreach ($errors as $msg) {
        if ((string) $msg !== '') {
            $invalid = true;
            break;
        }
    }

    if (!$invalid) {
        if ($isEdit) {
            // must exist + be in tenant
            $existing = $cms->getMenu()->getForWebsite($menuId, $menuWebsiteId);
            if (!$existing || empty($existing['id'])) {
                include APP_ROOT . '/src/pages/page-not-found.php';
                exit();
            }

            // freeze immutables
            $update = $existing;
            $update['id'] = (int) $existing['id'];
            $update['website'] = $menuWebsiteId; // authoritative tenant scope
            // account_id: editable by choice. keep editable:
            $update['account_id'] = $accountId;

            // set editable fields
            $update['name'] = $name;
            $update['description'] = $description;
            $update['navigation'] = $navigation;
            $update['position'] = $position;
            $update['default_sorttype_id'] = $defaultSorttypeId;
            $update['seo_name'] = create_seo_name($name);

            $affected = (int) $cms->getMenu()->update($update);

            // 0 rows affected = OK (no-change save)
            if ($affected >= 0) {
                redirect('admin/menus/', ['success' => 'Menu saved']);
                exit();
            }
            $errors['message'] = 'Save failed.';
        } else {
            // create
            $createParams = [
                'website' => $menuWebsiteId,
                'name' => $name,
                'description' => $description,
                'navigation' => $navigation,
                'account_id' => $accountId,
                'seo_name' => create_seo_name($name),
                'position' => $position,
                'default_sorttype_id' => $defaultSorttypeId,
            ];

            $newId = (int) $cms->getMenu()->create($createParams);
            if ($newId > 0) {
                redirect('admin/menus/', ['success' => 'Menu created']);
                exit();
            }
            $errors['message'] = 'Save failed (no id returned).';
        }
    }

    // if invalid, fall through to render with posted values
    $menu = [
        'id' => $isEdit ? $menuId : 0,
        'website' => $menuWebsiteId,
        'name' => $name,
        'description' => $description,
        'navigation' => $navigation,
        'position' => $position,
        'account_id' => $accountId,
        'default_sorttype_id' => $defaultSorttypeId,
    ];
}

// ------------------------------------------------------------
// 5) GET load (or defaults)
// ------------------------------------------------------------
// ------------------------------------------------------------
// Route param fix for nested admin route:
// /admin/menu/{id} arrives with $id=0 because front-controller sets $id from $parts[1].
// For this page, the numeric id is actually $parts[2].
// ------------------------------------------------------------
if ((int) ($id ?? 0) <= 0) {
    $p0 = (string) ($parts[0] ?? '');
    $p1 = (string) ($parts[1] ?? '');
    $p2 = (string) ($parts[2] ?? '');

    if ($p0 === 'admin' && $p1 === 'menu' && $p2 !== '' && ctype_digit($p2)) {
        $menuId = (int) $p2; // normalize so the rest of the file works unchanged
    }
}
if ($menu === null) {
    if ($menuId > 0) {
        $menu = $cms->getMenu()->getForWebsite($menuId, $menuWebsiteId);
        if (!$menu) {
            include APP_ROOT . '/src/pages/page-not-found.php';
            exit();
        }
    } else {
        $defaultPosition = (int) $cms
            ->getMenu()
            ->getNextPositionForAccount($menuWebsiteId, $defaultAccountId, 5);
        if ($defaultPosition <= 0) {
            $defaultPosition = 5;
        }

        $menu = [
            'id' => 0,
            'website' => $menuWebsiteId,
            'name' => '',
            'description' => '',
            'navigation' => 1,
            'position' => $defaultPosition,
            'account_id' => $defaultAccountId,
            'default_sorttype_id' => 0,
        ];
    }
}

// ------------------------------------------------------------
// 6) Render
// ------------------------------------------------------------
$data = [];
$data['session'] = $_SESSION;
$data['member'] = $member;
$data['website'] = $website;
$data['menu'] = $menu;
$data['sorttypes'] = $cms->getSorttype()->getAll();
$data['errors'] = $errors;

echo $twig->render('admin/menu.html', $data);
