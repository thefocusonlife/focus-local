<?php
declare(strict_types = 1);                               // Use strict types
use PhpBook\Validate\Validate; 
include APP_ROOT . '/src/pages/menu-path.php';  // menu-path includeinclude 
$families = [];
$menu = [];

is_admin($session->role);                                          // Check if admin
if (!$id) {                                                        // If no id
    redirect('page-not-found/');                                   // Page not found
}

$families    = $cms->getMember()->getAll3($_SESSION['account_id']);              // Get all members

$data['members'] = $cms->getMember()->getAll3($_SESSION['account_id']);          // Member data for template

$member = $cms->getMember()->get($_SESSION['id']);                             // Get member data

if (!$member) {                                                    // If no member data
    redirect('page-not-found/');                                   // Page not found
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {                         // If form submitted
  

   $families = $_POST['account_id'] ?? '1';                         // Get new role
   $account_id = intVal($_POST['member_id']) ?? 1;                    // Get new role
   $menu['account_id']=$account_id;
   $cms->getMenu()->update($menu,'','');                       // Update family id in database  <<< need to unset joined and member
   $cms->getMenu()->update($menu); 
  
    redirect('admin/menus/', ['success' => 'Family updated']); // Redirect with message
  }
  if (! $id) {
    $website = $cms->getWebsite()->getById(intval($_SESSION['website']));
} else {     
    $website = $cms->getWebsite()->getById(intval($id));
    
}

if(empty($_SESSION['id'])) {
  $member = 0; 
  $mem = intval($website['id']);
 
} else { 
    $member = $cms->getMember()->get(intval($_SESSION['id']));
    $mem = intval($member['account_id']);
} 

$data['member']    = $member;                                        // Member data for template
$data['families']  = $families; 
$data['menu']      = $menu;                     // Author data data for template
$data['website']   = $website;
echo $twig->render('admin/menu-family.html', $data);                 // Render Twig template