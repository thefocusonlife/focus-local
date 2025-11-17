<?php

declare(strict_types = 1); 
use PhpBook\Validate\Validate;
/*$note = [
    
    'note_type'   => 1,
    'to_id'       => 0,
    'to_name'     => '',
    'from_id'     => 0,
    'from_name'   => '',
    // 'family_id'   => 0,
    'request'     => 'Request to follow.',
    'allow'       => 0    
];                                                       // Story data
*/
$errors  = [
    'warning'     => '',
    'note_type'   => '',
    'to_id'       => '',
    'to_name'     => '',
    'from_id'     => '',
    'from_name'   => '',
    'family_id'   => '',
    'request'     => '',
    'allow'       => ''
    
];


$memberid = $_SESSION['id'];
if ($_SERVER['REQUEST_METHOD'] != 'POST') {  
$note = $cms->getNote()->getById($id);
$member=$cms->getMember()->get($memberid);
//var_dump_pre($member);
//var_dump_pre($note);
//echo "admin/note.php -38";

if ($member['account_id'] == $note['family_id']) {
    $member['account_id'] = $note['to_family_id'];
  //  var_dump_pre($member);
  //  echo "admin/note.php -43";   
} else {
    $member['account_id'] = $note['family_id'];
    //var_dump_pre($member);

   echo "Request to follow posted";
   //echo "admin/note =49";
 }
$cms->getMember()->update($member);
redirect('admin/members/$member.id/', ['success' => 'FOLLOW ID UPDATED.']);
}
//user's id from session                              // Use strict types
if (!$memberid) {                                              // If no valid id
    include APP_ROOT . '/src/pages/page-not-found.php';     // Page not found
}

$member = $cms->getMember()->get($memberid);          // Get member data

//if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $note['website']      = intval($_POST['website']);
    $note['to_id']        = intval($_POST['to_member']);        // Get from member id
    $note['to_name']      = $_POST['to_membername'];          // Get to name
    $note['from_id']      = intval($_POST['from_member']);              // Get to name
    $note['from_name']    = $_POST['from_membername'];        // Get from name
    $note['note_type']    = intval($_POST['noteid']);        // Get notetype
    $note['request']      = $_POST['request'];                // Get request
    $note['family_id']    = intval($_POST['family_id']);      // Get family id
    $note['to_family_id'] = intval($_POST['to_family_id']);
    $note['allow']        = intval($_POST['allow']);          // Get allow

if ($note['request'] == null) {
    $note['request'] = "Request to follow";
}

    $errors['request'] = Validate::isText($note['request'], 1, 1000) ? '' :
    'request should be between 0 and 1000 characters';

    
$invalid = implode($errors);                            // Join any error messages
if ($invalid) {                                         // If validation failed
    $errors['warning'] = 'Please correct form errors';  // Store a warning
} else {   
  $result = $cms->getNote()->create($note);               // Create a new request notfication
    if ($result === false) {                            // If result is false
        $errors['warning'] = 'Please correct form errors';      // Store a warning
    } else {
    
 }    
                                  // Otherwise;
  redirect('/member/intval(note.from_id)/', ['success' => 'Request updated']); // Redirect with message   
  

}


if (!$member) {                                          // If array is empty
    include APP_ROOT . '/src/pages/page-not-found.php';     // Page not found
}

$members = $cms->getMember()->getAll();
$notes   = $cms->getNote()->get($id);

$data['navigation']  = $cms->getMenu()->getAll();             // Get menus
$data['member']      = $member; 
$data['members']     = $members;
$data['notes']       = $notes;                                 // Member data
$data['success']     = $_GET['Request Sent'] ?? '';            // Success message if present
$data['website']     = $cms->getWebsite()->getById($member['website']);
//var_dump_pre($data);
//echo "admin/note.php =111";

if ($_SERVER['REQUEST_METHOD'] != 'POST') { 
 
echo $twig->render('note.html', $data);
}                         // Render Twig template