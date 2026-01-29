<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/security/guard.php';
include APP_ROOT . '/src/pages/menu-path.php';

$errors = [];
$data = [];

$path = mb_strtolower($_SERVER['REQUEST_URI']); // Get path in lowercase
$path = substr($path, strlen(DOC_ROOT)); // Remove up to DOC_ROOT
$parts = explode('/', $path); // Split into array at /

$ownerId = (int) ($parts[1] ?? 0);

// Load the “owner” member record (the one we’re following)
$ownerMember = $cms->getMember()->get($id);

if (!$ownerMember || empty($ownerMember['id'])) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

// Website scope: use owner’s website for menus + stories.
// (Keeps follow consistent: you follow their content + nav.)
$websiteId = (int) ($ownerMember['website'] ?? 0);
if ($websiteId <= 0) {
    $websiteId = (int) ($_SESSION['website'] ?? 1);
    if ($websiteId <= 0) {
        $websiteId = 1;
    }
}

$website = $cms->getWebsite()->getById($websiteId);
if (!$website || !isset($website['id'])) {
    $websiteId = 1;
    $website = $cms->getWebsite()->getById(1);
}
$_SESSION['website'] = $websiteId;

// ------------------------------------------------------------
// Page data for template
// ------------------------------------------------------------
$data['failure'] = $_GET['failure'] ?? null;
$data['member'] = $ownerMember; // viewer identity (who is logged in)
$data['website'] = $website; // resolved website (owner’s website)
$data['follow_owner'] = $ownerMember; // optional: lets template show “Following X”

$resolvedSorttype = (int) ($viewer['sorttype'] ?? 1);
$data['sorttype'] = $cms->getSorttype()->get($resolvedSorttype);

// Navigation (menus) based on owner + owner website
$data['navigation'] = $cms->getMenu()->getAll2($websiteId, $ownerId);

// ------------------------------------------------------------
// Stories
//  ownerId stories (follow)
// ------------------------------------------------------------

$data['stories'] = $cms->getStory()->getAll2($websiteId, 0, null, $ownerId);
// Default sort menu id (Focus menu id=2)
$data['sort_menu_id'] = (int) ($data['sort_menu_id'] ?? 2);

echo $twig->render('author.html', $data);
