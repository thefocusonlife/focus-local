<?php
declare(strict_types=1);
$id = $cms->getSession()->id;
//user's id from session                              // Use strict types

if (!$id) {
    // If no valid id
    include APP_ROOT . '/src/pages/page-not-found.php'; // Page not found
}

$member = $cms->getMember()->get($id); // Get member data
if (!$member) {
    // If array is empty
    include APP_ROOT . '/src/pages/page-not-found.php'; // Page not found
}
$members = $cms->getMember()->getAll();
//$follows = $cms->getFollow()->getAll();
$notes = $cms->getNote()->getAll($id);

$data['navigation'] = $cms->getMenu()->getAll(); // Get menus
$data['member'] = $member;
$data['members'] = $members;
$data['notes'] = $notes;
$data['success'] = $_GET['success'] ?? ''; // Success message if present

echo $twig->render('follow-request.html', $data); // Render Twig template
