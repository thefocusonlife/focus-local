<?php
//echo "admin/stories -2";
include APP_ROOT . '/src/pages/menu-path.php';  

is_admin($session->role);                                 // Check if admin
$member  = $cms->getMember()->get($_SESSION['id']);
if (! $_SESSION['id']) {
    $website = $cms->getWebsite()->getByID(1);
} else {
    $website = $cms->getWebsite()->getByID($member['website']);
}
$data['success']  = $_GET['success'] ?? null;            // Check for success message
$data['failure']  = $_GET['failure'] ?? null;            // Check for failure message

if ($_SESSION['id'] == 1) {
   $data['stories']    = $cms->getStory()->getAll2($website['id'], null, null,null,); // Get all stories for Uber
   //$cms()->getSession->create(0,website['id']);  
} else {
   

    $data['stories']  = $cms->getStory()->getAll3(intval($website['id']),null,null,intval($_SESSION['id'],));
    
}    


$data['website']  = $website;

echo $twig->render('admin/stories.html', $data);        // Render Twig template