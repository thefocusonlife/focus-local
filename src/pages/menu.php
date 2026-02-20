<?php
declare(strict_types=1);
include APP_ROOT . '/src/pages/menu-path.php';
require_once APP_ROOT . '/src/security/guard.php';
guardPublic();

if (!$id) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

$sessionId = (int) ($_SESSION['id'] ?? 0);
$sessionWebsiteId = (int) ($_SESSION['website'] ?? 0);

// Prefer already-loaded $website['id'] else session
$websiteId = (int) ($website['id'] ?? $sessionWebsiteId);

if ($websiteId <= 0) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

// Menu owner for navigation + slug lookups
$isGuest = empty($_SESSION) || $sessionId === 2;
$menuOwnerId = $isGuest ? 1 : $sessionId;

// Resolve menu (website-specific row) based on route
$menuId = (int) ($id ?? 0);

// IMPORTANT:
// - For canonical routes: /menu/{id}/{optional-slug}
//   -> $parts[1] is the id, $parts[2] is a redundant slug tail to IGNORE.
// - For legacy routes: /menu/{slug}
//   -> $parts[1] is the slug, $id will be 0 (non-numeric).

$idOrSlugSegment = (string) ($parts[1] ?? ''); // <-- legacy slug would be here
$tailSlug = (string) ($parts[2] ?? ''); // <-- redundant; IGNORE for id-based routes

$menu = null;

// ------------------------------------------------------------
// 1) Legacy slug route only: /menu/{slug} where the {id} segment is NOT numeric
// ------------------------------------------------------------
if ($menuId <= 0 && $idOrSlugSegment !== '' && !ctype_digit($idOrSlugSegment)) {
    // slug is in the {id} position (legacy)
    $maybeSlug = $idOrSlugSegment;

    $menu = $cms->getMenu()->getBySlug($websiteId, (int) $menuOwnerId, $maybeSlug);

    if (!$menu) {
        $menu = $cms->getMenu()->getBySlugAnyAccount($websiteId, $maybeSlug);
    }
}
// ------------------------------------------------------------
// 2) Normal route: /menu/{id} OR /menu/{id}/{anything}
// ------------------------------------------------------------
else {
    // Ignore $tailSlug completely; it is decorative
    $menuId = (int) ($id ?? 0);

    // If websiteId can be wrong (common when entering via deep-link),
    // derive website from the menuId FIRST, then do the website-specific fetch.
    // Use whatever "get by id" method you have (examples below).
    //$menuAny = $cms->getMenu()->getById($menuId) ?? ($cms->getMenu()->get($menuId) ?? null);
    $menuAny = $cms->getMenu()->get($menuId) ?? null;
    if ($menuAny) {
        $derivedWebsiteId = (int) ($menuAny['website_id'] ?? ($menuAny['website'] ?? 0));
        if ($derivedWebsiteId > 0) {
            $websiteId = $derivedWebsiteId;
            $_SESSION['website'] = $websiteId;
            $_SESSION['website_id'] = $websiteId;
        }
    }

    $menu = $cms->getMenu()->getForWebsite($menuId, $websiteId);
}

if (!$menu) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

$uiWebsiteId = (int) ($menu['website'] ?? 0);
$menuAccountId = (int) ($menu['account_id'] ?? 0);
$masterMenuId = (int) ($menu['master_id'] ?? 0);
$menuId = (int) ($menu['id'] ?? 0);

$storyMenuId = $masterMenuId > 0 ? $masterMenuId : $menuId;
$storyWebsiteId = $menuAccountId === 1 ? 1 : $uiWebsiteId;

$menuDefaultSorttypeId = (int) ($menu['default_sorttype_id'] ?? 0);

// Canonical menu id (global category)
$canonicalMenuId = $masterMenuId > 0 ? $masterMenuId : $menuId;

// Website context
//$websiteId = (int) ($_SESSION['website'] ?? (int) ($menu['website'] ?? 1));
//reversed for using account_id as canonical grid population.
$websiteId = (int) ($menu['website'] ?? (int) ($_SESSION['website'] ?? 1));
$website = $cms->getWebsite()->getById($websiteId);
if (!$website) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

// Logged-in member (read-only)
$member = null;
$accountId = 0;

if (!empty($_SESSION['id'])) {
    $member = $cms->getMember()->get((int) $_SESSION['id']);
    $accountId = (int) ($member['account_id'] ?? 0);
}

// Viewer vs menu-owner (account_id is used as "menu owner member id" for shared menus)
$viewerId = (int) ($_SESSION['id'] ?? 0);
$memAccountId = 0; // <-- always define it

if ($viewerId > 0) {
    // Logged-in: menus belong to the viewer, unless attached to an account owner
    $memAccountId = (int) ($member['account_id'] ?? 0); // adjust if your member array differs
    $menuOwnerId = $memAccountId > 0 ? $memAccountId : $viewerId;
} else {
    // Guest: show UberAdmin menus
    $menuOwnerId = 1; // uber admin member id
}

// Navigation
$data['navigation'] = $cms->getMenu()->getAll2((int) $website['id'], $menuOwnerId);

// Optional debug
if (defined('TFOL_ROUTE_DEBUG') && TFOL_ROUTE_DEBUG) {
    $data['_debug_nav_owner'] = [
        'viewerId' => $viewerId,
        'memAccountId' => $memAccountId,
        'menuOwnerId' => $menuOwnerId,
    ];
}

// Only owners should get owner-visibility; everyone else sees public visibility
$visibilityViewerId = $viewerId > 0 && $viewerId === $menuOwnerId ? $viewerId : null;

// Standard template context (recommended)
$data['session'] = $_SESSION;
$data['website'] = $website;
if ($member) {
    $data['member'] = $member;
}

// Current menu
$data['menu'] = $menu;
$data['section'] = (int) $menu['id'];

// ---- Menu-scoped sort wiring ----
$menuId = (int) ($menu['id'] ?? 0);
$canonicalMenuId = (int) ($menu['master_id'] ?? $menuId);

$memberSorttypeId = is_array($member) && isset($member['sorttype']) ? (int) $member['sorttype'] : 0;

// Prefer menu-scoped session sort if present, then session global, then member setting
// ------------------------------------------------------------
$websiteId = (int) ($website['id'] ?? ($_SESSION['website'] ?? 0));
$key = $websiteId . ':' . $menuId;

// Preferred sort comes from the same session key that sort.php writes
$websiteId = (int) ($_SESSION['website'] ?? 0);

// Per-website, per-menu override
$preferredSorttypeId = (int) ($_SESSION['sort_override'][$websiteId][$menuId] ?? 0);

// Menu-level default (new)
$menuDefaultSorttypeId = (int) ($menu['default_sorttype_id'] ?? 0);

// Feed resolver: session override wins, else menu default
$preferredIn = $preferredSorttypeId ?: $menuDefaultSorttypeId;

$resolvedSorttypeId = (int) $cms->getSorttype()->resolveForMenu($menuId, $preferredIn);
$data['active_sorttype_id'] = $resolvedSorttypeId;

// ---- Story account filter: only apply viewer filter for member-owned menus ----
$menuAccountId = (int) ($menu['account_id'] ?? 0);
$storyAccountFilter = $menuAccountId > 0 ? $menuAccountId : null;

// ---- Page limit (member or guest default) ----
$pageLimit = (int) ($_SESSION['pagelimit'] ?? ($member['pagelimit'] ?? 100));

$menuId = (int) ($menu['id'] ?? 0);
$masterMenuId = (int) ($menu['master_id'] ?? 0);

// Stories live on website 1
$storyMenuId = $masterMenuId > 0 ? $masterMenuId : $menuId;
$crossWebsite = true;
$uiWebsiteId = (int) ($menu['website'] ?? ($_SESSION['website'] ?? 0));
$menuAccountId = (int) ($menu['account_id'] ?? 0);

// UberAdmin menus always source stories from website 1
$storyWebsiteId = $menuAccountId === 1 ? 1 : $uiWebsiteId;

// DEBUG: menu / website / story resolution

error_log(
    sprintf(
        'MENU DEBUG: menuId=%d uiWebsite=%d storyWebsite=%d menuWebsite=%d masterId=%d storyMenuId=%d account=%d sessionWebsite=%s',
        $menuId,
        $uiWebsiteId ?? -1,
        $storyWebsiteId ?? -1,
        (int) ($menu['website'] ?? 0),
        $masterMenuId,
        $storyMenuId,
        (int) ($menu['account_id'] ?? 0),
        $_SESSION['website'] ?? 'NULL',
    ),
);

// Sort defaults/overrides are per LOCAL menu row
if ((int) $resolvedSorttypeId === 0) {
    $resolvedSorttypeId = (int) $cms->getSorttype()->resolveForMenu($menuId, 0);
    $data['active_sorttype_id'] = $resolvedSorttypeId;
}

if (defined('TFOL_ROUTE_DEBUG') && TFOL_ROUTE_DEBUG) {
    $data['_debug_sort'] = [
        'menuId' => $menuId ?? null,
        'preferredSorttypeId' => $preferredSorttypeId ?? null,
        'resolvedSorttypeId' => $sorttypeId ?? null,
    ];
}

file_put_contents(
    '/tmp/tfol-menu-call.log',
    sprintf(
        "%s menuId=%d masterId=%d storyMenuId=%d cross=%s sort=%d uri=%s\n",
        date('c'),
        $menuId,
        $masterMenuId,
        $storyMenuId,
        $crossWebsite ? '1' : '0',
        $resolvedSorttypeId,
        $_SERVER['REQUEST_URI'] ?? '',
    ),
    FILE_APPEND,
);
// ------------------------------------------------------------
// Sort selection (menu-based)
// Priority: menu-scoped session -> global session -> member pref -> 0
// ------------------------------------------------------------
$websiteId = (int) ($website['id'] ?? ($_SESSION['website'] ?? 0));
$key = $websiteId . ':' . $menuId;

// Preferred sort comes from the same session key that sort.php writes
$websiteId = (int) ($_SESSION['website'] ?? 0);

// Per-website, per-menu override--guard against empty sort_override
$hasMenuOverride = isset($_SESSION['sort_override'][$websiteId][$menuId]);

$preferredSorttypeId = $hasMenuOverride
    ? (int) $_SESSION['sort_override'][$websiteId][$menuId]
    : (int) ($menu['default_sorttype_id'] ?? 0);

$resolvedSorttypeId = (int) $cms->getSorttype()->resolveForMenu($menuId, $preferredSorttypeId);
$data['active_sorttype_id'] = $resolvedSorttypeId;

$resolvedSorttypeId = (int) $cms->getSorttype()->resolveForMenu($menuId, $preferredSorttypeId);
$data['active_sorttype_id'] = $resolvedSorttypeId;

// Optional: debug panel support
if (defined('TFOL_ROUTE_DEBUG') && TFOL_ROUTE_DEBUG) {
    $data['_debug_sort'] = [
        'menuId' => $menuId,
        'preferredSorttypeId' => $preferredSorttypeId,
        'resolvedSorttypeId' => $resolvedSorttypeId,
    ];
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$memberFilter = $viewerId > 0 ? $viewerId : null;

$published = $published ?? 1;
$crossWebsite = false;
error_log(
    "MENU FETCH: website={$websiteId} menu={$menuId} viewer={$viewerId} preferred={$preferredSorttypeId} resolved={$resolvedSorttypeId}",
);

$data['stories'] = $cms->getStory()->getAll3(
    $storyWebsiteId, // source website for stories
    $published,
    $storyMenuId, // source menu for stories
    $storyAccountFilter, // <-- use menu/account owner, e.g. 1 for UberAdmin
    300,
    $resolvedSorttypeId,
    $crossWebsite,
);
//$menuOwnerId = 1; // guest
$data['navigation'] = $cms->getMenu()->getAll2((int) $website['id'], $menuOwnerId);
$data['current_path'] = "menu/$menuId";
$data['current_menu_id'] = $menuId;
// ------------------------------------------------------------
// Sorttypes for menu default selection
// ------------------------------------------------------------
$data['sorttypes'] = $cms->getSorttype()->getAll();

$data['sort_menu_id'] = (int) $menuId;
if (defined('TFOL_ROUTE_DEBUG') && TFOL_ROUTE_DEBUG) {
    $data['_debug'] = [
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'get' => $_GET ?? [],
        'post' => $_POST ?? [],
        'session' => $_SESSION ?? [],
    ];
}

echo $twig->render('menu.html', $data);
