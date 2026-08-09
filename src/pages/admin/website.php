<?php
use PhpBook\Validate\Validate; // Import Validate namespace

/** @var array<string,mixed> $data */
/** @var \CMS\Session $session */
is_admin($session->role); // Keep for now

$sessionUserId = (int) ($cms->getSession()->id ?? 0);
$sessionRole = (string) ($cms->getSession()->role ?? 'guest');

if ($sessionUserId !== 1) {
    error_log(
        sprintf(
            '[DENY][admin/website] uid=%d role=%s routeWebsiteId=%s ip=%s',
            $sessionUserId,
            $sessionRole,
            (string) ($id ?? ''),
            $_SERVER['REMOTE_ADDR'] ?? '',
        ),
    );
    // Ensure flash exists even if redirect() doesn't set it
    $_SESSION['flash_failure'] = 'Access denied.';

    // Also send query fallback in case index page only reads $_GET['failure']
    redirect('index/', ['failure' => 'Access denied.']);
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
// Load flash failure if set
if (!empty($_SESSION['flash_failure'])) {
    $data['flash_failure'] = $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}

// Load flash success if set
if (!empty($_SESSION['flash_success'])) {
    $data['flash_success'] = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
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
            redirect('/admin/websites/', ['failure' => 'Website not found']);
            exit();
        }
        $website = array_merge($website, $existing);
    }
    $websiteMembers = [];

    if ($routeWebsiteId > 0) {
        $websiteMembers = $cms->getMember()->getAll2($routeWebsiteId);
    }

    $data = array_merge($data, [
        'website' => $website,
        'errors' => $errors,
        'sorttypes' => $sorttypes,
        'website_members' => $websiteMembers,
        'csrf_token' => generate_csrf_token(),
    ]);

    echo $twig->render('admin/website.html', $data);
    exit();
}

// ============================================================
// E) POST: update vs create (your block, fixed id usage)
// ============================================================

$posted = (string) ($_POST['csrf'] ?? '');

if ($posted === '' || !verify_csrf($posted)) {
    redirect('/admin/websites/', ['failure' => 'Security check failed (CSRF).']);
    exit();
}

$postId = (int) ($_POST['website_id'] ?? 0);
$isUpdate = $postId > 0;

// Deep-link safety (basic): route id must match posted id
// Create: route=0 post=0 ok. Update: route=4 post=4 ok.
if ($routeWebsiteId !== $postId) {
    redirect('/admin/websites/', ['failure' => 'Invalid request.']);
    exit();
}

// Load existing row on update (prevents wiping image_file)
$existing = [];
if ($isUpdate) {
    $existing = $cms->getWebsite()->get($postId);
    if (!$existing) {
        redirect('/admin/websites/', ['failure' => 'Website not found']);
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

$website['uber_id'] =
    isset($_POST['uber_id']) && $_POST['uber_id'] !== '' ? (int) $_POST['uber_id'] : null;

$website['name'] = trim((string) ($_POST['name'] ?? ''));
$website['sorttype'] = (int) ($_POST['sorttype'] ?? $website['sorttype']);
$website['non_members'] = isset($_POST['non_members']) ? 1 : 0;
$website['blog'] = isset($_POST['blog']) ? 1 : 0;

// alt (template may hide it)
$website['alt'] = trim((string) ($_POST['image_alt'] ?? ($website['alt'] ?? '')));

// Upload handling (Twig uses name="image")
$tmp = $_FILES['image']['tmp_name'] ?? '';
$fileErr = (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE);
$hasUpload = $fileErr === UPLOAD_ERR_OK && $tmp && is_uploaded_file($tmp);

$uploadDir = realpath(__DIR__ . '/../../../public/img') . '/';

$st = @stat($uploadDir);

$tmp = $_FILES['image']['tmp_name'] ?? '';
$fileErr = (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE);
$hasUpload = $fileErr === UPLOAD_ERR_OK && $tmp && is_uploaded_file($tmp);

$pendingMove = null; // ['tmp' => ..., 'dest' => ...]
if ($hasUpload) {
    $ext = strtolower(pathinfo((string) ($_FILES['image']['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, FILE_EXTENSIONS, true)) {
        $errors['image_file'] = 'Wrong file extension.';
    } else {
        $website['image_file'] = create_filename((string) $_FILES['image']['name'], $uploadDir);
        $destination = $uploadDir . $website['image_file'];
        $pendingMove = ['tmp' => $tmp, 'dest' => $destination];
    }
} else {
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

    $websiteMembers = [];

    if ($routeWebsiteId > 0) {
        $websiteMembers = $cms->getMember()->getAll2($routeWebsiteId);
    }

    $data = array_merge($data, [
        'website' => $website,
        'errors' => $errors,
        'sorttypes' => $sorttypes,
        'website_members' => $websiteMembers,
        'csrf_token' => generate_csrf_token(),
    ]);

    echo $twig->render('admin/website.html', $data);
    exit();
}

// SAVE split (single update block / single create block)
if ($isUpdate) {
    $affected = $cms->getWebsite()->update($website);

    if ($affected === 0) {
        redirect('/admin/websites/', ['success' => 'No changes to save']);
        exit();
    }

    redirect('/admin/websites/', ['success' => 'Website saved']);
    exit();
}

// Create
unset($website['id']); // safety

$pdo = $cms->getDb(); // Database extends PDO

$pendingMovedPath = null;

try {
    $pdo->beginTransaction();

    $newWebsiteId = (int) $cms->getWebsite()->create($website);

    if ($newWebsiteId === -1) {
        $pdo->rollBack();

        redirect('/admin/websites/', ['failure' => 'Website already exists']);
        exit();
    }
    if ($newWebsiteId <= 0) {
        throw new RuntimeException("Bad newWebsiteId={$newWebsiteId}");
    }

    if ($pendingMove) {
        if (!move_uploaded_file($pendingMove['tmp'], $pendingMove['dest'])) {
            throw new RuntimeException('move_uploaded_file failed');
        }
    }

    $setup = new \PhpBook\CMS\SetupService($pdo);

    $result = $setup->copyUberMenusToWebsite($newWebsiteId);

    $pdo->commit();

    redirect('/admin/websites/', ['success' => 'Website created']);
    exit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    redirect('/admin/websites/', ['failure' => 'Create failed. See debug_create.log']);
    exit();
}
