<?php
declare(strict_types=1);

// admin/edit-family/{id}
// $id is the member id being edited (from routing)

// -------------------------
// GUARDS (fail closed)
// -------------------------
$sessionMemberId = (int) ($_SESSION['id'] ?? 0);
$role = (string) ($_SESSION['role'] ?? '');
$websiteId = (int) ($_SESSION['website'] ?? 0);

if ($sessionMemberId <= 0) {
    redirect('login'); // or redirect('index/1');
}

// Admin-only page (adjust if you want member self-service later)
if (!in_array($role, ['admin', 'uber'], true)) {
    redirect('page-not-found/');
}

$targetMemberId = (int) ($id ?? 0);
if ($targetMemberId <= 0) {
    redirect('page-not-found/');
}

// Load session member + target member (fail closed)
$sessionMember = $cms->getMember()->get($sessionMemberId);
if (!$sessionMember || !isset($sessionMember['id'])) {
    redirect('login');
}

$targetMember = $cms->getMember()->get($targetMemberId);
if (!$targetMember || !isset($targetMember['id'])) {
    redirect('page-not-found/');
}

// Website scope guard (prevents cross-website editing)
if ((int) $targetMember['website'] !== $websiteId && $role !== 'uber') {
    redirect('page-not-found/');
}

// -------------------------
// POST: update family link
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $familyId = (int) ($_POST['family_id'] ?? 0);
    if ($familyId <= 0) {
        redirect('admin/members/', ['failure' => 'Invalid family selection']);
    }

    $fromMember = $cms->getMember()->get($familyId);
    if (!$fromMember || !isset($fromMember['id'])) {
        redirect('admin/members/', ['failure' => 'Selected family member not found']);
    }

    // Ensure selected family member is in same website (unless uber)
    if ((int) $fromMember['website'] !== $websiteId && $role !== 'uber') {
        redirect('admin/members/', ['failure' => 'Not permitted']);
    }

    // IMPORTANT: do not trust any browser-supplied account_id/website/etc.
    // Only update the intended field on the intended target member.
    $update = $targetMember;
    $update['account_id'] = (int) $fromMember['id'];

    $cms->getMember()->update($update);

    redirect('admin/members/', ['success' => 'Family updated']);
}

// -------------------------
// GET: render page
// -------------------------
$data = [];
$data['member'] = $targetMember;
$data['website'] = $cms->getWebsite()->getById($websiteId);

// Allowed families for this target member (your existing API expects an array of ids)
$allowed = $cms->getFamily()->getByAllowed([$targetMemberId]);
if (!is_array($allowed)) {
    $allowed = [];
}

// Add “self” option (your old push logic), but guard against missing keys
$selfRow = [
    'id' => (int) $targetMember['id'],
    'account_id' => (int) $targetMember['id'],
    'to_name' => trim(($targetMember['forename'] ?? '') . ' ' . ($targetMember['surname'] ?? '')),
    'from_id' => (int) $targetMember['id'],
    'family_id' => (int) $targetMember['id'],
    'to_family_id' => (int) $targetMember['id'],
    'allow' => 1,
];

$allowed[] = $selfRow;
$data['families'] = $allowed;

echo $twig->render('admin/edit-family.html', $data);
