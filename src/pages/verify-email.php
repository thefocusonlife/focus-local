<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/redirects.php';
require_once APP_ROOT . '/src/security/guard.php';

guardPublic();
error_log('[VERIFY PAGE HIT]');

$data = [];
$data['success'] = '';
$data['failure'] = '';

// Default fallback until token/member tells us the real website context
$websiteId = 1;
$data['website'] = $cms->getWebsite()->getById($websiteId);
$data['navigation'] = $cms->getMenu()->getAll2($websiteId, 1);

$rawToken = trim((string) ($_GET['token'] ?? ''));

error_log('[VERIFY] raw token present=' . ($rawToken !== '' ? 'yes' : 'no'));

if ($rawToken === '') {
    $data['failure'] = 'Missing verification token.';
    echo $twig->render('verify-email.html', $data);
    exit();
}

$tokenHash = hash('sha256', $rawToken);
error_log('[VERIFY] token hash=' . $tokenHash);

try {
    $verification = $cms->getMember()->getEmailVerificationByTokenHash($tokenHash);
    error_log('[VERIFY] lookup result=' . json_encode($verification));

    if (!$verification) {
        $data['failure'] = 'Invalid verification link.';
        echo $twig->render('verify-email.html', $data);
        exit();
    }

    // Derive website context from the member tied to this token
    $userId = (int) ($verification['user_id'] ?? 0);
    if ($userId > 0) {
        $member = $cms->getMember()->get($userId);
        if ($member) {
            $websiteId = (int) ($member['website'] ?? 1);
            $data['website'] = $cms->getWebsite()->getById($websiteId);
            $data['navigation'] = $cms->getMenu()->getAll2($websiteId, 1);
            error_log('[VERIFY] website context resolved to website_id=' . $websiteId);
        }
    }

    if (!empty($verification['used_at'])) {
        $data['failure'] = 'This verification link has already been used.';
        echo $twig->render('verify-email.html', $data);
        exit();
    }

    if ((int) ($verification['email_verified'] ?? 0) === 1) {
        $data['success'] = 'Your email address is already verified. You may sign in.';
        echo $twig->render('verify-email.html', $data);
        exit();
    }

    $expiresAt = new DateTimeImmutable((string) $verification['expires_at']);
    $now = new DateTimeImmutable('now');

    error_log('[VERIFY] expires_at=' . $expiresAt->format('Y-m-d H:i:s'));
    error_log('[VERIFY] now=' . $now->format('Y-m-d H:i:s'));

    if ($expiresAt < $now) {
        $data['failure'] = 'This verification link has expired.';
        echo $twig->render('verify-email.html', $data);
        exit();
    }

    $verificationId = (int) ($verification['verification_id'] ?? 0);

    error_log('[VERIFY] user_id=' . $userId . ' verification_id=' . $verificationId);

    $verified = $cms->getMember()->markEmailVerified($userId);
    error_log('[VERIFY] markEmailVerified result=' . ($verified ? 'true' : 'false'));

    if (!$verified) {
        throw new RuntimeException('Failed to update member verification status.');
    }

    $used = $cms->getMember()->markEmailVerificationUsed($verificationId);
    error_log('[VERIFY] markEmailVerificationUsed result=' . ($used ? 'true' : 'false'));

    if (!$used) {
        throw new RuntimeException('Failed to mark verification token as used.');
    }

    $data['success'] = 'Your email address has been verified. You may now sign in.';
} catch (Throwable $e) {
    error_log('[VERIFY EMAIL ERROR] ' . $e->getMessage());
    $data['failure'] =
        'We could not verify your email address. Please try again or request a new verification email.';
}

echo $twig->render('verify-email.html', $data);
exit();
