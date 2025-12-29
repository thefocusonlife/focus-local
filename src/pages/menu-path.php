<?php
declare(strict_types=1);
$id = 2;

$path = mb_strtolower($_SERVER['REQUEST_URI']);
$path = substr($path, strlen(DOC_ROOT));
$path = trim($path, '/');

if ($path === '') {
    $path = 'index/1';
}

$parts = explode('/', $path);

$page = $parts[0] ?? 'index';

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
