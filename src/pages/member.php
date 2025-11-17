<?php
declare(strict_types = 1); 
include APP_ROOT . '/src/pages/menu-path.php'; 

if (!$id) {                                              // If no valid id
    include APP_ROOT . '/src/pages/page-not-found.php';     // Page not found
}

$member  = $cms->getMember()->get(intval($parts[1]));
$mem = intval($member['account_id']);
if (! $_SESSION['id']) {
    $website = $cms->getWebsite()->getById($member['website']); 
} else {
    $website = $cms->getWebsite()->getByID($member['website']);
}
if(empty($_SESSION['id'])) {
  } else { 
      $member = $cms->getMember()->get(intval($parts[1]));
      $mem = intval($member['account_id']);
}
$data['success']  = $_GET['success'] ?? null;            // Check for success message
$data['failure']  = $_GET['failure'] ?? null;            // Check for failure message
      // Get story summaries
$data['navigation']  = $cms->getMenu()->getAll2($website['id'],$mem);                 // Get menus
$data['member']      = $member;                                   // Member data
$data['website']     = $cms->getWebsite()->getById($member['website']); 
$data['sorttype']    = $cms->getSorttype()->get($member['sorttype']);            // get member notes
if (isset($id) and $id == 2 ) {

    $data['stories']    = $cms->getStory()->getAll(true, null, null);   // Get all stories for Uber
    //$cms->getSession()->create(0,1);  
}elseif (!empty($parts[2]) and $parts[2]==1) {
//    $cms->getSession()->get;
    $data['stories']  = $cms->getStory()->getAll3($website['id'],true,null,null,); } //get all stories for member's website

else {  

    $id = intval($parts[1]);
    $data['stories']  = $cms->getStory()->getAll2($website['id'],0,null,$id,); }
    //$data['member']  = null;

echo $twig->render('member.html', $data);                        // Render Twig template