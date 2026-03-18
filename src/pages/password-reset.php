<?php
declare(strict_types=1);

use PhpBook\Validate\Validate;

require_once APP_ROOT . '/src/security/redirects.php';
require_once APP_ROOT . '/src/security/guard.php';
guardPublic();

$token = trim((string) ($_GET['token'] ?? ($_POST['token'] ?? '')));
$errors = [];
$success = '';
$validToken = false;

if ($token === '') {
    $errors['message'] = 'Missing password reset token.';
} else {
    $tokenHash = hash('sha256', $token);
    $reset = $cms->getMember()->getPasswordResetByTokenHash($tokenHash);

    if (!$reset) {
        $errors['message'] = 'Invalid password reset link.';
    } elseif (!empty($reset['used_at'])) {
        $errors['message'] = 'This password reset link has already been used.';
    } else {
        $expiresAt = new \DateTimeImmutable((string) $reset['expires_at']);
        $now = new \DateTimeImmutable('now');

        if ($expiresAt < $now) {
            $errors['message'] = 'This password reset link has expired.';
        } else {
            $validToken = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    $errors['password'] = Validate::isPassword($password)
        ? ''
        : 'Passwords must be at least 8 characters and have:<br>
            A lowercase letter<br>An uppercase letter<br>A number
            <br>And a special character';

    $errors['confirm'] = $password === $confirm ? '' : 'Passwords do not match';

    $invalid = implode($errors);

    if (!$invalid) {
        try {
            $userId = (int) ($reset['user_id'] ?? 0);
            $resetId = (int) ($reset['reset_id'] ?? 0);

            $updated = $cms->getMember()->updatePasswordById($userId, $password);
            if (!$updated) {
                throw new RuntimeException('Failed to update password.');
            }

            $used = $cms->getMember()->markPasswordResetUsed($resetId);
            if (!$used) {
                throw new RuntimeException('Failed to mark reset token used.');
            }

            $success = 'Your password has been reset successfully. You may now sign in.';
            $validToken = false;
        } catch (Throwable $e) {
            error_log('[PASSWORD RESET] ' . $e->getMessage());
            $errors['message'] = 'We could not reset your password. Please try again.';
        }
    }
}

$data = [];
$data['navigation'] = $cms->getMenu()->getAll2(1, 1);
$data['website'] = $cms->getWebsite()->getById(1);
$data['errors'] = $errors;
$data['success'] = $success;
$data['token'] = $token;
$data['validToken'] = $validToken;

echo $twig->render('password-reset.html', $data);
exit();
