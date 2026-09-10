<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';

$websiteId = filter_input(INPUT_GET, 'website', FILTER_VALIDATE_INT);
$supportedWebsiteIds = [44, 51];

if (!is_int($websiteId) || !in_array($websiteId, $supportedWebsiteIds, true)) {
    $_SESSION['flash_failure'] = 'A valid club website is required.';
    redirect('websites');
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

if ($viewerId <= 0 || $viewerId === 2 || $role === 'guest') {
    $_SESSION['return_to'] = '/club-members-admin?website=' . $websiteId;

    $_SESSION['flash_failure'] = 'You must be logged in to export club members.';

    redirect('login');
    exit();
}

$allowedMemberAdmins = [
    44 => [1, 3, 339, 500],
    51 => [1],
];

if (!in_array($viewerId, $allowedMemberAdmins[$websiteId], true)) {
    $_SESSION['flash_failure'] = 'You do not have permission to export club members.';

    redirect('index/' . $websiteId);
    exit();
}

$membersStmt = $cms->getClubMembers()->getAll($websiteId);

$members = $membersStmt instanceof PDOStatement ? $membersStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$filenamePrefixes = [
    44 => 'cobc-members',
    51 => 'central-oregon-chess-members',
];

$filename = $filenamePrefixes[$websiteId] . '-' . date('Y-m-d') . '.csv';

function csvCell($value): string
{
    $value = (string) $value;

    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
        return "'" . $value;
    }

    return $value;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'wb');

if ($output === false) {
    exit();
}

fputcsv(
    $output,
    [
        'ID',
        'Website ID',
        'Member ID',
        'First Name',
        'Last Name',
        'Email',
        'Phone',
        'Date of Birth',
        'Guardian Name',
        'Guardian Email',
        'Guardian Phone',
        'Emergency Name',
        'Emergency Phone',
        'Emergency Relationship',
        'Parental Authorization Accepted',
        'Parental Authorization Accepted At',
        'Membership Status',
        'Created At',
        'Updated At',
    ],
    ',',
    '"',
    '',
);

foreach ($members as $member) {
    $row = [
        $member['id'] ?? '',
        $member['website_id'] ?? '',
        $member['member_id'] ?? '',
        $member['first_name'] ?? '',
        $member['last_name'] ?? '',
        $member['email'] ?? '',
        $member['phone'] ?? '',
        $member['date_of_birth'] ?? '',
        $member['guardian_name'] ?? '',
        $member['guardian_email'] ?? '',
        $member['guardian_phone'] ?? '',
        $member['emergency_name'] ?? '',
        $member['emergency_phone'] ?? '',
        $member['emergency_relationship'] ?? '',
        $member['parental_authorization_accepted'] ?? '',
        $member['parental_authorization_accepted_at'] ?? '',
        $member['membership_status'] ?? '',
        $member['created_at'] ?? '',
        $member['updated_at'] ?? '',
    ];

    fputcsv($output, array_map('csvCell', $row), ',', '"', '');
}

fclose($output);
exit();
