<?php
declare(strict_types=1); // Use strict types
use PhpBook\Validate\Validate; // Import validate class

require_once APP_ROOT . '/config/recaptcha.php'; // ⭐ reCAPTCHA helper

$from = ''; // Initialize: from
$subject = '';
$message = ''; // Message
$errors = []; // Array for errors
$success = ''; // Success message

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $from = $_POST['email'] ?? '';
    $message = $_POST['message'] ?? '';

    // -----------------------------
    // reCAPTCHA v3 verification
    // -----------------------------
    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
    if (empty($recaptchaToken)) {
        $errors['warning'] = 'Security check token missing. Please refresh the page and try again.';
    } else {
        $secretKey = $config['recaptcha_secret_key'] ?? '';
        if (!verify_recaptcha_v3($recaptchaToken, 'contact', $secretKey, 0.1)) {
            $errors['recaptcha12'] = 'Message failed security check. Please try again.';
        }
    }

    // Only validate/send if security check passed
    if (empty($errors['warning']) && empty($errors['message'])) {
        $errors['email'] = Validate::IsEmail($from) ? '' : 'Email not valid';
        $errors['message'] = Validate::IsText($message, 1, 1000)
            ? ''
            : 'Please enter a message up to 1000 characters';

        $invalid = implode($errors);

        if ($invalid) {
            $errors['warning'] = 'Please correct the errors';
        } else {
            $subject = 'Contact form message from ' . $from;
            $mail = new \PhpBook\Email\Email($email_config);
            $mail->sendEmail($from, $email_config['admin_email'], $subject, $message);
            $success = 'Your message has been sent';
        }
    }
}

$data['navigation'] = $cms->getMenu()->getAll2(1, 1); // All categories for nav

// The following values are only created if the user has submitted the form
//$data['website'] = 1;
$data['website'] = $cms->getWebsite()->getById(1);
$data['from'] = $from; // From email
$data['message'] = $message; // Message
$data['errors'] = $errors; // Error messages
$data['success'] = $success; // Success message

echo $twig->render('contact.html', $data); // Render Twig template
