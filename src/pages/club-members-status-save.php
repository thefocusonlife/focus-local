<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';
require_once APP_ROOT . '/src/security/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index/1');
    exit();
}

$websiteId = filter_input(INPUT_POST, 'website_id', FILTER_VALIDATE_INT);
$supportedWebsiteIds = [44, 51];

if (!is_int($websiteId) || !in_array($websiteId, $supportedWebsiteIds, true)) {
    $_SESSION['flash_failure'] = 'A valid club website is required.';
    redirect('index/1');
    exit();
}

$returnUrl = 'club-members-admin?website=' . $websiteId;

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

if ($viewerId <= 0 || $viewerId === 2 || $role === 'guest') {
    $_SESSION['return_to'] = '/' . $returnUrl;
    $_SESSION['flash_failure'] = 'You must be logged in to update membership status.';

    redirect('login');
    exit();
}

$allowedMemberAdmins = [
    44 => [1, 3, 339, 500],
    51 => [1],
];

if (!in_array($viewerId, $allowedMemberAdmins[$websiteId], true)) {
    $_SESSION['flash_failure'] = 'You do not have permission to update club memberships.';

    redirect('index/' . $websiteId);
    exit();
}

$membershipId = filter_input(INPUT_POST, 'membership_id', FILTER_VALIDATE_INT);

$membershipStatus = trim((string) ($_POST['membership_status'] ?? ''));

$allowedStatuses = ['pending', 'active', 'inactive', 'declined'];

if (
    !is_int($membershipId) ||
    $membershipId < 1 ||
    !in_array($membershipStatus, $allowedStatuses, true)
) {
    $_SESSION['flash_failure'] = 'A valid membership and status are required.';

    redirect($returnUrl);
    exit();
}

$csrfFormKey = 'club_member_status_' . $websiteId;
$submittedCsrf = $_POST['csrf_token'] ?? '';

if (!csrf_validate($csrfFormKey, is_string($submittedCsrf) ? $submittedCsrf : null)) {
    error_log('[CLUB MEMBERSHIP STATUS] CSRF validation failed for viewer ' . $viewerId);

    csrf_rotate($csrfFormKey);

    $_SESSION['flash_failure'] =
        'Your form session expired or failed security validation. Please try again.';

    redirect($returnUrl);
    exit();
}

$membership = $cms->getClubMembers()->getById($membershipId, $websiteId);

if ($membership === null) {
    $_SESSION['flash_failure'] = 'The selected club membership could not be found.';

    redirect($returnUrl);
    exit();
}

try {
    $cms->getClubMembers()->updateStatus($membershipId, $websiteId, $membershipStatus);

    csrf_rotate($csrfFormKey);

    $_SESSION['flash_success'] = 'Membership status updated successfully.';

    redirect($returnUrl);
    exit();
} catch (Throwable $e) {
    error_log('[CLUB MEMBERSHIP STATUS] ' . $e->getMessage());

    $_SESSION['flash_failure'] = 'Unable to update membership status. Please try again.';

    redirect($returnUrl);
    exit();
}
