<?php
declare(strict_types=1);

use PhpBook\Validate\Validate;

$from = '';
$subject = '';
$message = '';
$errors = [];
$success = '';

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
        if (!verify_recaptcha_v3($recaptchaToken, 'contact_support', $secretKey, 0.1)) {
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
            $subject = 'Contact Support message from ' . $from;
            $mail = new \PhpBook\Email\Email($email_config);

            // Send to support address instead of admin_email
            $mail->sendEmail($from, 'support@thefocusonlife.org', $subject, $message);

            $success = 'Your support message has been sent';
        }
    }
}

$data['navigation'] = $cms->getMenu()->getAll2(1, 1);
$data['website'] = $cms->getWebsite()->getById(1);
$data['from'] = $from;
$data['message'] = $message;
$data['use_recaptcha'] = true;
$data['recaptcha_site_key'] = $config['recaptcha_site_key'];
$data['errors'] = $errors;
$data['success'] = $success;

echo $twig->render('contact-support.html', $data);
