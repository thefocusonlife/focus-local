<?php
declare(strict_types = 1);                               // Use strict types
use PhpBook\Validate\Validate;                           // Use Validate class

                            // Get user's id from session
if ($id === 0) {                                         // If not logged in
    redirect('login/');                                  // Page not found
}

$errors  = []; 
              // If form was posted
    // Error messages
       // If form was posted
    
if ($_SERVER['REQUEST_METHOD'] != 'POST') {              // If form not posted
    $member  = $cms->getMember()->get($id);               //  Get member details
    $agegroups = $cms->getMember()->getAgegroups();
    $plans     = $cms->getMember()->getPlans();  
  }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {              // If form was posted
    $member  = $cms->getMember()->get($id); 
    $member['forename']     = $_POST['forename'];            // Get forename
    $member['surname']      = $_POST['surname'];             // Get surname
    $member['email']        = $_POST['email'];               // Get email
    $member['email_master'] = $_POST['email'];
    $member['publik']       = intval($_POST['publik']);
    $member['termsok']      = intval($_POST['termsok']);
    // Validate form data
    $errors['forename'] = Validate::isText($member['forename'], 1, 254) ? '' :
        'Forename should be between 1 and 254 characters';
    $errors['surname']  = Validate::isText($member['surname'], 1, 254) ? '' :
        'Surname should be between 1 and 254 characters';
    $errors['email']    = Validate::isEmail($member['email']) ? '' :
        'Please enter a valid email address';
    
    $invalid = implode($errors);                            // Join any error messages
    if ($invalid) {                                         // If validation failed
        $errors['message'] = 'Please correct form errors';  // Store a warning
    } else {                                                // Otherwise
        $result = $cms->getMember()->update($member);       // Create new member & store id
                               // Commit transaction                        // Run SQL
        if ($result === false) {                            // If result is false
            $errors['message'] = 'Please fix form errors';    // Store a warning
        } else {                                            // Otherwise
           
          /*  $cms->getSession()->update($member);            // Update session
           */
          redirect('admin/members/', ['success' => 'Profile updated']); // Redirect with message
        }
    }
}
$data['navigation'] = $cms->getMenu()->getAll2($member['website'],$member['account_id']);     // All menus for navigation
$data['member']     = $member;                           // Member data
$data['agegroups']  = $agegroups;
$data['plans']      = $plans;
$data['errors']     = $errors;                           // Error messages
$data['website']    = $cms->getWebsite()->get($member['website']);

echo $twig->render('member-edit-profile.html', $data);   // Render Twig template