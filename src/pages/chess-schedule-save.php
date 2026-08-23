<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index/51');
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));
$isLoggedIn = $viewerId > 0 && $role !== 'guest';

if (!$isLoggedIn) {
    $_SESSION['member_required'] = true;
    $_SESSION['return_website'] = 51;
    $_SESSION['return_to'] = DOC_ROOT . 'chess-schedule-add';
    $_SESSION['member_required_reason'] =
        'Managing Chess Nights requires Central Oregon Chess administrator access.';

    header('Location: ' . DOC_ROOT . 'login/51');
    exit();
}

if ($viewerId !== 1) {
    $_SESSION['flash_failure'] = 'You do not have permission to manage scheduled Chess Nights.';

    redirect('index/51');
    exit();
}

$id = (int) ($_POST['id'] ?? 0);
$websiteId = 51;

$title = trim((string) ($_POST['title'] ?? ''));
$eventDate = trim((string) ($_POST['event_date'] ?? ''));
$startTime = trim((string) ($_POST['start_time'] ?? ''));
$endTime = trim((string) ($_POST['end_time'] ?? ''));
$location = trim((string) ($_POST['location'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$isActive = isset($_POST['is_active']) ? 1 : 0;

$formUrl = $id > 0 ? 'chess-schedule-edit/' . $id : 'chess-schedule-add';

if ($title === '' || $eventDate === '' || $startTime === '' || $location === '') {
    $_SESSION['flash_failure'] = 'Title, event date, start time, and location are required.';

    redirect($formUrl);
    exit();
}

$dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $eventDate);
$dateErrors = DateTimeImmutable::getLastErrors();

$dateIsValid =
    $dateObject !== false &&
    ($dateErrors === false ||
        ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0)) &&
    $dateObject->format('Y-m-d') === $eventDate;

if (!$dateIsValid) {
    $_SESSION['flash_failure'] = 'Please enter a valid event date.';
    redirect($formUrl);
    exit();
}

$timePattern = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';

if (!preg_match($timePattern, $startTime)) {
    $_SESSION['flash_failure'] = 'Please enter a valid start time.';
    redirect($formUrl);
    exit();
}

if ($endTime !== '' && !preg_match($timePattern, $endTime)) {
    $_SESSION['flash_failure'] = 'Please enter a valid ending time.';
    redirect($formUrl);
    exit();
}

if ($endTime !== '' && $endTime <= $startTime) {
    $_SESSION['flash_failure'] = 'The ending time must be later than the starting time.';

    redirect($formUrl);
    exit();
}

if ($id > 0) {
    $existingStmt = $cms->getDb()->runSql(
        'SELECT id
           FROM chess_schedule
          WHERE id = :id
            AND website_id = :website_id
          LIMIT 1',
        [
            'id' => $id,
            'website_id' => $websiteId,
        ],
    );

    if (!$existingStmt->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['flash_failure'] = 'Chess Night not found.';
        redirect('index/51');
        exit();
    }

    $cms->getDb()->runSql(
        'UPDATE chess_schedule
            SET title = :title,
                event_date = :event_date,
                start_time = :start_time,
                end_time = :end_time,
                location = :location,
                description = :description,
                is_active = :is_active,
                member_id = :member_id
          WHERE id = :id
            AND website_id = :website_id',
        [
            'title' => $title,
            'event_date' => $eventDate,
            'start_time' => $startTime,
            'end_time' => $endTime !== '' ? $endTime : null,
            'location' => $location,
            'description' => $description !== '' ? $description : null,
            'is_active' => $isActive,
            'member_id' => $viewerId,
            'id' => $id,
            'website_id' => $websiteId,
        ],
    );

    $_SESSION['flash_success'] = 'Chess Night updated.';
    redirect('index/51');
    exit();
}

$cms->getDb()->runSql(
    'INSERT INTO chess_schedule (
        website_id,
        member_id,
        title,
        event_date,
        start_time,
        end_time,
        location,
        description,
        is_active
    ) VALUES (
        :website_id,
        :member_id,
        :title,
        :event_date,
        :start_time,
        :end_time,
        :location,
        :description,
        :is_active
    )',
    [
        'website_id' => $websiteId,
        'member_id' => $viewerId,
        'title' => $title,
        'event_date' => $eventDate,
        'start_time' => $startTime,
        'end_time' => $endTime !== '' ? $endTime : null,
        'location' => $location,
        'description' => $description !== '' ? $description : null,
        'is_active' => $isActive,
    ],
);

$_SESSION['flash_success'] = 'Chess Night added.';

redirect('index/51');
exit();
