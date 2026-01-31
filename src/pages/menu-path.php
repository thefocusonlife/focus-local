<?php
declare(strict_types=1);

$id = null;

$path = mb_strtolower($_SERVER['REQUEST_URI']);
$path = substr($path, strlen(DOC_ROOT));
$path = trim($path, '/');

$websiteId = (int) ($_SESSION['website'] ?? 1);
if ($websiteId <= 0) {
    $websiteId = 1;
}

$path = trim($path, '/'); // important: normalizes /admin/index/ and /admin/index
$parts = explode('/', $path);

$parts = $path === '' ? [] : explode('/', $path);

// ✅ Alias: /admin -> /admin/index
if (($parts[0] ?? '') === 'admin') {
    $page = 'admin/' . ($parts[1] ?? 'index'); // admin/index, admin/website, etc
    $id = (int) ($parts[2] ?? 0); // optional
} else {
    $page = $parts[0] ?? 'index';
    $id = (int) ($parts[1] ?? 0);
}
error_log(
    '[menu-path] uri=' .
        $_SERVER['REQUEST_URI'] .
        ' path=' .
        $path .
        ' parts=' .
        json_encode($parts),
);
error_log('[menu-path] page=' . ($page ?? 'NULL') . ' id=' . ($id ?? 'NULL'));

// Admin routes: /admin/<page>/<id>
if ($page === 'admin') {
    $page = 'admin/' . ($parts[1] ?? 'index');
    $id = isset($parts[2]) && $parts[2] !== '' ? (int) $parts[2] : null;
} else {
    // Public routes: /<page>/<id>
    $id = isset($parts[1]) && $parts[1] !== '' ? (int) $parts[1] : null;
}

if (empty($_SESSION['id'])) {
    $websiteId = 1;
} else {
    $websiteId = (int) ($_SESSION['website'] ?? 1);
}

$website = $cms->getWebsite()->getById($websiteId);

if (!$website || !isset($website['id'])) {
    // absolute last-ditch safety
    $website = $cms->getWebsite()->getById(1);
}
