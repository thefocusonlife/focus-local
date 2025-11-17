<?php
declare(strict_types = 1);                                 // Use strict types
include APP_ROOT . '/src/pages/menu-path.php';

if (!$id) {                                                // If no valid id
    include APP_ROOT . '/src/pages/page-not-found.php';    // Page not found
}
if (($parts[2])=="get-focused") {
    $menu = $cms->getMenu()->get(50);
} else { 
    $menu = $cms->getMenu()->get($id);                         // Get menu data
}
if (!$menu) {                                              // If menu is empty
    include APP_ROOT . '/src/pages/page-not-found.php';    // Page not found
}
/*
if (mb_strtolower($parts[2]) != mb_strtolower($menu['seo_name'])) {  // If SEO name wrong
    redirect('menu/' . $id . '/' . $menu['seo_name'], [], 301);      // Redirect to correct URL
}
*/

    $website = $cms->getWebsite()->getById(intval($_SESSION['website']));

if(empty($_SESSION['id'])) {
    $member = 0; 
    $mem = intval($website['id']);
   
  } else { 
      $member = $cms->getMember()->get(intval($_SESSION['id']));
      $mem = intval($member['account_id']);
  }
  
  $cms->getSession()->create($member,$website['id']);
  

$data['navigation'] = $cms->getMenu()->getAll2($_SESSION['website'],$_SESSION['account_id']);     // All menus for navigation
$data['menu']       = $menu;
                                // Current menu
if ($_SESSION['id'] > 0) {
    if ($menu['id']==50) {                                              // if GET Focused menu
        $data['stories']    = $cms->getStory()->getAll(true, 50, 1,);  // Get stories
    } else {    
    //$data['stories']    = $cms->getStory()->getAll(true, $menu['id'], $_SESSION['account_id'],);  // Get stories
    $data['stories']    = $cms->getStory()->getAll(true, $menu['id'], null,);  // Get stories 
    }
} else {
    $data['stories']    = $cms->getStory()->getAll(true, $menu['id'], null,);  // Get stories 
}
$data['section']    = $menu['id'];                          // Menu id for nav
$data['website']    = $cms->getWebsite()->getById($menu['website']);


echo $twig->render('menu.html', $data);                     // Render Twig template