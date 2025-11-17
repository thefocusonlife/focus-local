<?php
is_admin($session->role);                                // Check if admin

$member  = $cms->getMember()->get($_SESSION['id']);
if (! $_SESSION['id']) {
    $website = $cms->getWebsite()->getByID(1);
} else {
    $website = $cms->getWebsite()->getByID($_SESSION['website']);
    $mem = $member['account_id'];
}
$data['success']    = $_GET['success'] ?? null;                         // Check for success message
$data['failure']    = $_GET['failure'] ?? null;
if ($_SESSION['id'] == 1) {
    $data['menus']      = $cms->getMenu()->getAll();
} else {                            // Check for failure message
$data['menus']      = $cms->getMenu()->getAll2($website['id'],$mem);    // Menu data for template
}

$data['website']    = $website;                                         // Pass website logo on to html
echo $twig->render('admin/menus.html', $data);                          // Render Twig template