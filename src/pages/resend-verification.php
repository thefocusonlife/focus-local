<?php
declare(strict_types=1);

use PhpBook\Validate\Validate;

require_once __DIR__ . '/../../config/recaptcha.php';
require_once APP_ROOT . '/src/security/redirects.php';
require_once APP_ROOT . '/src/security/guard.php';
require_once APP_ROOT . '/src/security/csrf.php';

/** @var array $config */
/** @var array $email_config */
/** @var mixed $cms */
/** @var \Twig\Environment $twig */

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

    $htmlMessage = <<<HTML
    <p>Hi {$safeName},</p>

    <p>Please verify your email address by clicking the link below:</p>

    <p><a href="{$verifyUrl}">Verify your email</a></p>

    <p>This link will expire in 24 hours.</p>

    <p>If you did not create this account, you can ignore this email.</p>

    <p>Focus on Life</p>
    HTML;

    $recipientDomain = strtolower(substr(strrchr($toEmail, '@') ?: '', 1));

    error_log(
        sprintf(
            '[EMAIL_VERIFY][RESEND] Preparing mail host=%s recipient_domain=%s sender=%s email_class=%s',
            $_SERVER['HTTP_HOST'] ?? 'unknown',
            $recipientDomain !== '' ? $recipientDomain : 'unknown',
            $emailConfig['admin_email'] ?? 'not-configured',
            \PhpBook\Email\Email::class,
        ),
    );

    $mail = new \PhpBook\Email\Email($emailConfig);

    error_log(
        sprintf(
            '[EMAIL_VERIFY][RESEND] Calling sendEmail host=%s recipient_domain=%s',
            $_SERVER['HTTP_HOST'] ?? 'unknown',
            $recipientDomain !== '' ? $recipientDomain : 'unknown',
        ),
    );

    $mail->sendEmail($emailConfig['admin_email'], $toEmail, $subject, $htmlMessage);

    error_log(
        sprintf(
            '[EMAIL_VERIFY][RESEND] sendEmail returned without exception host=%s recipient_domain=%s',
            $_SERVER['HTTP_HOST'] ?? 'unknown',
            $recipientDomain !== '' ? $recipientDomain : 'unknown',
        ),
    );
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
                            $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');

                            $verifyUrl = $baseUrl . '/verify-email?token=' . urlencode($rawToken);

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
$data['doc_root'] = $config['doc_root'] ?? '/_stage/';
$data['email'] = $email;
$data['errors'] = $errors;
$data['success'] = $success;
$data['use_recaptcha'] = true;
$data['recaptcha_site_key'] = $config['recaptcha_site_key'];
$data['csrf_token'] = csrf_token($csrfFormKey);

echo $twig->render('resend-verification.html', $data);
exit();
