<?php
declare(strict_types = 1);                               // Use strict types
use PhpBook\Validate\Validate;                           // Import Validate class

//include APP_ROOT . '/src/pages/menu-path.php';           // get path for website and menus

if ($cms->getSession()->role !== 'public' AND 
$cms->getSession()->role !== 'guest')
  {             // If user is already logged in
    redirect('member/' . $cms->getSession()->id);        // Redirect to their page
    exit;                                                     // Stop code running
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {              // If form submitted
    $website = $cms->getWebsite()->getById(intval($id));
}
$email   = '';                                           // Initialize email variable
$errors  = [];                                           // Initialize errors
$success = $_GET['success'] ?? null;                     // Get success message
//var_dump_pre($_SERVER['REQUEST_METHOD']);
//echo "login.php -18";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {              // If form submitted
    $email    = $_POST['email'];                         // Get email address
    $password = $_POST['password'];                      // Get password
    $website_id  = intval($_POST['website']);
    $errors['email']    = Validate::isEmail($email)
        ? '' : 'Please enter a valid email address';           // Validate email
    $errors['password'] = Validate::isPassword($password)
        ? '' : 'Passwords must be at least 8 characters and have:<br> 
                A lowercase letter<br>An uppercase letter<br>A number 
                <br>And a special character';                  // Validate password
    $invalid = implode($errors);
    
    if ($invalid) {                                            // If data is not valid
        $errors['message'] = 'Please try again.';              // Store error message
    } else {    
        
                                                // If data was valid
        $member = $cms->getMember()->login2($email, $password); // Get member details
        if (empty($member)) {
            $w = $cms->getWebsite()->getById($website_id);
            $errors['message'] = "This email not valid for " . $w['name'];
        }
        
        elseif ($member and $member['role'] == 'suspended') {      // If member is suspended
            $errors['message'] = 'Account suspended';          // Store message
        }
        /*
        elseif ($member['website'] != 1 AND $website_id == 1) {
            $errors['message'] = "Must use a registered theFocusOnLife email.";

        }
        */
        elseif ($member and $member['role'] == 'pending') {      // If member is suspended
            $errors['message'] = 'Membership pending.  Use Contact Us to inquire about your registration.';          // Store message
        }
        elseif ($member) { 
            
       
            // Otherwise for members
            $cms->getSession()->create($member,$website['id']);      // Create session
            redirect('member/' . $member['id']);               // Redirect to their page
        } else {                                               // Otherwise
        $errors['message'] = 'Please try again.';          // Store error message
        }
    }
}
if ($_SESSION['id'] == 2) {
    $member = 0; 
        //$mem = intval($website['id']);  remove after testing for awhile
    $mem = ($id);
    
  } else { 
      $member = $cms->getMember()->get(intval($_SESSION['id']));
      //$men = intval($member['account_id']);  remove after testing for awhile
      $mem = ($member['account_id']);
  }
  if($member){
    $cms->getSession()->create($member,$website['id']);
  }
$data['navigation'] = $cms->getMenu()->getAll2($_SESSION['website'],$mem);     // Get navigation menus
$data['success']    = $success;                          // Success message
$data['email']      = $email;                            // Email address if validation failed
$data['errors']     = $errors;                           // Errors array
if (!isset($website)) {
   $website = $cms->getWebsite()->getById(1);
}
$data['website']    = ($website);
echo $twig->render('login.html', $data);                 // Render Twig template