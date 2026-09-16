<?php
declare(strict_types=1);

$allowedLocations = [
    'bend',
    'redmond',
    'sisters',
    'madras',
    'prineville',
    'lapine',
    'centraloregon',
];

$location = strtolower((string) ($_GET['location'] ?? 'redmond'));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'redmond';
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
    $_SESSION['return_to'] = DOC_ROOT . 'ride-activities?location=' . urlencode($location);
    $_SESSION['member_required_reason'] = 'Deleting a completed ride requires a free TFOL account.';

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
        'Deleting a completed ride requires a Website 44 TFOL account.';

    header('Location: ' . DOC_ROOT . 'member-required/44');
    exit();
}

$rideId = (int) ($_POST['ride_id'] ?? 0);

if ($rideId <= 0) {
    $_SESSION['flash_failure'] = 'Invalid ride.';
    redirect('index/44?location=' . urlencode($location));
    exit();
}

$stmt = $cms->getDb()->runSql(
    'SELECT member_id
       FROM ride
      WHERE id = :id
        AND website_id = 44
      LIMIT 1',
    ['id' => $rideId],
);

$ride = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ride) {
    $_SESSION['flash_failure'] = 'Ride not found.';
    redirect('index/44?location=' . urlencode($location));
    exit();
}

$isOwner = (int) $ride['member_id'] === $viewerId;
$isUber = $viewerId === 1;

if (!$isOwner && !$isUber) {
    $_SESSION['flash_failure'] = 'You do not have permission to delete this ride.';
    redirect('index/44?location=' . urlencode($location));
    exit();
}

$cms->getDb()->runSql('DELETE FROM ride WHERE id = :id', ['id' => $rideId]);

$_SESSION['flash_success'] = 'Ride deleted.';
redirect('index/44?location=' . urlencode($location));
