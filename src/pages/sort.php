<?php
declare(strict_types=1);

use PhpBook\Validate\Validate;

include APP_ROOT . '/src/pages/menu-path.php';

$errors = [];
$menuId = (int) ($_POST['menu_id'] ?? ($_GET['menu_id'] ?? ($_SESSION['menu_id'] ?? 0)));
if ($menuId <= 0) {
    redirect('page-not-found/');
    exit();
}
$_SESSION['menu_id'] = $menuId; // remember last menu

$member = $cms->getMember()->get($_SESSION['id']);
if (!$member) {
    redirect('page-not-found/');
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

    redirect('member/' . $member['id'] . '/');
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
