<?php
//is_admin($session->role);                                          // Check if admin
//if (!$id) {                                                        // If no id
//    redirect('page-not-found/');                                   // Page not found
//}

$toMember   = $cms->getMember()->get($_SESSION['id']);              // Get all members
if ($_SERVER['REQUEST_METHOD'] == 'POST') { 
$family = intval($_POST['family_id']);

$fromMember = $cms->getMember()->get($family);
if($fromMember['id'] == $family) {
$toMember['account_id'] = $fromMember['id'];
} else {
  $toMember['account_id'] = $family;
}

 // Get member data
if (!$toMember) {                                                    // If no member data
    redirect('page-not-found/');                                     // Page not found
}
        $cms->getMember()->update($toMember);                        // Update member account id
        redirect('admin/members/', ['success' => 'Family updated']); // Redirect with message
}
 
$data['website'] = $cms->getWebsite()->getById(intval($_SESSION['website']));
$data['member'] = $cms->getMember()->get($id);                        // retrieve Session ID member info
$id = (array($_SESSION['id']));
$data['families']  = $cms->getFamily()->getByAllowed($id);            // retieve all allowed notes for this member
$arr = array("id"=>intval($toMember['id']), "account_id"=>intval($toMember['id']),                // create a new array for this member
"to_name"=>($toMember['forename'] .' '. $toMember['surname']), "from_id"=>intval($toMember['id']),
"family_id"=>(intval($toMember['id'])),"to_family_id"=>(intval($toMember['id'])), "allow"=>1);
$stack = $data['families'];
array_push($stack,$arr);                                           // push the new array into line 33 families                                                  
$data['families']=$stack;                                          // load pushed $stack array into $data for html file
echo $twig->render('admin/edit-family.html', $data); 