<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/chess-leaderboard-access.php';
require_once APP_ROOT . '/src/security/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index/51');
    exit();
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$id = is_int($id) && $id > 0 ? $id : 0;

$formUrl = $id > 0 ? 'chess-tournament-edit/' . $id : 'chess-tournament-add';

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

if ($viewerId < 1 || $viewerId === 2 || $role === 'guest') {
    $_SESSION['return_website'] = 51;
    $_SESSION['return_to'] = DOC_ROOT . $formUrl;

    header('Location: ' . DOC_ROOT . 'login/51');
    exit();
}

if (!isChessLeaderboardAdmin($viewerId)) {
    $_SESSION['flash_failure'] = 'You do not have permission to manage Chess tournaments.';

    redirect('index/51');
    exit();
}

$csrfFormKey = 'chess_tournament_save';
$submittedCsrf = $_POST['csrf_token'] ?? '';

if (!csrf_validate($csrfFormKey, is_string($submittedCsrf) ? $submittedCsrf : null)) {
    csrf_rotate($csrfFormKey);

    $_SESSION['flash_failure'] =
        'Your form session expired or failed security validation. Please try again.';

    redirect($formUrl);
    exit();
}

$websiteId = filter_input(INPUT_POST, 'website_id', FILTER_VALIDATE_INT);

if ($websiteId !== 51) {
    $_SESSION['flash_failure'] = 'A valid Central Oregon Chess website is required.';

    redirect($formUrl);
    exit();
}

$allowedAreas = [
    'Bend',
    'Redmond',
    'Sisters',
    'Madras',
    'Prineville',
    'La Pine',
    'Central Oregon',
    'Online',
];

$allowedStatuses = ['Draft', 'Published', 'Cancelled'];

$name = trim((string) ($_POST['name'] ?? ''));
$tournamentDate = trim((string) ($_POST['tournament_date'] ?? ''));
$area = trim((string) ($_POST['area'] ?? ''));
$location = trim((string) ($_POST['location'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? ''));
$status = trim((string) ($_POST['status'] ?? ''));
$isActive = isset($_POST['is_active']) ? 1 : 0;

if ($name === '' || mb_strlen($name) > 150) {
    $_SESSION['flash_failure'] = 'Tournament name is required and cannot exceed 150 characters.';

    redirect($formUrl);
    exit();
}

if (!in_array($area, $allowedAreas, true)) {
    $_SESSION['flash_failure'] = 'Please select a valid tournament area.';

    redirect($formUrl);
    exit();
}

if ($location === '' || mb_strlen($location) > 255) {
    $_SESSION['flash_failure'] =
        'Tournament location is required and cannot exceed 255 characters.';

    redirect($formUrl);
    exit();
}

if (mb_strlen($notes) > 5000) {
    $_SESSION['flash_failure'] = 'Tournament notes cannot exceed 5,000 characters.';

    redirect($formUrl);
    exit();
}

if (!in_array($status, $allowedStatuses, true)) {
    $_SESSION['flash_failure'] = 'Please select a valid tournament status.';

    redirect($formUrl);
    exit();
}

$dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $tournamentDate);

$dateErrors = DateTimeImmutable::getLastErrors();

$dateIsValid =
    $dateObject !== false &&
    ($dateErrors === false ||
        ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0)) &&
    $dateObject->format('Y-m-d') === $tournamentDate;

if (!$dateIsValid) {
    $_SESSION['flash_failure'] = 'Please enter a valid tournament date.';

    redirect($formUrl);
    exit();
}

try {
    if ($id > 0) {
        $existingStmt = $cms->getDb()->runSql(
            "
            SELECT id
            FROM chess_tournament
            WHERE id = :id
              AND website_id = :website_id
            LIMIT 1
            ",
            [
                'id' => $id,
                'website_id' => 51,
            ],
        );

        $existingTournament =
            $existingStmt instanceof PDOStatement ? $existingStmt->fetch(PDO::FETCH_ASSOC) : false;

        if (!$existingTournament) {
            $_SESSION['flash_failure'] = 'Chess tournament not found.';

            redirect('index/51');
            exit();
        }

        $cms->getDb()->runSql(
            "
            UPDATE chess_tournament
            SET name = :name,
                tournament_date = :tournament_date,
                area = :area,
                location = :location,
                notes = :notes,
                status = :status,
                is_active = :is_active
            WHERE id = :id
              AND website_id = :website_id
            ",
            [
                'name' => $name,
                'tournament_date' => $tournamentDate,
                'area' => $area,
                'location' => $location,
                'notes' => $notes !== '' ? $notes : null,
                'status' => $status,
                'is_active' => $isActive,
                'id' => $id,
                'website_id' => 51,
            ],
        );

        csrf_rotate($csrfFormKey);

        $_SESSION['flash_success'] = 'Chess tournament updated successfully.';

        redirect('chess-tournament-edit/' . $id);
        exit();
    }

    $cms->getDb()->runSql(
        "
        INSERT INTO chess_tournament (
            website_id,
            name,
            tournament_date,
            area,
            location,
            notes,
            status,
            is_active,
            created_by
        ) VALUES (
            :website_id,
            :name,
            :tournament_date,
            :area,
            :location,
            :notes,
            :status,
            :is_active,
            :created_by
        )
        ",
        [
            'website_id' => 51,
            'name' => $name,
            'tournament_date' => $tournamentDate,
            'area' => $area,
            'location' => $location,
            'notes' => $notes !== '' ? $notes : null,
            'status' => $status,
            'is_active' => $isActive,
            'created_by' => $viewerId,
        ],
    );

    $idStmt = $cms->getDb()->runSql('SELECT LAST_INSERT_ID() AS id');

    $newId = (int) $idStmt->fetchColumn();

    csrf_rotate($csrfFormKey);

    $_SESSION['flash_success'] = 'Chess tournament added successfully.';

    if ($newId > 0) {
        redirect('chess-tournament-edit/' . $newId);
        exit();
    }

    redirect('index/51');
    exit();
} catch (Throwable $e) {
    error_log('[CHESS TOURNAMENT SAVE] ' . $e->getMessage());

    $_SESSION['flash_failure'] = 'Unable to save the Chess tournament. Please try again.';

    redirect($formUrl);
    exit();
}
