<?php
declare(strict_types = 1); 
    
//user's id from session                              // Use strict types

if (!$id) {                                              // If no valid id
    include APP_ROOT . '/src/pages/page-not-found.php';     // Page not found
}


$member = $cms->getMember()->get($id);                   // Get member data
if (!$member) {                                          // If array is empty
    include APP_ROOT . '/src/pages/page-not-found.php';     // Page not found
}
$members = $cms->getMember()->getAll();
$notes =  $cms->getNote()->getAll($member,$member);
$notetype = $cms->getNotetype()->get(1);   
$website =$cms->getWebsite()->getById($member['website']);
$mem = intval($members[id]) ?? 1;
$w = intval($website['id']);
$data['navigation']  = $cms->getMenu()->getAll2($w,$mem);             // Get menus
$data['member']      = $member; 
$data['members']     = $members;
$data['notetype']    = $notetype;
$data['notes']       = $notes;                                 // Member data
$data['success']     = $_GET['Request Sent'] ?? '';            // Success message if present
$data['website']     = $cms->getWebsite()->getById($member['website']);
echo $twig->render('note.html', $data);                         // Render Twig template