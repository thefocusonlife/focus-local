<?php
declare(strict_types=1);
include APP_ROOT . '/src/pages/menu-path.php';

if (!$id) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

$sessionId = (int) ($_SESSION['id'] ?? 0);
$sessionWebsiteId = (int) ($_SESSION['website'] ?? 0);

// Current website context (prefer $website['id'] if you already loaded it)
$websiteId = (int) ($website['id'] ?? $sessionWebsiteId);

// Menu owner for navigation + slug lookups
$isGuest = empty($_SESSION) || $sessionId === 2;
$menuOwnerId = $isGuest ? 1 : $sessionId;

// Resolve menu (website-specific row) based on route
$menuId = (int) ($id ?? 0);

// Resolve menu
$maybeSlugOrId = (string) ($parts[2] ?? '');
$menu = null;

// If route uses a slug in the {id} position (legacy), resolve by slug.
if ($maybeSlugOrId !== '' && !ctype_digit($maybeSlugOrId)) {
    // Use current website + the menu owner you're already using for navigation
    $menu = $cms->getMenu()->getBySlug((int) $website['id'], (int) $menuOwnerId, $maybeSlugOrId);

    // Optional fallback: if website-specific menu isn't present, try website 1 owner 1
    if (!$menu) {
        $menu = $cms->getMenu()->getBySlug(1, 1, $maybeSlugOrId);
    }
} else {
    $menuId = (int) ($id ?? 0);
    $menu = $cms->getMenu()->get($menuId);
}

if (!$menu) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

// Canonical menu id (global category)
$canonicalMenuId = (int) ($menu['master_id'] ?? $menu['id']);

// Website context
//$websiteId = (int) ($_SESSION['website'] ?? (int) ($menu['website'] ?? 1));
//reversed for using account_id as canonical grid population.
$websiteId = (int) ($menu['website'] ?? (int) ($_SESSION['website'] ?? 1));
$website = $cms->getWebsite()->getById($websiteId);
if (!$website) {
    $websiteId = 1;
    $website = $cms->getWebsite()->getById(1);
}
$_SESSION['website'] = (int) $website['id']; // safe for guests

// Logged-in member (read-only)
$member = null;
$accountId = 0;

if (!empty($_SESSION['id'])) {
    $member = $cms->getMember()->get((int) $_SESSION['id']);
    $accountId = (int) ($member['account_id'] ?? 0);
}

// Viewer vs menu-owner (accountId is being used as "menu owner member id" for shared menus)
$viewerId = (int) ($_SESSION['id'] ?? 0);
$menuOwnerId = (int) ($accountId > 0 ? $accountId : $viewerId);

// Only owners should get owner-visibility; everyone else sees public visibility
$visibilityViewerId = $viewerId > 0 && $viewerId === $menuOwnerId ? $viewerId : null;

// Standard template context (recommended)
$data['session'] = $_SESSION;
$data['website'] = $website;
if ($member) {
    $data['member'] = $member;
}

// Navigation (use accountId, not $_SESSION['account_id'])
$data['navigation'] = $cms->getMenu()->getAll2((int) $website['id'], $menuOwnerId);

// Current menu
$data['menu'] = $menu;
$data['section'] = (int) $menu['id'];

// ---- Menu-scoped sort wiring ----
$menuId = (int) ($menu['id'] ?? 0);
$canonicalMenuId = (int) ($menu['master_id'] ?? $menuId);

// Prefer menu-scoped session sort if present, then session global, then member setting
$preferred =
    (int) ($_SESSION['sorttype_by_menu'][$menuId] ??
        (null ?? ($_SESSION['sorttype'] ?? (null ?? ($member['sorttype'] ?? 0)))));

$resolvedSorttypeId = (int) $cms->getSorttype()->resolveForMenu($menuId, $preferred);
$data['active_sorttype_id'] = $resolvedSorttypeId;

// ---- Story account filter: only apply viewer filter for member-owned menus ----
$menuAccountId = (int) ($menu['account_id'] ?? 0);
$storyAccountFilter = $menuAccountId > 0 ? $menuAccountId : null;

// ---- Page limit (member or guest default) ----
$pageLimit = (int) ($_SESSION['pagelimit'] ?? ($member['pagelimit'] ?? 100));

// Stories
$data['stories'] = $cms->getStory()->getAll3(
    (int) $website['id'],
    true,
    $canonicalMenuId,
    $storyAccountFilter,
    $pageLimit,
    $resolvedSorttypeId,
    true, // crossWebsite
);
$data['sort_menu_id'] = (int) $menuId;

echo $twig->render('menu.html', $data);
