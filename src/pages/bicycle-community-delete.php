<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index/44');
    exit();
}

$allowedLocations = [
    'bend',
    'redmond',
    'sisters',
    'madras',
    'prineville',
    'lapine',
    'centraloregon',
];

$location = strtolower(trim((string) ($_POST['location'] ?? 'redmond')));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'redmond';
}

$itemId = (int) ($_POST['id'] ?? 0);

if ($itemId <= 0) {
    $_SESSION['flash_failure'] = 'Invalid Community Item.';

    redirect('index/44?location=' . urlencode($location));
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

$isLoggedIn = $viewerId > 0 && $role !== 'guest';

/*
 * Require a signed-in TFOL account.
 */
if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 44;
    $_SESSION['return_to'] = DOC_ROOT . 'index/44?location=' . urlencode($location);
    $_SESSION['member_required_reason'] = 'Deleting a Community Item requires a free TFOL account.';

    header('Location: ' . DOC_ROOT . 'login/44');
    exit();
}

/*
 * Require a Website 44 TFOL account.
 */
if ($viewerId !== 1 && !$cms->getMember()->isMemberOfWebsite($viewerId, 44)) {
    $cms->getSession()->resetToGuest(44, true);

    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 44;
    $_SESSION['return_to'] = DOC_ROOT . 'index/44';
    $_SESSION['member_required_reason'] =
        'Deleting a Community Item requires a Website 44 TFOL account.';

    header('Location: ' . DOC_ROOT . 'member-required/44');
    exit();
}

/*
 * Step 2: Load the existing item so ownership comes from the database.
 */
$statement = $cms->getDb()->runSql(
    'SELECT id, member_id
       FROM bicycle_community
      WHERE id = :id
        AND website_id = 44
      LIMIT 1',
    [
        'id' => $itemId,
    ],
);

$item = $statement->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    $_SESSION['flash_failure'] = 'Community Item not found.';

    redirect('index/44?location=' . urlencode($location));
    exit();
}

/*
 * Step 3: Only the owner or member 1 may delete.
 */
$isOwner = (int) $item['member_id'] === $viewerId;
$isUberMember = $viewerId === 1;

if (!$isOwner && !$isUberMember) {
    $_SESSION['flash_failure'] = 'You do not have permission to delete this Community Item.';

    redirect('index/44?location=' . urlencode($location));
    exit();
}

/*
 * Step 4: Delete.
 */
$cms->getDb()->runSql(
    'DELETE FROM bicycle_community
      WHERE id = :id
        AND website_id = 44',
    [
        'id' => $itemId,
    ],
);

$_SESSION['flash_success'] = 'Community Item deleted.';

redirect('index/44?location=' . urlencode($location));
exit();
