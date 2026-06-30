<?php
declare(strict_types=1); // Use strict types
use PhpBook\Validate\Validate; // Import validate class

/** @var array $config */
/** @var array $email_config */
/** @var \Twig\Environment $twig */
/** @var mixed $cms */

require_once APP_ROOT . '/src/security/csrf.php';

$csrfFormKey = 'contact';

$from = ''; // Initialize: from
$subject = '';
$message = ''; // Message
$errors = []; // Array for errors
$success = ''; // Success message

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $from = trim((string) ($_POST['email'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    $submittedCsrf = $_POST['csrf_token'] ?? '';
    if (!csrf_validate($csrfFormKey, is_string($submittedCsrf) ? $submittedCsrf : null)) {
        error_log('[CONTACT] CSRF failed ip=' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        http_response_code(403);
        exit();
    }

    $honeypot = trim((string) ($_POST['company'] ?? ''));
    if ($honeypot !== '') {
        error_log('[CONTACT] honeypot blocked ip=' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        http_response_code(403);
        exit();
    }

    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
    if (empty($recaptchaToken)) {
        $errors['warning'] = 'Security check token missing. Please refresh the page and try again.';
    } else {
        $secretKey = $config['recaptcha_secret_key'] ?? '';
        if (!verify_recaptcha_v3($recaptchaToken, 'contact', $secretKey, 0.7)) {
            error_log('[CONTACT] reCAPTCHA failed ip=' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $errors['warning'] = 'Message failed security check. Please try again.';
        }
    }

    if (empty($errors)) {
        $errors['email'] = Validate::IsEmail($from) ? '' : 'Email not valid';
        $errors['message'] = Validate::IsText($message, 1, 1000)
            ? ''
            : 'Please enter a message up to 1000 characters';

        if (implode($errors)) {
            $errors['warning'] = 'Please correct the errors';
        } else {
            $subject = 'Contact form message from ' . $from;
            $mail = new \PhpBook\Email\Email($email_config);
            $mail->sendEmail($from, $email_config['admin_email'], $subject, $message);
            $success = 'Your message has been sent';
        }
    }

    csrf_rotate($csrfFormKey);
}
$data['navigation'] = $cms->getMenu()->getAll2(1, 1); // All categories for nav

// The following values are only created if the user has submitted the form
//$data['website'] = 1;
$data['website'] = $cms->getWebsite()->getById(1);
$data['from'] = $from; // From email
$data['message'] = $message; // Message
$data['use_recaptcha'] = true;
$data['recaptcha_site_key'] = $config['recaptcha_site_key'];
$data['errors'] = $errors; // Error messages
$data['success'] = $success; // Success message
$data['csrf_token'] = csrf_token($csrfFormKey);
echo $twig->render('contact.html', $data); // Render Twig template
