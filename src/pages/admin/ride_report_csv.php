<?php

include APP_ROOT . '/src/pages/menu-path.php';

require_once APP_ROOT . '/src/security/guard.php';

function formatMinutesToTime($minutes)
{
    if (!$minutes && $minutes !== 0) {
        return '';
    }

    $hours = floor($minutes / 60);
    $mins = $minutes % 60;

    return sprintf('%d:%02d', $hours, $mins);
}

guardMember();

is_admin($session->role);

$member = $cms->getMember()->get($_SESSION['id']);
if (!$_SESSION['id']) {
    $website = $cms->getWebsite()->getByID(1);
} else {
    $website = $cms->getWebsite()->getByID($member['website']);
}

if (!$website || (int) ($website['id'] ?? 0) !== 44) {
    http_response_code(403);
    exit('Access denied.');
}

// Read same filters as the report page
$filters = [
    'from_date' => $_GET['from_date'] ?? '',
    'to_date' => $_GET['to_date'] ?? '',
    'member_id' => $_GET['member_id'] ?? '',
    'ride_type' => $_GET['ride_type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'gpx_uploaded' => $_GET['gpx_uploaded'] ?? '',
];

// Reuse same SQL logic from the report page
$data = [];
$data['website'] = $website;
$data['filters'] = $filters;
$data['members'] = $cms->getRide()->getMembersByWebsiteId(44);
$data['summary'] = $cms->getRide()->getSummaryByWebsiteId(44, $filters);
$data['rides'] = $cms->getRide()->getReportRowsByWebsiteId(44, $filters);
$data['ride_type_options'] = [
    'Road',
    'Gravel',
    'Mountain',
    'Virtual',
    'Hybrid',
    'Casual',
    'Commute',
    'Event',
];

// Use prepared statements if filters are dynamic

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="bicycle_statistics_report.csv"');

$output = fopen('php://output', 'w');

// Excel-friendly header row
// CSV header row
fputcsv($output, [
    'Date',
    'Member',
    'Ride',
    'Miles',
    'Time',
    'Ascent',
    'Avg Speed',
    'Max Speed',
    'Avg Power',
    'Max Power',
    'Avg HR',
    'Max HR',
    'Notes',
]);

foreach ($data['rides'] as $row) {
    fputcsv($output, [
        $row['ride_date'],
        $row['member_name'],
        $row['title'],
        $row['distance_miles'],
        formatMinutesToTime($row['elapsed_minutes']), // 👈 formatted
        $row['elevation_gain_ft'],
        $row['avg_speed_mph'],
        $row['max_speed_mph'],
        $row['avg_power_watts'],
        $row['max_power_watts'],
        $row['avg_heart_rate'],
        $row['max_heart_rate'],
        $row['notes'],
    ]);
}
// Totals row
fputcsv($output, [
    'TOTAL',
    '',
    '',
    $data['summary']['total_miles'] ?? '',
    '',
    $data['summary']['total_elevation'] ?? '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
]);

fclose($output);
exit();
