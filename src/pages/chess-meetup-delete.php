<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index/51');
    exit();
}

$meetupId = (int) ($_POST['id'] ?? 0);

if ($meetupId <= 0) {
    $_SESSION['flash_failure'] = 'Invalid Chess Game Meetup.';
    redirect('index/51');
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));
$isLoggedIn = $viewerId > 0 && $viewerId !== 2 && $role !== 'guest';

if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 51;
    $_SESSION['return_to'] = DOC_ROOT . 'chess-meetup-edit/' . $meetupId;
    $_SESSION['member_required_reason'] =
        'Deleting a Chess Game Meetup requires Central Oregon Chess membership.';

    header('Location: ' . DOC_ROOT . 'login/51');
    exit();
}

$isChessAdmin = in_array($viewerId, [1, 489, 503], true);

$chessMembership = $cms->getClubMembers()->getByMemberId($viewerId, 51);

$isChessMember =
    $isChessAdmin ||
    strtolower((string) ($chessMembership['membership_status'] ?? '')) === 'active';

if (!$isChessMember) {
    $_SESSION['flash_failure'] = 'Deleting a Meetup requires Central Oregon Chess membership.';

    redirect('index/51');
    exit();
}

$stmt = $cms->getDb()->runSql(
    'SELECT id, member_id
       FROM chess_meetup
      WHERE id = :id
        AND website_id = :website_id
      LIMIT 1',
    [
        'id' => $meetupId,
        'website_id' => 51,
    ],
);

$meetup = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$meetup) {
    $_SESSION['flash_failure'] = 'Chess Game Meetup not found.';
    redirect('index/51');
    exit();
}

$isOwner = (int) $meetup['member_id'] === $viewerId;
// Local Chess management permission; does not grant global Uber Admin access.
$isUberAdmin = in_array($viewerId, [1, 489, 503], true);

if (!$isOwner && !$isUberAdmin) {
    $_SESSION['flash_failure'] = 'You do not have permission to delete this Meetup.';

    redirect('index/51');
    exit();
}

$cms->getDb()->runSql(
    'DELETE FROM chess_meetup
      WHERE id = :id
        AND website_id = :website_id',
    [
        'id' => $meetupId,
        'website_id' => 51,
    ],
);

$_SESSION['flash_success'] = 'Chess Game Meetup deleted.';

redirect('index/51');
exit();
