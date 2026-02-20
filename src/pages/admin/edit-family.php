<?php
declare(strict_types=1);

require_once __DIR__ . '/../security/guard.php';
guardMember();

// admin/edit-family/{id}
// $id comes from routing: the member id whose family link is being edited.

$sessionMemberId = (int) ($_SESSION['id'] ?? 0);
if ($sessionMemberId <= 0) {
    redirect('login');
}

$role = (string) ($_SESSION['role'] ?? ''); // keep if you later want uber override
$websiteId = (int) ($_SESSION['website'] ?? 0);
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
}

// OWNER-ONLY hardening (if you want uber override, add: && $role !== 'uber')
if ($targetMemberId !== $sessionMemberId) {
    redirect('page-not-found/');
}

// Load target member for BOTH GET and POST
$targetMember = $cms->getMember()->get($targetMemberId);
if (!$targetMember || !isset($targetMember['id'])) {
    redirect('page-not-found/');
}

// Website scope guard (optional but recommended even for owner-only)
if ((int) ($targetMember['website'] ?? 0) !== $websiteId) {
    redirect('page-not-found/');
}

// ------------------------------------------------------------
// Allowed Family/Account IDs for dropdown (tamper-proof)
// - Always include Personal Family = member.id
// - Always include Current Family = member.account_id
// - Plus approved outgoing follows from note table
// ------------------------------------------------------------

$personalFamilyId = (int) ($targetMember['id'] ?? 0); // ALWAYS allow
$currentFamilyId = (int) ($targetMember['account_id'] ?? 0); // ALWAYS allow

$displayName = trim(($targetMember['forename'] ?? '') . ' ' . ($targetMember['surname'] ?? ''));

// Build allowed set
$allowedAccountIds = [];
if ($personalFamilyId > 0) {
    $allowedAccountIds[$personalFamilyId] = true;
}
if ($currentFamilyId > 0) {
    $allowedAccountIds[$currentFamilyId] = true;
}

// Build dropdown options (dedupe by id at end)
$accountOptions = [];

// Personal family option (lets user revert back to "self family")
if ($personalFamilyId > 0) {
    $accountOptions[] = [
        'account_id' => $personalFamilyId,
        'account_name' => ($displayName !== '' ? $displayName : 'Self') . ' (Personal)',
    ];
}

// Current family option (where they are now; may equal personal)
if ($currentFamilyId > 0) {
    $accountOptions[] = [
        'account_id' => $currentFamilyId,
        'account_name' => ($displayName !== '' ? $displayName : 'Self') . ' (Current)',
    ];
}

// Approved outgoing follows (one-sided)
$followAccounts = [];
if ($websiteId > 0) {
    $followAccounts = $cms->getNote()->getAllowedFollowAccounts($websiteId, $targetMemberId, 1);
    if (!is_array($followAccounts)) {
        $followAccounts = [];
    }
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

// Deduplicate options by account_id
$tmp = [];
foreach ($accountOptions as $opt) {
    $tmp[(int) $opt['account_id']] = $opt;
}
$accountOptions = array_values($tmp);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountId = (int) ($_POST['account_id'] ?? 0);
    if ($accountId <= 0 || !isset($allowedAccountIds[$accountId])) {
        redirect('page-not-found/');
    }

    $update = $targetMember;
    $update['account_id'] = $accountId;

    $cms->getMember()->update($update);
    // If editing own record, keep session in sync
    if ($targetMemberId === (int) ($_SESSION['id'] ?? 0)) {
        $_SESSION['account_id'] = (int) $accountId;
        $_SESSION['follow_id'] = (int) $accountId; // your app uses follow_id similarly
    }

    redirect('admin/members/', ['success' => 'Family updated']);
}

// -------------------------
// GET: render
// -------------------------
$data = [];
$data['member'] = $targetMember;
$data['website'] = $cms->getWebsite()->getById($websiteId);

// Add a "self" row for the dropdown so current member always appears
$selfRow = [
    'to_family_id' => $targetMemberId,
    'to_name' => trim(($targetMember['forename'] ?? '') . ' ' . ($targetMember['surname'] ?? '')),
];

$data['account_options'] = $accountOptions;
$data['selected_account_id'] = (int) ($targetMember['account_id'] ?? $targetMemberId);

echo $twig->render('admin/edit-family.html', $data);
