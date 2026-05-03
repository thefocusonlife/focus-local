<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? ''));

if ($viewerId <= 0 || $role === 'guest') {
    $_SESSION['return_to'] = '/club-members-admin?website=44';
    $_SESSION['flash_failure'] = 'You must be logged in to export club members.';
    redirect('login');
    exit();
}

$websiteId = (int) ($_GET['website'] ?? 44);
if ($websiteId !== 44) {
    $websiteId = 44;
}

$members = $cms->getClubMembers()->getAll($websiteId);

$filename = 'club-members-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'ID',
    'Website ID',
    'First Name',
    'Last Name',
    'Address 1',
    'Address 2',
    'City',
    'State',
    'Zip',
    'Email',
    'Phone',
    'Emergency Name',
    'Emergency Phone',
    'Emergency Relationship',
    'Primary Ride Type',
    'Other Ride Type',
    'Riding Level',
    'Preferred Distance',
    'Medical Notes',
    'Liability Release Accepted',
    'Liability Release Accepted At',
    'Created At',
]);

foreach ($members as $member) {
    fputcsv($output, [
        $member['id'] ?? '',
        $member['website_id'] ?? '',
        $member['first_name'] ?? '',
        $member['last_name'] ?? '',
        $member['address1'] ?? '',
        $member['address2'] ?? '',
        $member['city'] ?? '',
        $member['state'] ?? '',
        $member['zip'] ?? '',
        $member['email'] ?? '',
        $member['phone'] ?? '',
        $member['emergency_name'] ?? '',
        $member['emergency_phone'] ?? '',
        $member['emergency_relationship'] ?? '',
        $member['primary_ride_type'] ?? '',
        $member['other_ride_type'] ?? '',
        $member['riding_level'] ?? '',
        $member['preferred_distance'] ?? '',
        $member['medical_notes'] ?? '',
        $member['liability_release_accepted'] ?? '',
        $member['liability_release_accepted_at'] ?? '',
        $member['created_at'] ?? '',
    ]);
}

fclose($output);
exit();
