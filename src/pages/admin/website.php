<?php
use PhpBook\Validate\Validate; // Import Validate namespace

is_admin($session->role); // Keep for now

// ------------------------------------------------------------
// A) Uber-only (Week 2 hardening can wait; but keep basic gate)
// IMPORTANT: do NOT reuse $id for session user id
// ------------------------------------------------------------
$sessionUserId = (int) ($cms->getSession()->id ?? 0);
if ($sessionUserId !== 1) {
    redirect('index/');
    exit();
}

// ------------------------------------------------------------
// B) Route website id (0=create, >0=edit)
// Use $id if router provides it; fallback to $parts[2] (admin/website/{id})
// ------------------------------------------------------------
$routeWebsiteId = isset($id) ? (int) $id : 0;
if ($routeWebsiteId <= 0 && isset($parts[2]) && ctype_digit((string) $parts[2])) {
    $routeWebsiteId = (int) $parts[2];
}

// ------------------------------------------------------------
// C) Defaults for template (CREATE mode)
// ------------------------------------------------------------
$website = [
    'id' => $routeWebsiteId,
    'uber_id' => null, // create default
    'sorttype' => 2,
    'name' => '',
    'image_file' => '',
    'alt' => '',
    'non_members' => 0,
    'blog' => 0,
];

$errors = [
    'warning' => '',
    'name' => '',
    'image_file' => '',
    'image_alt' => '',
    'non_members' => 0,
];

// Sorttypes always needed for dropdown
$sorttypes = $cms->getSorttype()->getAllSorttypes();

// ------------------------------------------------------------
// D) GET: load existing row if editing
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($routeWebsiteId > 0) {
        $existing = $cms->getWebsite()->get($routeWebsiteId);
        if (!$existing) {
            redirect('admin/websites/', ['failure' => 'Website not found']);
            exit();
        }
        $website = array_merge($website, $existing);
    }

    $data = [
        'website' => $website,
        'errors' => $errors,
        'sorttypes' => $sorttypes,
    ];

    echo $twig->render('admin/website.html', $data);
    exit();
}

// ============================================================
// E) POST: update vs create (your block, fixed id usage)
// ============================================================

$postId = (int) ($_POST['website_id'] ?? 0);
$isUpdate = $postId > 0;

// Deep-link safety (basic): route id must match posted id
// Create: route=0 post=0 ok. Update: route=4 post=4 ok.
if ($routeWebsiteId !== $postId) {
    redirect('admin/websites/', ['failure' => 'Invalid request.']);
    exit();
}

// Load existing row on update (prevents wiping image_file)
$existing = [];
if ($isUpdate) {
    $existing = $cms->getWebsite()->get($postId);
    if (!$existing) {
        redirect('admin/websites/', ['failure' => 'Website not found']);
        exit();
    }
}

// Build $website from POST (start from existing if update)
$website = $isUpdate
    ? $existing
    : [
        'id' => 0,
        'uber_id' => null,
        'sorttype' => 2,
        'name' => '',
        'image_file' => '',
        'alt' => '',
        'non_members' => 0,
        'blog' => 0,
    ];

$website['id'] = $postId;
$website['name'] = trim((string) ($_POST['name'] ?? ''));
$website['sorttype'] = (int) ($_POST['sorttype'] ?? $website['sorttype']);
$website['non_members'] = isset($_POST['non_members']) ? 1 : 0;
$website['blog'] = isset($_POST['blog']) ? 1 : 0;

// alt (template may hide it)
$website['alt'] = trim((string) ($_POST['alt'] ?? ($website['alt'] ?? '')));

// Upload handling (Twig uses name="image")
$tmp = $_FILES['image']['tmp_name'] ?? '';
$fileErr = (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE);
$hasUpload = $fileErr === UPLOAD_ERR_OK && $tmp && is_uploaded_file($tmp);

$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/focus-local/public/img/';

if ($hasUpload) {
    $ext = strtolower(pathinfo((string) ($_FILES['image']['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, FILE_EXTENSIONS, true)) {
        $errors['image_file'] = 'Wrong file extension.';
    } else {
        $website['image_file'] = create_filename((string) $_FILES['image']['name'], $uploadDir);
        $destination = $uploadDir . $website['image_file'];

        if (!move_uploaded_file($tmp, $destination)) {
            $errors['image_file'] = 'Upload failed.';
        }
    }
} else {
    // Create requires image_file because DB column is NOT NULL
    if (!$isUpdate && ($website['image_file'] ?? '') === '') {
        $errors['image_file'] = 'Please upload an image.';
    }
}

// Minimal validation for now
$errors['name'] = Validate::isText($website['name'], 1, 254)
    ? ''
    : 'Name should be 1–254 characters.';

if (!empty($errors['name']) || !empty($errors['image_file'])) {
    $errors['warning'] = 'Please correct form errors.';

    $data = [
        'website' => $website,
        'errors' => $errors,
        'sorttypes' => $sorttypes,
    ];

    echo $twig->render('admin/website.html', $data);
    exit();
}

// SAVE split (single update block / single create block)
if ($isUpdate) {
    $cms->getWebsite()->update($website);
    redirect('admin/websites/', ['success' => 'Website saved']);
    exit();
}

// Create
unset($website['id']); // safety
error_log(print_r($website, true));
$cms->getWebsite()->create($website);
redirect('admin/websites/', ['success' => 'Website created']);
exit();
