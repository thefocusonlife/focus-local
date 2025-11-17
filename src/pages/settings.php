<?php
declare(strict_types = 1);                               // Use strict types
use PhpBook\Validate\Validate;                           // Use Validate class
$id = $_SESSION['id'];                                   // Get user's id from session
if ($id === 0) {                                         // If not logged in
    redirect('login/');                                  // Page not found
}

$errors  = [];                                            // Check if admin


//$data['members'] = $cms->getMember()->getAll
//();          // Member data for template

$member = $cms->getMember()->get($id);                             // Get member data
if (!$member) {                                                    // If no member data
    redirect('page-not-found/');                                   // Page not found
}
$pagelimit = $cms->getPagelimit()->getAll();
$sorttype  = $cms->getSorttype()->getAll();


if ($_SERVER['REQUEST_METHOD'] == 'POST') {                        // If form submitted
   
    $member['pagelimit'] = intval($_POST['pagelimit']) ?? 10; 
    $member['sorttype']  = intval($_POST['sorttype'])  ?? 8;                           
    
    
    $cms->getMember()->update($member);                       // Update pagelimit and sorttype  <<< need to unset joined and member
   // Otherwise for members
   $cms->getSession()->create($member,$website['id']);      // Create session
   redirect('member/' . $member['id']);               // Redirect to their page
       
    }



$data['member']  = $member;                                        // Member data for template
$data['pagelimit'] = $pagelimit;
$data['sorttype']  = $sorttype;
$data['website'] = $cms->getWebsite()->getById($member['website']);

echo $twig->render('settings.html', $data);                 // Render Twig template


