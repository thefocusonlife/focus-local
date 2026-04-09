<?php
declare(strict_types=1);

use PhpBook\Validate\Validate;

require_once APP_ROOT . '/src/security/guard.php';

guardPublic();

$errors = [];

/**
 * Lightweight debug logging for sort page.
 */
$logPath = '/tmp/tfol-sort-hit.log';
$log = static function (string $message) use ($logPath): void {
    file_put_contents($logPath, date('c') . ' ' . $message . "\n", FILE_APPEND);
};

$log('HIT sort.php id=' . var_export($id ?? null, true));
$log('URI=' . ($_SERVER['REQUEST_URI'] ?? '') . ' id=' . var_export($id ?? null, true));

/**
 * Resolve menu id from route first, then legacy fallbacks.
 */
$menuId = (int) ($id ?? 0);
if ($menuId <= 0) {
    $menuId = (int) ($_POST['menu_id'] ?? ($_GET['menu_id'] ?? ($_SESSION['menu_id'] ?? 0)));
}

$log('menuId=' . $menuId);

if ($menuId <= 0) {
    redirect('page-not-found/');
    exit();
}

/**
 * Load menu and derive authoritative website context from the menu.
 */
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

$website = $cms->getWebsite()->getById($websiteId);
if (!$website) {
    redirect('page-not-found/');
    exit();
}

/**
 * Keep all session website keys in sync.
 */
$_SESSION['website'] = $websiteId;
$_SESSION['websiteid'] = $websiteId;
$_SESSION['menu_website'] = $websiteId;
$_SESSION['menu_id'] = $menuId;

/**
 * Logged-in member is optional context only.
 * Do not let member.website override page/menu website context.
 */
$sessionMemberId = (int) ($_SESSION['id'] ?? 0);
$isLoggedIn = $sessionMemberId > 0 && $sessionMemberId !== 2;

$member = null;
if ($sessionMemberId > 0) {
    $member = $cms->getMember()->get($sessionMemberId);
    if (!$member) {
        $isLoggedIn = false;
        $member = null;
    }
}

/**
 * Normalize initial GET return route for rendering the form.
 * Only allow internal relative routes.
 */
$return = (string) ($_GET['return'] ?? '');
$return = trim($return);

if ($return !== '' && preg_match('~^[a-z]+://~i', $return)) {
    $return = '';
}

$return = preg_replace('~^' . preg_quote(DOC_ROOT, '~') . '~', '', $return);
$return = ltrim($return, '/');

/* ---------- POST: save + redirect ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedMenuId = (int) ($_POST['menu_id'] ?? ($_GET['menu_id'] ?? $menuId));
    if ($postedMenuId <= 0) {
        $postedMenuId = $menuId;
    }

    $chosenSorttypeId = (int) ($_POST['sorttype_id'] ?? ($_POST['sorttype'] ?? 0));

    /**
     * Re-resolve posted menu and website from authoritative menu record.
     */
    $postMenuRow = $cms->getMenu()->get($postedMenuId);
    if (!$postMenuRow) {
        redirect('page-not-found/');
        exit();
    }

    $postWebsiteId = (int) ($postMenuRow['website'] ?? 0);
    if ($postWebsiteId <= 0) {
        redirect('page-not-found/');
        exit();
    }

    /**
     * Keep website/session context coherent before redirect.
     */
    $_SESSION['website'] = $postWebsiteId;
    $_SESSION['websiteid'] = $postWebsiteId;
    $_SESSION['menu_website'] = $postWebsiteId;
    $_SESSION['menu_id'] = $postedMenuId;

    /**
     * Persist sort override.
     */
    if ($chosenSorttypeId > 0) {
        // Website-global override used by index/landing pages
        $_SESSION['sort_override_global'][$postWebsiteId] = $chosenSorttypeId;

        // Legacy flat key support
        $_SESSION['sort_override_by_menu'][
            $postWebsiteId . ':' . $postedMenuId
        ] = $chosenSorttypeId;

        // Structured website/menu override support
        $_SESSION['sort_override'][$postWebsiteId][$postedMenuId] = $chosenSorttypeId;
    }

    /**
     * Candidate return from form/query.
     */
    $returnUrl = (string) ($_POST['return'] ?? ($_GET['return'] ?? ''));
    $returnUrl = trim($returnUrl);

    // Only allow internal relative routes
    if ($returnUrl !== '' && preg_match('~^[a-z]+://~i', $returnUrl)) {
        $returnUrl = '';
    }

    $returnUrl = preg_replace('~^' . preg_quote(DOC_ROOT, '~') . '~', '', $returnUrl);
    $returnUrl = ltrim($returnUrl, '/');

    /**
     * Guest-vs-logged-in redirect behavior:
     * - Guests always return to website landing page.
     * - Logged-in users return to posted return if valid,
     *   else last member menu,
     *   else member/{id},
     *   else website landing page.
     */ $sessionMemberId = (int) ($_SESSION['id'] ?? 0);
    $isLoggedIn = $sessionMemberId > 0 && $sessionMemberId !== 2;

    $member = null;
    if ($sessionMemberId > 0) {
        $member = $cms->getMember()->get($sessionMemberId);
        if (!$member) {
            $isLoggedIn = false;
            $member = null;
        }
    }

    if (!$isLoggedIn) {
        $returnUrl = 'index/' . $postWebsiteId;
    } else {
        if ($returnUrl === '') {
            $returnUrl = (string) ($_SESSION['last_member_menu'] ?? '');
        }

        if ($returnUrl === '') {
            $returnUrl = 'member/' . (int) ($_SESSION['id'] ?? 0);
        }

        // Final safety normalization
        if (
            $returnUrl === '' ||
            $returnUrl === 'index' ||
            $returnUrl === 'index/' ||
            preg_match('~^member/0/?$~', $returnUrl)
        ) {
            $returnUrl = 'index/' . $postWebsiteId;
        }
    }

    unset($_SESSION['return_to']);

    $log(
        'SORT SUBMIT' .
            ' loggedIn=' .
            (int) $isLoggedIn .
            ' website=' .
            $postWebsiteId .
            ' menu=' .
            $postedMenuId .
            ' chosen=' .
            $chosenSorttypeId .
            ' redirect=' .
            $returnUrl .
            ' session.website=' .
            (int) ($_SESSION['website'] ?? 0) .
            ' session.websiteid=' .
            (int) ($_SESSION['websiteid'] ?? 0),
    );

        redirect($returnUrl);
    exit();
}

/* ---------- GET: render form ---------- */

$pagelimit = $cms->getPagelimit()->getAll();
$sorttype = $cms->getSorttype()->getByMenu($menuId);
$activeSorttypeId = (int) $cms->getSorttype()->resolveForMenu($menuId, 0);

$data['member'] = $member;
$data['pagelimit'] = $pagelimit;
$data['sorttype'] = $sorttype;
$data['active_sorttype_id'] = $activeSorttypeId;
$data['menu_id'] = $menuId;
$data['sort_menu_id'] = $menuId;

/**
 * Render using menu-derived website context.
 */
$data['website'] = $website;

/**
 * Keep form return lightweight.
 * Logged-in users may post a member/menu return; guests are forced back to index/{websiteId} on submit.
 */
$data['return_to'] = $_SERVER['HTTP_REFERER'] ?? 'menu/' . $menuId . '/';
$data['return'] = $return;

echo $twig->render('sort.html', $data);
