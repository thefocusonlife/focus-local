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
    $_SESSION['return_to'] = DOC_ROOT . 'chess-meetup-add';
    $_SESSION['member_required_reason'] =
        'Managing a Chess Game Meetup requires Central Oregon Chess membership.';

    header('Location: ' . DOC_ROOT . 'login/51');
    exit();
}

$isChessMember = $viewerId === 1 || $cms->getMember()->isMemberOfWebsite($viewerId, 51);

if (!$isChessMember) {
    $_SESSION['flash_failure'] = 'Managing a Meetup requires Central Oregon Chess membership.';

    redirect('index/51');
    exit();
}

$id = (int) ($_POST['id'] ?? 0);
$websiteId = 51;

$allowedAreas = ['Bend', 'Redmond', 'Prineville', 'Central Oregon', 'Online'];

$allowedTypes = ['In Person', 'Chess.com', 'Lichess', 'Other Online'];

$allowedLevels = ['', 'Beginner', 'Intermediate', 'Advanced'];

$allowedStatuses = ['Open', 'Filled'];

$title = trim((string) ($_POST['title'] ?? ''));
$area = trim((string) ($_POST['area'] ?? ''));
$meetupType = trim((string) ($_POST['meetup_type'] ?? ''));
$meetupDate = trim((string) ($_POST['meetup_date'] ?? ''));
$startTime = trim((string) ($_POST['start_time'] ?? ''));
$endTime = trim((string) ($_POST['end_time'] ?? ''));
$location = trim((string) ($_POST['location'] ?? ''));
$playerLevel = trim((string) ($_POST['player_level'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$status = trim((string) ($_POST['status'] ?? ''));
$isActive = isset($_POST['is_active']) ? 1 : 0;

$formUrl = $id > 0 ? 'chess-meetup-edit/' . $id : 'chess-meetup-add';

if ($title === '' || $meetupDate === '' || $startTime === '' || $location === '') {
    $_SESSION['flash_failure'] = 'Title, meetup date, start time, and location are required.';

    redirect($formUrl);
    exit();
}

if (!in_array($area, $allowedAreas, true)) {
    $_SESSION['flash_failure'] = 'Please select a valid Chess area.';
    redirect($formUrl);
    exit();
}

if (!in_array($meetupType, $allowedTypes, true)) {
    $_SESSION['flash_failure'] = 'Please select a valid Meetup format.';
    redirect($formUrl);
    exit();
}

if (!in_array($playerLevel, $allowedLevels, true)) {
    $_SESSION['flash_failure'] = 'Please select a valid preferred playing level.';

    redirect($formUrl);
    exit();
}

if (!in_array($status, $allowedStatuses, true)) {
    $_SESSION['flash_failure'] = 'Please select a valid Meetup status.';
    redirect($formUrl);
    exit();
}

$dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $meetupDate);

$dateErrors = DateTimeImmutable::getLastErrors();

$dateIsValid =
    $dateObject !== false &&
    ($dateErrors === false ||
        ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0)) &&
    $dateObject->format('Y-m-d') === $meetupDate;

if (!$dateIsValid) {
    $_SESSION['flash_failure'] = 'Please enter a valid Meetup date.';
    redirect($formUrl);
    exit();
}

$today = new DateTimeImmutable('today');

if ($dateObject < $today) {
    $_SESSION['flash_failure'] = 'The Meetup date cannot be earlier than today.';

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
        'SELECT id, member_id
           FROM chess_meetup
          WHERE id = :id
            AND website_id = :website_id
          LIMIT 1',
        [
            'id' => $id,
            'website_id' => $websiteId,
        ],
    );

    $existingMeetup = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingMeetup) {
        $_SESSION['flash_failure'] = 'Chess Game Meetup not found.';
        redirect('index/51');
        exit();
    }

    $isOwner = (int) $existingMeetup['member_id'] === $viewerId;
    $isUberAdmin = $viewerId === 1;

    if (!$isOwner && !$isUberAdmin) {
        $_SESSION['flash_failure'] = 'You do not have permission to edit this Meetup.';

        redirect('index/51');
        exit();
    }

    $cms->getDb()->runSql(
        'UPDATE chess_meetup
            SET title = :title,
                area = :area,
                meetup_type = :meetup_type,
                meetup_date = :meetup_date,
                start_time = :start_time,
                end_time = :end_time,
                location = :location,
                player_level = :player_level,
                description = :description,
                status = :status,
                is_active = :is_active
          WHERE id = :id
            AND website_id = :website_id',
        [
            'title' => $title,
            'area' => $area,
            'meetup_type' => $meetupType,
            'meetup_date' => $meetupDate,
            'start_time' => $startTime,
            'end_time' => $endTime !== '' ? $endTime : null,
            'location' => $location,
            'player_level' => $playerLevel !== '' ? $playerLevel : null,
            'description' => $description !== '' ? $description : null,
            'status' => $status,
            'is_active' => $isActive,
            'id' => $id,
            'website_id' => $websiteId,
        ],
    );

    $_SESSION['flash_success'] = 'Chess Game Meetup updated.';
    redirect('index/51');
    exit();
}

$cms->getDb()->runSql(
    'INSERT INTO chess_meetup (
        website_id,
        member_id,
        title,
        area,
        meetup_type,
        meetup_date,
        start_time,
        end_time,
        location,
        player_level,
        description,
        status,
        is_active
    ) VALUES (
        :website_id,
        :member_id,
        :title,
        :area,
        :meetup_type,
        :meetup_date,
        :start_time,
        :end_time,
        :location,
        :player_level,
        :description,
        :status,
        :is_active
    )',
    [
        'website_id' => $websiteId,
        'member_id' => $viewerId,
        'title' => $title,
        'area' => $area,
        'meetup_type' => $meetupType,
        'meetup_date' => $meetupDate,
        'start_time' => $startTime,
        'end_time' => $endTime !== '' ? $endTime : null,
        'location' => $location,
        'player_level' => $playerLevel !== '' ? $playerLevel : null,
        'description' => $description !== '' ? $description : null,
        'status' => $status,
        'is_active' => $isActive,
    ],
);

$_SESSION['flash_success'] = 'Chess Game Meetup added.';

redirect('index/51');
exit();
