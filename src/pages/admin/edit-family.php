<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/bootstrap.php';

require_once APP_ROOT . '/src/security/guard.php';
require_once APP_ROOT . '/src/security/redirects.php';
include APP_ROOT . '/src/pages/menu-path.php';

guardMember();

/**
 * Public-safe fallback route (avoid leaking admin pages on deny)
 */
function publicSafePath(): string
{
    $websiteId = (int) ($_SESSION['website'] ?? 1);
    if ($websiteId <= 0) {
        $websiteId = 1;
    }
    return 'index/' . $websiteId;
}

function logDeny(string $reason, array $ctx = []): void
{
    $row = [
        'ts' => date('c'),
        'event' => 'DENY',
        'reason' => $reason,
        'member_id' => $_SESSION['id'] ?? ($_SESSION['member_id'] ?? null),
        'role' => $_SESSION['role'] ?? 'guest',
        'website' => $_SESSION['website'] ?? null,
        'path' => $_SERVER['REQUEST_URI'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'ctx' => $ctx,
    ];
    error_log(json_encode($row, JSON_UNESCAPED_SLASHES));
}

// Flash
$data = [];
if (!empty($_SESSION['flash_failure'])) {
    $data['flash_failure'] = $_SESSION['flash_failure'];
    unset($_SESSION['flash_failure']);
}
if (!empty($_SESSION['flash_success'])) {
    $data['flash_success'] = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Session basics
$sessionMemberId = (int) ($_SESSION['id'] ?? ($_SESSION['member_id'] ?? 0));
if ($sessionMemberId <= 0) {
    redirect('login');
    exit();
}

$role = (string) ($_SESSION['role'] ?? '');
$websiteId = (int) ($_SESSION['website'] ?? 0);

if ($websiteId <= 0) {
    logDeny('missing_website', []);
    $_SESSION['flash_failure'] = 'Access denied.';
    redirect(publicSafePath(), ['failure' => 'Access denied']);
    exit();
}

$website = $cms->getWebsite()->getById($websiteId);
if (!$website) {
    logDeny('invalid_website', ['website_id' => $websiteId]);
    $_SESSION['flash_failure'] = 'Access denied.';
    redirect(publicSafePath(), ['failure' => 'Access denied']);
    exit();
}

// ------------------------------------------------------------
// Route fallback: derive $id if router didn't inject it
// URL expected: /admin/edit-family/{id}
// Optional: /admin/edit-family?id=123
// ------------------------------------------------------------
if (!isset($id) || (int) $id <= 0) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
    $path = trim($path, '/');
    $parts = explode('/', $path);

    // If your app is under /focus-local/public, strip those segments if present
    if (($parts[0] ?? '') === 'focus-local') {
        array_shift($parts);
    }
    if (($parts[0] ?? '') === 'public') {
        array_shift($parts);
    }

    // Expect: admin/edit-family/{id}
    if (($parts[0] ?? '') === 'admin' && ($parts[1] ?? '') === 'edit-family') {
        if (!empty($parts[2]) && ctype_digit($parts[2])) {
            $id = (int) $parts[2];
        }
    }

    // Optional query param fallback
    if (
        (!isset($id) || (int) $id <= 0) &&
        !empty($_GET['id']) &&
        ctype_digit((string) $_GET['id'])
    ) {
        $id = (int) $_GET['id'];
    }
}

$targetMemberId = (int) ($id ?? 0);
if ($targetMemberId <= 0) {
    redirect('page-not-found/');
    exit();
}

// OWNER-ONLY hardening (if you want uber override: && $role !== 'uber')
if ($targetMemberId !== $sessionMemberId) {
    logDeny('not_owner', ['target_member_id' => $targetMemberId]);
    $_SESSION['flash_failure'] = 'Access denied.';
    redirect(publicSafePath());
    exit();
}

// ------------------------------------------------------------
// Tenant-scoped fetch (prefer a website-scoped method if available)
// ------------------------------------------------------------

// If you have/added this Week 3 method, use it:
/*$websiteId = (int) ($_SESSION['website'] ?? 0);
$memberId = (int) ($_POST['member_id'] ?? 0);

$cms->getMember()->get($memberId);
if (!$member) {
    $_SESSION['flash_failure'] = 'Access denied.';
    redirect(publicSafePath());
    exit();
}*/
// Fallback if your model doesn't have it yet:
$targetMember = $cms->getMember()->get($targetMemberId);

if (!$targetMember || !isset($targetMember['id'])) {
    redirect('page-not-found/');
    exit();
}

if ((int) ($targetMember['website'] ?? 0) !== $websiteId) {
    logDeny('cross_tenant_member_fetch', [
        'target_member_id' => $targetMemberId,
        'member_website' => (int) ($targetMember['website'] ?? 0),
        'session_website' => $websiteId,
    ]);
    redirect('page-not-found/');
    exit();
}

// ------------------------------------------------------------
// Allowed Family/Account IDs for dropdown (tamper-proof)
// ------------------------------------------------------------
$personalFamilyId = (int) ($targetMember['id'] ?? 0); // ALWAYS allow
$currentFamilyId = (int) ($targetMember['account_id'] ?? 0); // ALWAYS allow

$displayName = trim(($targetMember['forename'] ?? '') . ' ' . ($targetMember['surname'] ?? ''));

// Allowed set
$allowedAccountIds = [];
if ($personalFamilyId > 0) {
    $allowedAccountIds[$personalFamilyId] = true;
}
if ($currentFamilyId > 0) {
    $allowedAccountIds[$currentFamilyId] = true;
}

$accountOptions = [];

// Personal
if ($personalFamilyId > 0) {
    $accountOptions[] = [
        'account_id' => $personalFamilyId,
        'account_name' => ($displayName !== '' ? $displayName : 'Self') . ' (Personal)',
    ];
}

// Current
if ($currentFamilyId > 0) {
    $accountOptions[] = [
        'account_id' => $currentFamilyId,
        'account_name' => ($displayName !== '' ? $displayName : 'Self') . ' (Current)',
    ];
}

// Approved outgoing follows (one-sided)
$followAccounts = [];
$followAccounts = $cms->getNote()->getAllowedFollowAccounts($websiteId, $targetMemberId, 1);
if (!is_array($followAccounts)) {
    $followAccounts = [];
}

foreach ($followAccounts as $row) {
    $aid = (int) ($row['account_id'] ?? 0);
    if ($aid <= 0) {
        continue;
    }

    $allowedAccountIds[$aid] = true;
    $accountOptions[] = [
        'account_id' => $aid,
        'account_name' => (string) ($row['account_name'] ?? 'Account #' . $aid),
    ];
}

// Deduplicate options
$tmp = [];
foreach ($accountOptions as $opt) {
    $tmp[(int) $opt['account_id']] = $opt;
}
$accountOptions = array_values($tmp);

// ------------------------------------------------------------
// POST: validate CSRF + tenant-scoped write
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fail closed: CSRF must exist and verify helper must be available
    if (!function_exists('verify_csrf') || !function_exists('generate_csrf_token')) {
        $_SESSION['flash_failure'] = 'Invalid request (CSRF unavailable).';
        redirect('admin/members/');
        exit();
    }

    $token = (string) ($_POST['csrf'] ?? '');
    if ($token === '' || !verify_csrf($token)) {
        $_SESSION['flash_failure'] = 'Invalid request. Please try again.';
        redirect('admin/members/');
        exit();
    }

    $accountId = (int) ($_POST['account_id'] ?? 0);
    if ($accountId <= 0 || !isset($allowedAccountIds[$accountId])) {
        logDeny('tampered_account_id', [
            'target_member_id' => $targetMemberId,
            'posted_account_id' => $accountId,
        ]);
        redirect('page-not-found/');
        exit();
    }

    // Week 3 requirement: tenant-scoped write (WHERE id AND website)
    // Prefer: $cms->getMember()->updateAccountIdForWebsite($targetMemberId, $websiteId, $accountId);
    $ok = $cms->getMember()->updateAccountIdForWebsite($targetMemberId, $websiteId, $accountId);

    if ($ok !== true) {
        $_SESSION['flash_failure'] = 'Update failed.';
        redirect('admin/members/');
        exit();
    }

    // Keep session in sync (editing own record)
    $_SESSION['account_id'] = (int) $accountId;
    $_SESSION['follow_id'] = (int) $accountId;

    $_SESSION['flash_success'] = 'Family updated.';
    redirect('admin/members/');
    exit();
}

// ------------------------------------------------------------
// GET: render
// ------------------------------------------------------------
$data['member'] = $targetMember;
$data['website'] = $website;
$data['account_options'] = $accountOptions;
$data['selected_account_id'] = (int) ($targetMember['account_id'] ?? $targetMemberId);
$data['csrf_token'] = generate_csrf_token();
echo $twig->render('admin/edit-family.html', $data);
