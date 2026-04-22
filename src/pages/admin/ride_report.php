<?php

include APP_ROOT . '/src/pages/menu-path.php';

require_once APP_ROOT . '/src/security/guard.php';
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

$filters = [
    'from_date' => $_GET['from_date'] ?? '',
    'to_date' => $_GET['to_date'] ?? '',
    'member_id' => $_GET['member_id'] ?? '',
    'ride_type' => $_GET['ride_type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'gpx_uploaded' => $_GET['gpx_uploaded'] ?? '',
];

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

echo $twig->render('admin/ride_report.html', $data);
