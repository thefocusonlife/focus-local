<?php
include APP_ROOT . '/src/pages/menu-path.php';  // menu-path includeinclude 
//is_admin($session->role);                                          // Check if admin
//if (!$id) {                                                        // If no id
//    redirect('page-not-found/');                                   // Page not found
//}
$member = $cms->getMember()->get(intval($parts[1]));
$toMember = $cms->getMember()->get(intval($parts[2]));
if ($toMember['id'] != $member['id']) {
  $member['account_id']= $toMember['account_id'];
} else {
  $member['account_id'] = $member['id'];
}
 // Get member data
if (!$member) {                                                    // If no member data
    redirect('page-not-found/');                                     // Page not found
}

$cms->getMember()->update($member);                        // Update member account id
        redirect('member/' . $member['id'], ['success' => 'Family newly updated']); // Redirect with message
