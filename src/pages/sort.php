<?php
declare(strict_types=1);
file_put_contents(
    '/tmp/tfol-sort-hit.log',
    date('c') . ' HIT sort.php id=' . var_export($id ?? null, true) . "\n",
    FILE_APPEND,
);

use PhpBook\Validate\Validate;

include APP_ROOT . '/src/pages/menu-path.php';

$errors = [];
// Menu id comes primarily from the route: /sort/{menuId}
$menuId = (int) ($id ?? 0);

// Fallbacks for POST/GET/session (legacy support)
if ($menuId <= 0) {
    $menuId = (int) ($_POST['menu_id'] ?? ($_GET['menu_id'] ?? ($_SESSION['menu_id'] ?? 0)));
}

if ($menuId <= 0) {
    redirect('page-not-found/');
    exit();
}

$_SESSION['menu_id'] = $menuId; // remember last menu

// Require login for Sort (menu-scoped)
if (($_SESSION['id'] ?? 0) <= 0) {
    redirect('login/' . $menuId);
    exit();
}

$member = $cms->getMember()->get((int) ($_SESSION['id'] ?? 0));
if (!$member) {
    // Session says logged in, but member missing → force re-auth
    redirect('login/' . $menuId);
    exit();
}

/* ---------- POST: update settings ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member['pagelimit'] = (int) ($_POST['pagelimit'] ?? 10);
    $menuId = (int) ($_POST['menu_id'] ?? ($_GET['menu_id'] ?? 0));
    if ($menuId <= 0) {
        redirect('page-not-found/');
        exit();
    }

    $incomingSorttype = (int) ($_POST['sorttype'] ?? 0);
    $resolvedSorttype = $cms->getSorttype()->resolveForMenu($menuId, $incomingSorttype);
    $member['sorttype'] = $resolvedSorttype;

    $cms->getMember()->update($member);
    $cms->getSession()->create($member, (int) $member['website']);

    redirect('menu/' . $menuId . '/');

    exit();
}

/* ---------- GET: render form ---------- */

$pagelimit = $cms->getPagelimit()->getAll();
$sorttype = $cms->getSorttype()->getByMenu($menuId);

$activeSorttypeId = $cms
    ->getSorttype()
    ->resolveForMenu($menuId, (int) ($member['sorttype'] ?? (int) ($_SESSION['sorttype'] ?? 0)));

$data['member'] = $member;
$data['pagelimit'] = $pagelimit;
$data['sorttype'] = $sorttype;
$data['active_sorttype_id'] = $activeSorttypeId;
$data['menu_id'] = $menuId;
$data['website'] = $cms->getWebsite()->getById((int) $member['website']);

echo $twig->render('sort.html', $data);
