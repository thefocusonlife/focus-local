<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index/51');
    exit();
}

$scheduleId = (int) ($_POST['id'] ?? 0);

if ($scheduleId <= 0) {
    $_SESSION['flash_failure'] = 'Invalid Chess Night.';
    redirect('index/51');
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));
$isLoggedIn = $viewerId > 0 && $role !== 'guest';

/*
 * A delete POST cannot resume after login, so return to the Edit page.
 */
if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 51;
    $_SESSION['return_to'] = DOC_ROOT . 'chess-schedule-edit/' . $scheduleId;
    $_SESSION['member_required_reason'] =
        'Deleting a Chess Night requires Central Oregon Chess administrator access.';

    header('Location: ' . DOC_ROOT . 'login/51');
    exit();
}

/*
 * Uber Admin is the only Chess schedule manager for now.
 */
if ($viewerId !== 1) {
    $_SESSION['flash_failure'] = 'You do not have permission to delete scheduled Chess Nights.';

    redirect('index/51');
    exit();
}

/*
 * Verify that the record belongs to the Chess website.
 */
$stmt = $cms->getDb()->runSql(
    'SELECT id
       FROM chess_schedule
      WHERE id = :id
        AND website_id = :website_id
      LIMIT 1',
    [
        'id' => $scheduleId,
        'website_id' => 51,
    ],
);

if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
    $_SESSION['flash_failure'] = 'Chess Night not found.';
    redirect('index/51');
    exit();
}

$cms->getDb()->runSql(
    'DELETE FROM chess_schedule
      WHERE id = :id
        AND website_id = :website_id',
    [
        'id' => $scheduleId,
        'website_id' => 51,
    ],
);

$_SESSION['flash_success'] = 'Chess Night deleted.';

redirect('index/51');
exit();
