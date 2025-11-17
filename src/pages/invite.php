<?php
declare(strict_types = 1);                                  // Use strict types
use PhpBook\Validate\Validate;  
                      // Import validate class
include APP_ROOT . '/src/pages/menu-path.php';  
$to      = '';  
$from    = '';                                              // Initialize: from
$message = '';                                              // Message
$errors  = [];                                              // Array for errors
$success = '';
$string  = '';                                              // Success message

if ($_SERVER['REQUEST_METHOD'] == 'POST') {                 // If form submitted

    $to                = $_POST['to_email'];
    $from              = $_POST['email'];      // Get member id
    $message              = $_POST['message'];                 // Message
    //var_dump_pre($to);
    //var_dump_pre($from);
    //var_dump_pre($message);
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {                 // If form submitted
        $email = $_POST['email'];                               // Get email
        $error = Validate::isEmail($email) ? '' : 'Please enter your email'; // Validate
        if ($error === '') {                                    // If email valid
            $id = $cms->getMember()->getIdByEmail($from);      // Get member id
             // Email body
                $mail    = new \PhpBook\Email\Email($email_config);   // Email object
             
               
               $mail->sendEmail($email_config['admin_email'], $email, $subject, $message);         // Send
            }                                                                                    // Send to login       
        }
        $sent = true;
    
}
//var_dump_pre($_SESSION);
//echo "invite.php -37";

if(empty($_SESSION['id'])) {
    $member = 0; 
    $mem = intval($website['id']);
   
  } else { 
      $member = $cms->getMember()->get(intval($_SESSION['id']));
      $mem = intval($member['account_id']);
  }
//var_dump_pre($member);
//echo "invite.php -48";
/*
$cms->getSession()->create($member,$website['id']);
$data['navigation'] = $cms->getMenu()->getAll2($member['id'],$mem);          // All menus for nav
$string = $member['forename'] ." ". $member['surname'] . " is viting you to take a look at an interesting website: http://www.thefocusonlife.com";
$string = preg_replace(
    "~[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]~",
    "<a href=\"\\0\">\\0</a>", 
    $string);
    
    //$cms->getSession()->create(intval($member['id']),intval($website['id']));


$data['to']      = $to;
$data['from']    = $member['email'];                        // From email
$data['message'] = $string;                                // Message
$data['success'] = $success;                                // Success message
$data['errors']  = $errors;                                 // Error messages
*/

$data['website']    = $website;
echo $twig->render('invite.html', $data);                  // Render Twig template