<?php
declare(strict_types=1);

file_put_contents(
    '/tmp/tfol-route.log',
    date('c') .
        " ROUTE page={$page} id=" .
        var_export($id ?? null, true) .
        ' parts=' .
        (isset($parts) ? json_encode($parts) : 'NA') .
        "\n",
    FILE_APPEND,
);

$data = [];
$guidetext = '';

/**
 * Resolve canonical website for this request.
 * Route wins. Then session. Then default 1.
 */
$websiteId = (int) ($id ?? 0);

if ($websiteId <= 0) {
    $websiteId = (int) ($_SESSION['website'] ?? 0);
}
if ($websiteId <= 0) {
    $websiteId = (int) ($_SESSION['websiteid'] ?? 0);
}
if ($websiteId <= 0) {
    $websiteId = 1;
}

/**
 * Load website; fallback to 1 only if invalid.
 */
$website = $cms->getWebsite()->getById($websiteId);
if (!$website || !isset($website['id'])) {
    $websiteId = 1;
    $website = $cms->getWebsite()->getById(1);
}

/**
 * Keep session coherent.
 */
$_SESSION['website'] = $websiteId;
$_SESSION['websiteid'] = $websiteId;
$_SESSION['menu_website'] = $websiteId;

/**
 * Logged-in member is optional context only.
 * Never use member.website to override current browsing website.
 */
$member = null;
$memAccountId = 0;
$viewerId = (int) ($_SESSION['id'] ?? 0);

if ($viewerId > 0) {
    $member = $cms->getMember()->get($viewerId);
    if ($member && isset($member['account_id'])) {
        $memAccountId = (int) $member['account_id'];
    }
}

/**
 * Menu owner logic preserved.
 */
if ($viewerId > 0) {
    $menuOwnerId = (int) ($memAccountId > 0 ? $memAccountId : $viewerId);
} else {
    $menuOwnerId = 1;
}

$visibilityViewerId = $viewerId > 0 && $viewerId === $menuOwnerId ? $viewerId : null;

/**
 * Membership gating.
 */
if ($websiteId > 1 && $viewerId <= 0 && empty($website['non_members'])) {
    $data['failure'] =
        'WARNING: You must be a registered member in order to access a GET FOCUSED website.  Click the Register link above to view subscription plans OR click the Refresh link for more photos on this page. ** Note: You may access websites marked as FREE-Access without a membership.';

    $websiteId = 1;
    $website = $cms->getWebsite()->getById(1);

    $_SESSION['website'] = 1;
    $_SESSION['websiteid'] = 1;
    $_SESSION['menu_website'] = 1;
}

/**
 * Flash/quickguide.
 */
if (!empty($_SESSION['flash_success'])) {
    $data['failure'] = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
} elseif (!empty($_SESSION['flash_failure'])) {
    $data['failure'] = (string) $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
} else {
    $guide = $cms->getQuickguide()->getOne();
    if (!empty($guide['guidetext'])) {
        $data['success'] = $guide['guidetext'];
    }
}
/**
 * Stories: use canonical websiteId already resolved above.
 * Do NOT overwrite websiteId from session here.
 */
$preferredSorttypeId = (int) ($_SESSION['sort_override_global'][$websiteId] ?? 0);
$sorttypeId = $preferredSorttypeId > 0 ? $preferredSorttypeId : null;

$data['stories'] = $cms->getStory()->getAll3($websiteId, true, null, null, 100, $sorttypeId);

/**
 * Navigation.
 */
$data['navigation'] = $cms->getMenu()->getAll2($websiteId, 1);

/**
 * Default Sort target.
 */
/**
 * Default Sort target.
 * Use the current website's sort/focus menu, not hardcoded website 1 menu 2.
 */
if (empty($data['sort_menu_id'])) {
    $currentWebsiteId = (int) ($websiteId ?? ($website['id'] ?? ($_SESSION['website'] ?? 1)));

    // Best case: ask menu model for the sort/focus menu for this website
    $sortMenuId = 0;

    // Replace this with your actual menu lookup if you have one
    $menus = $cms->getMenu()->getAll2($currentWebsiteId, 1);
    foreach ($menus as $menu) {
        $name = strtolower(trim((string) ($menu['name'] ?? '')));
        if ($name === 'focus' || $name === 'sort') {
            $sortMenuId = (int) ($menu['id'] ?? 0);
            break;
        }
    }

    // Only fall back to menu 2 for website 1
    if ($sortMenuId <= 0 && $currentWebsiteId === 1) {
        $sortMenuId = 2;
    }

    $data['sort_menu_id'] = $sortMenuId;
}
/**
 * Template data must use resolved websiteId, not raw route id.
 */
$data['website'] = $website;
$data['websiteId'] = $websiteId;
$data['id'] = $websiteId;

if ($member) {
    $data['member'] = $member;
}

if (defined('TFOL_ROUTE_DEBUG') && TFOL_ROUTE_DEBUG) {
    $data['_debug'] = [
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'get' => $_GET ?? [],
        'post' => $_POST ?? [],
        'session' => $_SESSION ?? [],
        'resolved_website_id' => $websiteId,
    ];
}

echo $twig->render('index.html', $data);
return;
