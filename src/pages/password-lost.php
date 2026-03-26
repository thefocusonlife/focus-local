<?php
declare(strict_types=1);

use PhpBook\Validate\Validate;

require_once __DIR__ . '/../../config/recaptcha.php';
require_once APP_ROOT . '/src/security/redirects.php';
require_once APP_ROOT . '/src/security/guard.php';
require_once APP_ROOT . '/src/security/csrf.php';
guardPublic();

$csrfFormKey = 'password_lost';

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
    $result = $mail->sendEmail($emailConfig['admin_email'], $toEmail, $subject, $message);
    error_log('[PASSWORD LOST] mail result=' . var_export($result, true));
}

$email = '';
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = $_POST['csrf_token'] ?? '';

    if (!csrf_validate($csrfFormKey, is_string($submittedCsrf) ? $submittedCsrf : null)) {
        error_log('[PASSWORD LOST] CSRF validation failed sid=' . session_id());
        $errors['warning'] =
            'Your form session expired or failed security validation. Please try again.';
        csrf_rotate($csrfFormKey);
    }

    $email = trim((string) ($_POST['email'] ?? ''));

    if (empty($errors['warning'])) {
        $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
        if (empty($recaptchaToken)) {
            $errors['warning'] =
                'Security check token missing. Please refresh the page and try again.';
        } else {
            $secretKey = $config['recaptcha_secret_key'] ?? '';
            if (!verify_recaptcha_v3($recaptchaToken, 'password_lost', $secretKey, 0.1)) {
                $errors['warning'] = 'Password reset failed security check. Please try again.';
            }
        }
    }

    if (empty($errors['warning'])) {
        $errors['email'] = Validate::isEmail($email) ? '' : 'Please enter a valid email address';

        if (!empty($errors['email'])) {
            $errors['warning'] = 'Please correct the errors.';
        } else {
            try {
                $websiteId = (int) ($_SESSION['website'] ?? 1);
                if ($websiteId <= 0) {
                    $websiteId = 1;
                }

                error_log('[PASSWORD LOST] session website=' . $websiteId . ' email=' . $email);

                $member = $cms->getMember()->getByEmailMasterAndWebsite($email, $websiteId);

                if ($member) {
                    $userId = (int) ($member['id'] ?? 0);
                    $forename = (string) ($member['forename'] ?? '');

                    error_log(
                        '[PASSWORD LOST] Found member id=' .
                            $userId .
                            ' website=' .
                            (int) ($member['website'] ?? 0) .
                            ' email_master=' .
                            (string) ($member['email_master'] ?? ''),
                    );

                    if ($userId > 0) {
                        $cms->getMember()->expireUnusedPasswordResets($userId);

                        $rawToken = bin2hex(random_bytes(32));
                        $tokenHash = hash('sha256', $rawToken);
                        $expiryDate = new \DateTimeImmutable('+1 hour');
                        $expiresAt = $expiryDate->format('Y-m-d H:i:s');

                        $saved = $cms
                            ->getMember()
                            ->createPasswordReset($userId, $tokenHash, $expiresAt);

                        error_log('[PASSWORD LOST] token save=' . ($saved ? 'true' : 'false'));

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

                            error_log(
                                '[PASSWORD LOST] sending to=' . $email . ' resetUrl=' . $resetUrl,
                            );

                            sendPasswordResetEmail($email_config, $email, $forename, $resetUrl);
                        }
                    }
                } else {
                    error_log(
                        '[PASSWORD LOST] No member found for email_master=' .
                            $email .
                            ' website=' .
                            $websiteId,
                    );
                }

                $success =
                    'If that email address is registered, a password reset email has been sent.';

                csrf_rotate($csrfFormKey);
            } catch (Throwable $e) {
                error_log('[PASSWORD LOST] ' . $e->getMessage());

                if (defined('DEV') && DEV) {
                    $errors['warning'] = 'Password reset failed in development. Check error log.';
                } else {
                    $success =
                        'If that email address is registered, a password reset email has been sent.';
                }
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
$data['doc_root'] = $config['doc_root'] ?? '/_stage/';
$data['email'] = $email;
$data['errors'] = $errors;
$data['success'] = $success;
$data['use_recaptcha'] = true;
$data['recaptcha_site_key'] = $config['recaptcha_site_key'];
$data['csrf_token'] = csrf_token($csrfFormKey);

echo $twig->render('password-lost.html', $data);
exit();
