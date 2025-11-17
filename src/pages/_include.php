<?php
declare(strict_types = 1);                      // Use strict types
include APP_ROOT . '/src/pages/menu-path.php';  // menu-path include


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

$cms->getSession()->create($member,$website['id']);

