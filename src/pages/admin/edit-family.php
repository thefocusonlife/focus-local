<?php
declare(strict_types=1);

// admin/edit-family/{id}
// $id comes from routing: the member id whose family link is being edited.

$sessionMemberId = (int) ($_SESSION['id'] ?? 0);
if ($sessionMemberId <= 0) {
    redirect('login');
}

$role = (string) ($_SESSION['role'] ?? ''); // keep if you later want uber override
$websiteId = (int) ($_SESSION['website'] ?? 0);

$targetMemberId = (int) ($id ?? 0);
if ($targetMemberId <= 0) {
    redirect('page-not-found/');
}

// OWNER-ONLY hardening (if you want uber override, add: && $role !== 'uber')
if ($targetMemberId !== $sessionMemberId) {
    redirect('page-not-found/');
}

// Load target member for BOTH GET and POST
$targetMember = $cms->getMember()->get($targetMemberId);
if (!$targetMember || !isset($targetMember['id'])) {
    redirect('page-not-found/');
}

// Website scope guard (optional but recommended even for owner-only)
if ((int) ($targetMember['website'] ?? 0) !== $websiteId) {
    redirect('page-not-found/');
}

// Build allowed list for dropdown AND for POST validation
$allowedRows = $cms->getFamily()->getByAllowed([$targetMemberId]);
if (!is_array($allowedRows)) {
    $allowedRows = [];
}

// Collect allowed ids (based on the same field your Twig uses: to_family_id)
$allowedIds = [];
foreach ($allowedRows as $row) {
    if (isset($row['to_family_id'])) {
        $allowedIds[(int) $row['to_family_id']] = true;
    }
}
// Always allow "self"
$allowedIds[$targetMemberId] = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $familyId = (int) ($_POST['family_id'] ?? 0);
    if ($familyId <= 0) {
        redirect('page-not-found/');
    }

    // Tamper-proof: must be in allowed set
    if (!isset($allowedIds[$familyId])) {
        redirect('page-not-found/');
    }

    // Update only intended field
    $update = $targetMember;
    $update['account_id'] = $familyId;

    $cms->getMember()->update($update);
    redirect('admin/members/', ['success' => 'Family updated']);
}

// -------------------------
// GET: render
// -------------------------
$data = [];
$data['member'] = $targetMember;
$data['website'] = $cms->getWebsite()->getById($websiteId);

// Add a "self" row for the dropdown so current member always appears
$selfRow = [
    'to_family_id' => $targetMemberId,
    'to_name' => trim(($targetMember['forename'] ?? '') . ' ' . ($targetMember['surname'] ?? '')),
];

// Ensure dropdown rows have the fields Twig expects: to_family_id, to_name
$families = $allowedRows;
$families[] = $selfRow;

$data['families'] = $families;

echo $twig->render('admin/edit-family.html', $data);
