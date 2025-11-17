<?php
if (!empty($parts[2])) {
$id = intval($parts[2]);

}


/*
$myArray = false;
if (!is_array($myArray)) {
    $myArray = array();
}
$myArray[0] = 'value';
*/

if (!empty($parts[2])) {
    $note = $cms->getNotes()->getById($id);
    if (!is_array($note['allow'])) {
       $note['allow'] = array();
    }
    
    $note['allow'] = 1;
    $note['reply_date'] = date("Y-m-d");
    $cms->getNotes()->update($note);                     // Update note in database  <<< need to unset joined and member
    redirect('member/' . $note['from_id'] . '/', ['success' => 'FOLLOW ALLOWED']); // Redirect with message
}

//is_admin($session->role);
$member  = intval($_SESSION['id']);
$member2 = $cms->getMember()->get($member);
$data['success']  = $_GET['success'] ?? null;            // Check for success message
$data['failure']  = $_GET['failure'] ?? null;            // Check for failure message
$data['member']   = $member2;
$data['members']  = $cms->getMember()->getAll();
$data['notes']    = $cms->getNotes()->getAll($member2['id']);     // Get notes by member
$data['website']  = $cms->getWebsite()->getById($member2['website']);

echo $twig->render('admin/notes.html', $data);
