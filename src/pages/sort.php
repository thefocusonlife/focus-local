<?php
declare(strict_types=1);
file_put_contents(
    '/tmp/tfol-sort-hit.log',
    date('c') . ' HIT sort.php id=' . var_export($id ?? null, true) . "\n",
    FILE_APPEND,
);

use PhpBook\Validate\Validate;

$errors = [];

// Menu id comes primarily from the route: /sort/{menuId}
$menuId = (int) ($id ?? 0);

// Fallbacks for POST/GET/session (legacy support)
if ($menuId <= 0) {
    $menuId = (int) ($_POST['menu_id'] ?? ($_GET['menu_id'] ?? ($_SESSION['menu_id'] ?? 0)));
}

$menuRow = $cms->getMenu()->get($menuId);
if (!$menuRow) {
    redirect('page-not-found/');
    exit();
}

$websiteId = (int) ($menuRow['website'] ?? 0);
if ($websiteId <= 0) {
    redirect('page-not-found/');
    exit();
}

// Make website context available for nav + layout (guest-safe)
$_SESSION['website'] = $websiteId;

$website = $cms->getWebsite()->getById($websiteId);
if (!$website) {
    redirect('page-not-found/');
    exit();
}

// Keep session website coherent (helps nav + other pages)
$_SESSION['website'] = $websiteId;

$_SESSION['menu_id'] = $menuId; // remember last menu
$isLoggedIn = (int) ($_SESSION['id'] ?? 0) > 0;

$isLoggedIn = (int) ($_SESSION['id'] ?? 0) > 0;

$member = null;
if ($isLoggedIn) {
    $member = $cms->getMember()->get((int) $_SESSION['id']);
    if (!$member) {
        // Session says logged in, but member missing → treat as guest (or force re-auth)
        $isLoggedIn = false;
        $member = null;
        // Optional: unset($_SESSION['id']); // if you want to hard-reset
    }
}

/* ---------- POST: update settings ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pagelimitVal = (int) ($_POST['pagelimit'] ?? 100);

    $menuId = (int) ($_POST['menu_id'] ?? ($_GET['menu_id'] ?? 0));
    if ($menuId <= 0) {
        redirect('page-not-found/');
        exit();
    }

    $incomingSorttype = (int) ($_POST['sorttype'] ?? 0);
    $resolvedSorttype = $cms->getSorttype()->resolveForMenu($menuId, $incomingSorttype);

    if ($isLoggedIn && $member) {
        // Member: persist preferences to member record (your current behavior)
        $member['pagelimit'] = $pagelimitVal;
        $member['sorttype'] = $resolvedSorttype;

        $cms->getMember()->update($member);
        $cms->getSession()->create($member, (int) $member['website']);
        // Always reflect choice into session (so grids/refresh use it immediately)
        $_SESSION['sorttype'] = $resolvedSorttype;
        $_SESSION['sorttype_by_menu'] = $_SESSION['sorttype_by_menu'] ?? [];
        $_SESSION['sorttype_by_menu'][$menuId] = $resolvedSorttype;

        // If pagelimit is member-only, still ok to set session for convenience:
        $_SESSION['pagelimit'] = (int) ($member['pagelimit'] ?? 100);
    } else {
        // Guest: session-only preferences
        $_SESSION['pagelimit'] = $pagelimitVal;
        $_SESSION['sorttype'] = $resolvedSorttype;

        // If you want menu-scoped guest sort (recommended):
        $_SESSION['sorttype_by_menu'] = $_SESSION['sorttype_by_menu'] ?? [];
        $_SESSION['sorttype_by_menu'][$menuId] = $resolvedSorttype;
    }

    $returnTo = (string) ($_POST['return_to'] ?? '');
    if ($returnTo !== '' && str_starts_with($returnTo, '/')) {
        header('Location: ' . $returnTo);
        exit();
    }
    // Redirect target after saving
    $websiteId = (int) ($website['id'] ?? ($_SESSION['website'] ?? 1));

    if (!$isLoggedIn) {
        // Guest: go back to website home grid
        redirect('index/' . $websiteId);
        exit();
    }

    // Member: keep your existing behavior (menu-scoped grid)
    redirect('menu/' . $menuId . '/');
    exit();
}
$websiteId = (int) ($_SESSION['website'] ?? ($_SESSION['menu_website'] ?? 0));
if ($isLoggedIn && $member) {
    $websiteId = (int) ($member['website'] ?? $websiteId);
}
if ($websiteId <= 0) {
    // Fallback: use current $website from menu-path.php if it sets it
    $websiteId = (int) ($website['id'] ?? 1);
}
/* ---------- GET: render form ---------- */

$pagelimit = $cms->getPagelimit()->getAll();
$sorttype = $cms->getSorttype()->getByMenu($menuId);

$activeSorttypeId = $cms
    ->getSorttype()
    ->resolveForMenu(
        $menuId,
        (int) ($isLoggedIn && $member
            ? $member['sorttype'] ??
                ($_SESSION['sorttype_by_menu'][$menuId] ?? ($_SESSION['sorttype'] ?? 0))
            : $_SESSION['sorttype_by_menu'][$menuId] ?? ($_SESSION['sorttype'] ?? 0)),
    );

$data['member'] = $member; // null for guests is OK if your Twig checks it
$data['pagelimit'] = $pagelimit;
$data['sorttype'] = $sorttype;
$data['active_sorttype_id'] = $activeSorttypeId;
$data['menu_id'] = $menuId;

// Website for render (member or guest)
$data['website'] = $cms->getWebsite()->getById($websiteId);
$data['return_to'] = $_SERVER['HTTP_REFERER'] ?? 'menu/' . $menuId . '/';
$data['sort_menu_id'] = (int) $menuId;

echo $twig->render('sort.html', $data);
