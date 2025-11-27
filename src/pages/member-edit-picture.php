<?php
declare(strict_types = 1); 
include APP_ROOT . '/src/pages/menu-path.php';

$errors  = '';                                           // Error messages

$id = $cms->getSession()->id;                            // Get user's id from session

/*if ($id === 0) {                                         // If not logged in
    redirect('login/');                                  // Page not found
}
*/
$member = $cms->getMember()->get($id);                   // Get member data

$delete = $_POST['delete'] ?? '';                        // Check if deleting image
if ($delete === 'delete') {                              // If so
    $cms->getMember()->pictureDelete($member, UPLOADS);  // Update story
    redirect('member/' . $member['id'] . '/', ['success'=>'Picture deleted',]); // Reload page
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {              // Form submitted
    
    $temp = $_FILES['image']['tmp_name'] ?? '';          // Temporary image
    if (is_uploaded_file($temp) and $_FILES['image']['error'] == 0) {  // If file OK
        $errors  = in_array(mime_content_type($temp), MEDIA_TYPES) 
            ? '' : 'Wrong file type. ';                  // Validate type
        $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION)); // File extension in lowercase
        $errors .= in_array($extension, FILE_EXTENSIONS)
            ? '' : 'Wrong file extension. ';             // File extension
        $errors .= ($_FILES['image']['size'] <= MAX_SIZE)
            ? '' : 'File too big. Resize with Paint or other image utility. Max Size = ' . (MAX_SIZE/1000000). 'mb.';                     // Validate size

        if (!$errors) {                                                          // If invalid data
            $filename = create_filename($_FILES['image']['name'], UPLOADS);               // Create filename
            $cms->getMember()->pictureCreate($id, $filename, $temp, UPLOADS . $filename);   // Save + update image
            redirect('admin/members/', ['success' => 'Family updated']); // Redirect with message
        } else {                                                                 // Otherwise
            $errors .= 'Please try again.';                        // Message
        }

    } else {                                                                      // Otherwise
        $errors = 'Please upload a profile picture.';                              // Store message
    }
}
if (! $id) {
    // No member id in session – fall back to website id from session
    $website = $cms->getWebsite()->getById((int) ($_SESSION['website'] ?? 0));
} else {     
    // Logged-in member – use member's website id
    $website = $cms->getWebsite()->getById((int) $member['website']);
}

if (empty($_SESSION['id'])) {
    //$member = 0; 
    $mem = intval($website['id']);
   
  } else { 
      //$member = $cms->getMember()->get(intval($_SESSION['id']));
      $mem = intval($member['account_id']);
  }
     $cms->getSession()->create($member,$website['id']);

$data['navigation'] = $cms->getMenu()->getAll2($member['website'],$member['account_id']);     // All menus for navigation
$data['member']     = $member;                           // Member
$data['errors']     = $errors;                           // Errors
$data['website']    = $website;

echo $twig->render('member-edit-picture.html', $data);   // Render Twig template