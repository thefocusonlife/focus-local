<?php
declare(strict_types = 1);                                  // Use strict types
use PhpBook\Validate\Validate;                              // Import Validate class


$error = false;                                             // Error message
$sent  = false;                                             // Has email been sent
if ($_SERVER['REQUEST_METHOD'] == 'POST') {                 // If form submitted
    $email = $_POST['email'];                               // Get email
   
        // -----------------------------
        // reCAPTCHA v3 verification
        // -----------------------------
                    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
                    error_log('PASSWORD-LOST recaptcha token: ' . substr($recaptchaToken, 0, 40));

        if (empty($recaptchaToken)) {
            // Front-end didn't provide a token at all
            $errors['warning'] = 'Security check token missing. Please refresh the page and try again.';
        } else {
            $secretKey = $config['recaptcha_secret_key'] ?? '';

            // Use a slightly lower threshold for login to reduce false negatives
            if (!verify_recaptcha_v3($recaptchaToken, 'password-lost', $secretKey, 0.1)) {
                // reCAPTCHA failed – do NOT attempt login
                $errors['message'] = 'Password-lost failed security check. Please try again.';
            
           } // end verify_recaptcha_v3()
        } // end empty token check 
   
    $error = Validate::isEmail($email) ? '' : 'Please enter your email'; // Validate
    if ($error === '') {                                    // If email valid
        $id = $cms->getMember()->getIdByEmail($email);      // Get member id
        if ($id) {                                          // If id found
            $token   = $cms->getToken()->create($id, 'password_reset');     // Token
            $linkstring  = DOMAIN . DOC_ROOT . 'password-reset/?token=' . $token; // Link
            $replace ="\\";
            $with ="/";
            $link = str_replace( $replace , $with, $linkstring);
           
            $subject = 'Reset Password Link';               // Email subject
            $body    = 'To reset password click: <a href="' . $link . '">' . $link . '</a>'; // Email body
            $mail    = new \PhpBook\Email\Email($email_config);   // Email object
         
           
           $mail->sendEmail($email_config['admin_email'], $email, $subject, $body);         // Send
        }                                                                                    // Send to login       
    }
    $sent = true;
}
$website = ($cms->getWebsite()->getById($_SESSION['website']));
$w = intval($website['id']);
$data['website'] = $website;
$data['navigation'] = $cms->getMenu()->getAll2($w,1);        // Menus for navigation
$data['error']      = $error ?? null;                       // Validation errors
$data['sent']       = $sent;                                 // Did it send
echo $twig->render('password-lost.html', $data);            // Render Twig template