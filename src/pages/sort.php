<?php
declare(strict_types=1);
file_put_contents(
    '/tmp/tfol-sort-hit.log',
    date('c') . ' HIT sort.php id=' . var_export($id ?? null, true) . "\n",
    FILE_APPEND,
);

use PhpBook\Validate\Validate;
require_once APP_ROOT . '/src/security/guard.php';
guardPublic();
$errors = [];

// Menu id comes primarily from the route: /sort/{menuId}
file_put_contents(
    '/tmp/tfol-sort-hit.log',
    date('c') .
        ' URI=' .
        ($_SERVER['REQUEST_URI'] ?? '') .
        ' id=' .
        var_export($id ?? null, true) .
        "\n",
    FILE_APPEND,
);

$return = (string) ($_GET['return'] ?? '');
if ($return !== '' && str_starts_with($return, 'http')) {
    $return = '';
}

$menuId = (int) ($id ?? 0);

file_put_contents('/tmp/tfol-sort-hit.log', date('c') . ' menuId=' . $menuId . "\n", FILE_APPEND);

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
// ------------------------------------------------------------
// Handle POST (save + redirect)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Always define inputs
    $menuId = (int) ($_POST['menu_id'] ?? ($_GET['menu_id'] ?? 0));

    // Accept either field name (use whichever your form currently posts)
    $chosenSorttypeId = (int) ($_POST['sorttype_id'] ?? ($_POST['sorttype'] ?? 0));

    // 1) Pull return from POST first, then GET
    $returnUrl = (string) ($_POST['return'] ?? ($_GET['return'] ?? ''));

    // 2) Normalize / validate (internal only, relative route)
    $returnUrl = trim($returnUrl);

    // Disallow full URLs (open redirect)
    if ($returnUrl !== '' && preg_match('~^[a-z]+://~i', $returnUrl)) {
        $returnUrl = '';
    }

    // Optional: strip leading doc_root if someone passes full internal path
    $returnUrl = preg_replace('~^' . preg_quote(DOC_ROOT, '~') . '~', '', $returnUrl);

    // 3) Fallback should be index (your expectation)
    if ($returnUrl === '') {
        $returnUrl = 'index/' . (int) ($_SESSION['website'] ?? 1);
    }

    $websiteId = (int) ($_SESSION['website'] ?? 0);

    if ($chosenSorttypeId > 0) {
        if ($menuId > 0) {
            $key = $websiteId . ':' . $menuId;
            $_SESSION['sort_override_by_menu'][$key] = $chosenSorttypeId;
        } else {
            $_SESSION['sort_override_global'][$websiteId] = $chosenSorttypeId;
        }
    }

    // Persist sort override
    $websiteId = (int) ($_SESSION['website'] ?? 1);

    if ($chosenSorttypeId > 0) {
        // Always persist a website-global override (used by index)
        $_SESSION['sort_override_global'][$websiteId] = $chosenSorttypeId;

        // Additionally persist a menu-scoped override when applicable
        if ($menuId > 0) {
            $_SESSION['sort_override'][$websiteId][$menuId] = $chosenSorttypeId;
        }
    }

    // Optional: prevent leakage between tabs
    unset($_SESSION['return_to']);

    error_log(
        "SORT SUBMIT: website={$websiteId} menu={$menuId} chosen={$chosenSorttypeId} redirect={$returnUrl}",
    );

    redirect($returnUrl);
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

$activeSorttypeId = (int) $cms->getSorttype()->resolveForMenu($menuId, 0);

$data['member'] = $member; // null for guests is OK if your Twig checks it
$data['pagelimit'] = $pagelimit;
$data['sorttype'] = $sorttype;
$data['active_sorttype_id'] = $activeSorttypeId;
$data['menu_id'] = $menuId;

// Website for render (member or guest)
$data['website'] = $cms->getWebsite()->getById($websiteId);
$data['return_to'] = $_SERVER['HTTP_REFERER'] ?? 'menu/' . $menuId . '/';
$data['sort_menu_id'] = (int) $menuId;
$data['return'] = $return;

echo $twig->render('sort.html', $data);
