<?php
declare(strict_types=1);

use PhpBook\Validate\Validate;

require_once __DIR__ . '/../../config/recaptcha.php';
require_once APP_ROOT . '/src/security/redirects.php';
require_once APP_ROOT . '/src/security/guard.php';
guardPublic();

function sendPasswordResetEmail(
    array $emailConfig,
    string $toEmail,
    string $forename,
    string $resetUrl,
): void {
    $subject = 'Reset your Focus on Life password';

    $safeName = trim($forename) !== '' ? trim($forename) : 'there';

    $message = <<<TEXT
    Hi {$safeName},

    We received a request to reset your password.

    Please click the link below to choose a new password:

    {$resetUrl}

    This link will expire in 1 hour.

    If you did not request a password reset, you can ignore this email.

    Focus on Life
    TEXT;

    $mail = new \PhpBook\Email\Email($emailConfig);
    $mail->sendEmail($emailConfig['admin_email'], $toEmail, $subject, $message);
}

$email = '';
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));

    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
    if (empty($recaptchaToken)) {
        $errors['warning'] = 'Security check token missing. Please refresh the page and try again.';
    } else {
        $secretKey = $config['recaptcha_secret_key'] ?? '';
        if (!verify_recaptcha_v3($recaptchaToken, 'password_lost', $secretKey, 0.1)) {
            $errors['warning'] = 'Password reset failed security check. Please try again.';
        }
    }

    if (empty($errors['warning'])) {
        $errors['email'] = Validate::isEmail($email) ? '' : 'Please enter a valid email address';

        if (!empty($errors['email'])) {
            $errors['warning'] = 'Please correct the errors.';
        } else {
            try {
                $member = $cms->getMember()->getByEmailMaster($email);

                if ($member) {
                    $userId = (int) ($member['id'] ?? 0);
                    $forename = (string) ($member['forename'] ?? '');

                    if ($userId > 0) {
                        $cms->getMember()->expireUnusedPasswordResets($userId);

                        $rawToken = bin2hex(random_bytes(32));
                        $tokenHash = hash('sha256', $rawToken);
                        $expiryDate = new \DateTimeImmutable('+1 hour');
                        $expiresAt = $expiryDate->format('Y-m-d H:i:s');

                        $saved = $cms
                            ->getMember()
                            ->createPasswordReset($userId, $tokenHash, $expiresAt);

                        if ($saved) {
                            $scheme =
                                !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
                                    ? 'https'
                                    : 'http';
                            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                            $resetUrl =
                                $scheme .
                                '://' .
                                $host .
                                DOC_ROOT .
                                'password-reset?token=' .
                                urlencode($rawToken);

                            sendPasswordResetEmail($email_config, $email, $forename, $resetUrl);
                        }
                    }
                }

                $success =
                    'If that email address is registered, a password reset email has been sent.';
            } catch (Throwable $e) {
                error_log('[PASSWORD LOST] ' . $e->getMessage());
                $success =
                    'If that email address is registered, a password reset email has been sent.';
            }
        }
    }
}

$data = [];
$data['navigation'] = $cms->getMenu()->getAll2(1, 1);
$data['website'] = $cms->getWebsite()->getById(1);
$data['email'] = $email;
$data['errors'] = $errors;
$data['success'] = $success;
$data['use_recaptcha'] = true;
$data['recaptcha_site_key'] = $config['recaptcha_site_key'];

echo $twig->render('password-lost.html', $data);
exit();
