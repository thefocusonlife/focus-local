<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';
guardPublic();

$data = [];
$data['success'] = '';
$data['failure'] = '';
$data['website'] = $cms->getWebsite()->getById(1);
$data['navigation'] = $cms->getMenu()->getAll2(1, 1);

$rawToken = trim((string) ($_GET['token'] ?? ''));

if ($rawToken === '') {
    $data['failure'] = 'Missing email change token.';
    echo $twig->render('confirm-email-change.html', $data);
    exit();
}

$tokenHash = hash('sha256', $rawToken);

try {
    $request = $cms->getMember()->getEmailChangeRequestByTokenHash($tokenHash);

    if (!$request) {
        $data['failure'] = 'Invalid email change link.';
        echo $twig->render('confirm-email-change.html', $data);
        exit();
    }

    if (!empty($request['used_at'])) {
        $data['failure'] = 'This email change link has already been used.';
        echo $twig->render('confirm-email-change.html', $data);
        exit();
    }

    $expiresAt = new \DateTimeImmutable((string) $request['expires_at']);
    $now = new \DateTimeImmutable('now');

    if ($expiresAt < $now) {
        $data['failure'] = 'This email change link has expired.';
        echo $twig->render('confirm-email-change.html', $data);
        exit();
    }

    $userId = (int) ($request['user_id'] ?? 0);
    $requestId = (int) ($request['request_id'] ?? 0);
    $oldEmailMaster = strtolower((string) ($request['old_email_master'] ?? ''));
    $newEmailMaster = strtolower((string) ($request['new_email_master'] ?? ''));
    $websiteId = (int) ($request['website'] ?? 1);

    if ($userId <= 0 || $requestId <= 0 || $oldEmailMaster === '' || $newEmailMaster === '') {
        $data['failure'] = 'Email change data is invalid.';
        echo $twig->render('confirm-email-change.html', $data);
        exit();
    }

    $linkedMembers = $cms->getMember()->getAllByEmailMaster($oldEmailMaster);

    if (!$linkedMembers) {
        $data['failure'] = 'No linked accounts were found for this email change.';
        echo $twig->render('confirm-email-change.html', $data);
        exit();
    }

    foreach ($linkedMembers as $linkedMember) {
        $linkedWebsiteId = (int) ($linkedMember['website'] ?? 0);
        if ($linkedWebsiteId <= 0) {
            continue;
        }

        $candidateLogin =
            $linkedWebsiteId > 1 ? $newEmailMaster . $linkedWebsiteId : $newEmailMaster;

        if (
            $cms->getMember()->emailLoginExistsOutsideEmailMaster($candidateLogin, $oldEmailMaster)
        ) {
            $data['failure'] = 'That email is no longer available for this account group.';
            echo $twig->render('confirm-email-change.html', $data);
            exit();
        }
    }

    $updated = $cms->getMember()->updateAllEmailsByEmailMaster($oldEmailMaster, $newEmailMaster);

    if (!$updated) {
        throw new RuntimeException('Failed to update linked member emails.');
    }

    $used = $cms->getMember()->markEmailChangeRequestUsed($requestId);
    if (!$used) {
        throw new RuntimeException('Failed to mark email change request as used.');
    }

    $data['website'] = $cms->getWebsite()->getById($websiteId) ?: $data['website'];
    $data['navigation'] = $cms->getMenu()->getAll2($websiteId, 1);
    $data['success'] =
        'Your email address has been updated successfully across your linked Focus on Life memberships.';
} catch (Throwable $e) {
    error_log('[CONFIRM EMAIL CHANGE] ' . $e->getMessage());
    $data['failure'] = 'We could not confirm your new email address. Please try again.';
}

echo $twig->render('confirm-email-change.html', $data);
exit();
