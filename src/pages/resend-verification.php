<?php
declare(strict_types=1);

use PhpBook\Validate\Validate;

require_once __DIR__ . '/../../config/recaptcha.php';
require_once APP_ROOT . '/src/security/redirects.php';
require_once APP_ROOT . '/src/security/guard.php';
require_once APP_ROOT . '/src/security/csrf.php';
guardPublic();
$csrfFormKey = 'resend_verification';
function sendVerificationEmail(
    array $emailConfig,
    string $toEmail,
    string $forename,
    string $verifyUrl,
): void {
    $subject = 'Verify your Focus on Life account';

    $safeName = trim($forename) !== '' ? trim($forename) : 'there';

    $message = <<<TEXT
    Hi {$safeName},

    Please verify your email address by clicking the link below:

    {$verifyUrl}

    This link will expire in 24 hours.

    If you did not create this account, you can ignore this email.

    Focus on Life
    TEXT;

    $mail = new \PhpBook\Email\Email($emailConfig);
    $mail->sendEmail($emailConfig['admin_email'], $toEmail, $subject, $message);
}

$email = '';
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = $_POST['csrf_token'] ?? '';

    if (!csrf_validate($csrfFormKey, is_string($submittedCsrf) ? $submittedCsrf : null)) {
        error_log('[RESEND VERIFICATION] CSRF validation failed sid=' . session_id());
        $errors['warning'] =
            'Your form session expired or failed security validation. Please try again.';
        csrf_rotate($csrfFormKey);
    }
    $email = trim((string) ($_POST['email'] ?? ''));

    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
    if (empty($recaptchaToken)) {
        $errors['warning'] = 'Security check token missing. Please refresh the page and try again.';
    } else {
        $secretKey = $config['recaptcha_secret_key'] ?? '';
        if (!verify_recaptcha_v3($recaptchaToken, 'resend_verification', $secretKey, 0.1)) {
            $errors['warning'] = 'Verification resend failed security check. Please try again.';
        }
    }

    if (empty($errors['warning'])) {
        $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
        $errors['email'] = Validate::isEmail($email) ? '' : 'Please enter a valid email address';

        if (!empty($errors['email'])) {
            $errors['warning'] = 'Please correct the errors.';
        } else {
            csrf_rotate($csrfFormKey);
            try {
                $websiteId = (int) ($_SESSION['website'] ?? 1);
                if ($websiteId <= 0) {
                    $websiteId = 1;
                }

                $member = $cms->getMember()->getByEmailMasterAndWebsite($email, $websiteId);

                if ($member && (int) ($member['email_verified'] ?? 0) !== 1) {
                    $userId = (int) ($member['id'] ?? 0);
                    $forename = (string) ($member['forename'] ?? '');
                    $websiteId = (int) ($member['website'] ?? 1);

                    if ($userId > 0) {
                        $cms->getMember()->expireUnusedEmailVerifications($userId);

                        $rawToken = bin2hex(random_bytes(32));
                        $tokenHash = hash('sha256', $rawToken);

                        $expiryDate = new \DateTimeImmutable('+24 hours');
                        $expiresAt = $expiryDate->format('Y-m-d H:i:s');

                        $saved = $cms
                            ->getMember()
                            ->createEmailVerification($userId, $tokenHash, $expiresAt);

                        if ($saved) {
                            $scheme =
                                !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
                                    ? 'https'
                                    : 'http';
                            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                            $verifyUrl =
                                $scheme .
                                '://' .
                                $host .
                                DOC_ROOT .
                                'verify-email?token=' .
                                urlencode($rawToken);

                            sendVerificationEmail($email_config, $email, $forename, $verifyUrl);
                        }
                    }
                }

                $success =
                    'If that email address is registered and not yet verified, a new verification email has been sent.';
            } catch (Throwable $e) {
                error_log('[RESEND VERIFICATION ERROR] ' . $e->getMessage());
                $success =
                    'If that email address is registered and not yet verified, a new verification email has been sent.';
            }
        }
    }
}

$websiteId = (int) ($_SESSION['website'] ?? 1);
if ($websiteId <= 0) {
    $websiteId = 1;
}

$data = [];
$data['navigation'] = $cms->getMenu()->getAll2($websiteId, 1);
$data['website'] = $cms->getWebsite()->getById($websiteId);
$data['doc_root'] = $config['doc_root'];
$data['email'] = $email;
$data['errors'] = $errors;
$data['success'] = $success;
$data['use_recaptcha'] = true;
$data['recaptcha_site_key'] = $config['recaptcha_site_key'];
$data['csrf_token'] = csrf_token($csrfFormKey);

echo $twig->render('resend-verification.html', $data);
exit();
