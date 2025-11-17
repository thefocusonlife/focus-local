<?php
declare(strict_types = 1);                               // Use strict types
use PhpBook\Validate\Validate;                           // Import Validate class

include APP_ROOT . '/src/pages/menu-path.php';           // get path for website and menus

if ($cms->getSession()->role !== 'public') {             // If user is already logged in
    redirect('member/' . $cms->getSession()->id);        // Redirect to their page
    exit;                                                     // Stop code running
}
/*$path  = mb_strtolower($_SERVER['REQUEST_URI']);             // Get path in lowercase
$path  = substr($path, strlen(DOC_ROOT)); 
if($path == ""){
    $path = "index/". 1;
}
                   // Remove up to DOC_ROOT
$parts = explode('/', $path);                                // Split into array at /
if ($parts[0] != 'admin') {                                  // If an admin page
    $page = $parts[0] ?: 'index';                            // Page name (or use index)
    $id   = ( intval($parts[1])) ?? 1;                               // Get ID (or use null)
} else {                                                     // If not an admin page
    $page = 'admin/' . ($parts[1] ?? '');                    // Page name
    $id   = intval($parts[2]) ?? null;                               // Get ID
}
if (! $id) {
    $cms->getSession()->create(0,1);               // Create session
   // $id = 1;
}

if ($cms->getSession()->role !== 'public') {             // If user is already logged in
    redirect('member/' . $cms->getSession()->id);        // Redirect to their page
    exit;                                                     // Stop code running
}
if (! $id) {
    $website = $cms->getWebsite()->getById(intval($_SESSION['website']));
} else {     
    $website = $cms->getWebsite()->getById(intval($id));
    
}

if ($_SESSION['id'] == 0) {
  $member = 0; 
  $mem = intval($website['id']);
 
} else { 
    $member = $cms->getMember()->get(intval($_SESSION['id']));
    $men = intval($member['account_id']);
}
*/
$email   = '';                                           // Initialize email variable
$errors  = [];                                           // Initialize errors
$success = $_GET['success'] ?? null;                     // Get success message
//var_dump_pre($website);
//var_dump_pre($_SERVER['REQUEST_METHOD']);
//echo "login -54";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {              // If form submitted
    $email    = $_POST['email'];                         // Get email address
    $password = $_POST['password'];  
    $website = intval($_POST['website']);                    // Get password
  //  var_dump_pre($website);
    //echo "login -59";
    
    $errors['email']    = Validate::isEmail($email)
        ? '' : 'Please enter a valid email address';           // Validate email
    $errors['password'] = Validate::isPassword($password)
        ? '' : 'Passwords must be at least 8 characters and have:<br> 
                A lowercase letter<br>An uppercase letter<br>A number 
                <br>And a special character';                  // Validate password
    $invalid = implode($errors);
    //var_dump_pre($invalid);
    //echo "login - 71";
    
    if ($invalid) {                                            // If data is not valid
        $errors['message'] = 'Please try again.';              // Store error message
    } else {    
                                                 // If data was valid
    //var_dump_pre($email);
    //var_dump_pre($password);
    //var_dump_pre($website);
    //echo "login -81";
                                              
        $member = $cms->getMember()->login($email, $website, $password); // Get member details
        if ($member and $member['role'] == 'suspended') {      // If member is suspended
            $errors['message'] = 'Account suspended';          // Store message
        } 
        elseif ($member and $member['role'] == 'pending') {      // If member is suspended
            $errors['message'] = 'Membership pending.  Use Contact Us to inquire about your registration.';          // Store message
        }
        elseif ($member) {  
        //var_dump_pre($member);
        //echo "login -87";
                                       // Otherwise for members
            $cms->getSession()->create($member,$website);      // Create session
            redirect('member/' . $member['id']);               // Redirect to their page

        } else {                                               // Otherwise
            $errors['message'] = 'Please try again.';          // Store error message
        }
        
    }
    
}
if ($_SESSION['id'] == 0) {
    $member = 0; 
    $mem = intval($website['id']);
   
  } else { 
      $member = $cms->getMember()->get(intval($_SESSION['id']));
      $men = intval($member['account_id']);
  }
$cms->getSession()->create($member,$website['id']);
$data['navigation'] = $cms->getMenu()->getAll2($_SESSION['website'],$mem);     // Get navigation menus
$data['success']    = $success;                          // Success message
$data['email']      = $email;                            // Email address if validation failed
$data['errors']     = $errors;                           // Errors array
$data['website']    = $website;
//var_dump_pre($data);
//echo "login -108";
echo $twig->render('login.html', $data);                 // Render Twig template