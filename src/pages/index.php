<?php
declare(strict_types=1);

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

$isGuest = empty($_SESSION['id']);
$isStoriesLandingCandidate = $page === 'index' && ($id === 0 || $id === 1);

if ($isGuest && $isStoriesLandingCandidate) {
    header('Location: ' . DOC_ROOT . 'stories-worth-saving');
    exit();
}

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

$crossWebsite = (int) $websiteId === 1;

$data['stories'] = $cms
    ->getStory()
    ->getAll3($websiteId, true, null, null, 100, $sorttypeId, $crossWebsite);

/**
 * Bicycle Club module for website 44.
 * Keep normal website/story flow, then append ride feed data.
 */
//limit stories to 3 on bicycle website=44
if ($websiteId === 44 && !empty($data['stories'])) {
    $data['stories'] = array_slice($data['stories'], 0, 3);
}
$data['isBicycleClub'] = $websiteId === 44;
$data['rides'] = [];
$data['rideSchedules'] = [];
$data['rideNotes'] = [];

if ($data['isBicycleClub']) {
    $allowedLocations = ['bend', 'redmond', 'sisters'];

    $location = strtolower((string) ($_GET['location'] ?? 'redmond'));

    if (!in_array($location, $allowedLocations, true)) {
        $location = 'redmond';
    }

    $data['location'] = $location;
    $data['locationName'] = ucfirst($location);
    $data['locations'] = [
        'bend' => 'Bend',
        'redmond' => 'Redmond',
        'sisters' => 'Sisters',
    ];

    // Group ride schedule
    $scheduleSql = "
        SELECT
            rs.id,
            rs.website_id,
            rs.member_id,
            rs.title,
            rs.ride_type,
            rs.day_of_week,
            rs.start_time,
            rs.start_location,
            rs.description,
            rs.is_active,
            rs.sort_order,
            rs.created,
            m.forename,
            m.surname
        FROM ride_schedule rs
        LEFT JOIN member m ON m.id = rs.member_id
        WHERE rs.website_id = :website_id
        AND rs.is_active = 1
        AND rs.location = :location
        ORDER BY rs.sort_order ASC, rs.day_of_week ASC, rs.start_time ASC, rs.id ASC
    ";

    $scheduleStmt = $cms->getDb()->runSql($scheduleSql, [
        'website_id' => $websiteId,
        'location' => $location,
    ]);
    $data['rideSchedules'] = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

    // Club notes
    $noteSql = "
        SELECT
            rn.id,
            rn.website_id,
            rn.member_id,
            rn.title,
            rn.note_text,
            rn.note_date,
            rn.is_active,
            rn.created,
            m.forename,
            m.surname
        FROM ride_note rn
        LEFT JOIN member m ON m.id = rn.member_id
        WHERE rn.website_id = :website_id
          AND rn.is_active = 1
          AND rn.location = :location
        ORDER BY
            CASE WHEN rn.note_date IS NULL THEN 1 ELSE 0 END,
            rn.note_date DESC,
            rn.created DESC,
            rn.id DESC
    ";

    $noteStmt = $cms->getDb()->runSql($noteSql, [
        'website_id' => $websiteId,
        'location' => $location,
    ]);
    $data['rideNotes'] = $noteStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($data['rideNotes'] as &$note) {
        $note['note_text_html'] = formatTextWithLinks((string) ($note['note_text'] ?? ''));
    }
    unset($note);

    // Recent rides
    $rideSql = "
        SELECT
            r.id,
            r.website_id,
            r.member_id,
            r.ride_date,
            r.start_time,
            r.title,
            r.ride_type,
            r.start_location,
            r.distance_miles,
            r.elapsed_minutes,
            r.elevation_gain_ft,
            r.avg_speed_mph,
            r.avg_power_watts,
            r.np_power_watts,
            r.notes,
            r.gpx_file,
            r.gpx_uploaded,
            r.start_lat,
            r.start_lng,
            r.end_lat,
            r.end_lng,
            r.status,
            r.created,
            m.forename,
            m.surname
        FROM ride r
        LEFT JOIN member m ON m.id = r.member_id
        WHERE r.website_id = :website_id
          AND r.status = 'published'
          AND r.location = :location
        ORDER BY r.ride_date DESC, r.id DESC
        LIMIT 20
    ";

    $rideStmt = $cms->getDb()->runSql($rideSql, [
        'website_id' => $websiteId,
        'location' => $location,
    ]);
    $data['rides'] = $rideStmt->fetchAll(PDO::FETCH_ASSOC);

    $data['communityItems'] = $cms->getBicycleCommunity()->getByWebsiteId(44, 8);
}

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
$data['locations'] = [
    'bend' => 'Bend',
    'redmond' => 'Redmond',
    'sisters' => 'Sisters',
];
echo $twig->render('index.html', $data);
return;
