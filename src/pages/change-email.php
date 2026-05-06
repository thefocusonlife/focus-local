<?php
declare(strict_types=1);

use PhpBook\Validate\Validate;

require_once __DIR__ . '/../../config/recaptcha.php';
require_once APP_ROOT . '/src/security/guard.php';

/** @var array $config */
/** @var array $email_config */
/** @var mixed $cms */
/** @var \Twig\Environment $twig */

guardMember();

function sendEmailChangeVerification(
    array $emailConfig,
    string $toEmail,
    string $forename,
    string $verifyUrl,
): void {
    $subject = 'Confirm your new Focus on Life email address';

    $safeName = trim($forename) !== '' ? trim($forename) : 'there';

    $message = <<<TEXT
    Hi {$safeName},

    We received a request to change the email address on your Focus on Life account.

    Please confirm your new email address by clicking the link below:

    {$verifyUrl}

    This link will expire in 24 hours.

    If you did not request this change, you can ignore this email.

    Focus on Life
    TEXT;

    $mail = new \PhpBook\Email\Email($emailConfig);
    $mail->sendEmail($emailConfig['admin_email'], $toEmail, $subject, $message);
}

$errors = [];
$success = '';
$newEmail = '';

$memberId = (int) ($_SESSION['id'] ?? 0);
$member = $cms->getMember()->getForEmailChangeById($memberId);

if (!$member || $memberId <= 0) {
    redirect('login/');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newEmail = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['confirm_password'] ?? '');

    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
    if (empty($recaptchaToken)) {
        $errors['warning'] = 'Security check token missing. Please refresh the page and try again.';
    } else {
        $secretKey = $config['recaptcha_secret_key'] ?? '';
        if (!verify_recaptcha_v3($recaptchaToken, 'change_email', $secretKey, 0.1)) {
            $errors['warning'] = 'Email change failed security check. Please try again.';
        }
    }

    if (empty($errors['warning'])) {
        $errors['email'] = Validate::isEmail($newEmail) ? '' : 'Please enter a valid email address';

        if (!password_verify($password, (string) ($member['password'] ?? ''))) {
            $errors['password'] = 'Password is incorrect.';
        } else {
            $errors['password'] = '';
        }

        $oldEmailMaster = strtolower((string) ($member['email_master'] ?? ''));
        $newEmailMaster = strtolower(trim($newEmail));

        if (empty($errors['email']) && empty($errors['password'])) {
            if ($newEmailMaster === $oldEmailMaster) {
                $errors['email'] = 'That is already your current email address.';
            } else {
                $linkedMembers = $cms->getMember()->getAllByEmailMaster($oldEmailMaster);

                if (!$linkedMembers) {
                    $errors['warning'] = 'We could not find linked accounts for this email.';
                } else {
                    foreach ($linkedMembers as $linkedMember) {
                        $websiteId = (int) ($linkedMember['website'] ?? 0);
                        if ($websiteId <= 0) {
                            continue;
                        }

                        $candidateLogin =
                            $websiteId > 1 ? $newEmailMaster . $websiteId : $newEmailMaster;

                        if (
                            $cms
                                ->getMember()
                                ->emailLoginExistsOutsideEmailMaster(
                                    $candidateLogin,
                                    $oldEmailMaster,
                                )
                        ) {
                            $errors['email'] = 'That email cannot be used for this account group.';
                            break;
                        }
                    }
                }
            }
        }

        if (empty($errors['warning']) && empty($errors['email']) && empty($errors['password'])) {
            try {
                $cms->getMember()->expireUnusedEmailChangeRequestsByEmailMaster($oldEmailMaster);

                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);

                $expiryDate = new \DateTimeImmutable('+24 hours');
                $expiresAt = $expiryDate->format('Y-m-d H:i:s');

                $saved = $cms
                    ->getMember()
                    ->createEmailChangeRequest(
                        $memberId,
                        $oldEmailMaster,
                        $newEmailMaster,
                        $tokenHash,
                        $expiresAt,
                    );

                if (!$saved) {
                    throw new RuntimeException('Failed to create email change request.');
                }

                $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');

                $verifyUrl = $baseUrl . '/confirm-email-change?token=' . urlencode($rawToken);
                sendEmailChangeVerification(
                    $email_config,
                    $newEmailMaster,
                    (string) ($member['forename'] ?? ''),
                    $verifyUrl,
                );

                $success =
                    'A confirmation link has been sent to your new email address. Your account email will not change until you click that link.';
                $newEmail = '';
            } catch (Throwable $e) {
                $errors['warning'] =
                    'We could not process your email change request. Please try again.';
            }
        } elseif (empty($errors['warning'])) {
            $errors['warning'] = 'Please correct the errors.';
        }
    }
}

$data = [];
$data['navigation'] = $cms->getMenu()->getAll2((int) ($_SESSION['website'] ?? 1), 1);
$data['website'] = $cms->getWebsite()->getById((int) ($_SESSION['website'] ?? 1));
$data['errors'] = $errors;
$data['success'] = $success;
$data['email'] = $newEmail;
$data['current_email'] = (string) ($member['email_master'] ?? '');
$data['use_recaptcha'] = true;
$data['recaptcha_site_key'] = $config['recaptcha_site_key'];

echo $twig->render('change-email.html', $data);
exit();
