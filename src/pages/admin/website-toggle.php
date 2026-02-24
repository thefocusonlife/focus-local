<?php
declare(strict_types=1);

// Uber-only (match your current rule)
$sessionUserId = (int) ($cms->getSession()->id ?? 0);
if ($sessionUserId !== 1) {
    redirect('index/', ['failure' => 'Access denied.']);
    exit();
}

// Route website id (admin/website-toggle/{id})
$websiteId = isset($id) ? (int) $id : 0;
if ($websiteId <= 0 && isset($parts[2]) && ctype_digit((string) $parts[2])) {
    $websiteId = (int) $parts[2];
}

if ($websiteId <= 0) {
    redirect('admin/websites/', ['failure' => 'Website not found']);
    exit();
}

$website = $cms->getWebsite()->get($websiteId);
if (!$website) {
    redirect('admin/websites/', ['failure' => 'Website not found']);
    exit();
}

// POST only (keep it simple)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/websites/', ['failure' => 'Invalid request.']);
    exit();
}

$postId = (int) ($_POST['website_id'] ?? 0);
if ($postId !== $websiteId) {
    redirect('admin/websites/', ['failure' => 'Invalid request.']);
    exit();
}

$current = (int) ($website['is_active'] ?? 1);
$newValue = $current === 1 ? 0 : 1;

$cms->getWebsite()->setActive($websiteId, $newValue);

redirect('admin/websites/', [
    'success' => $newValue === 1 ? 'Website reactivated' : 'Website deactivated',
]);
exit();
