<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/security/guard.php';
guardAdmin();
is_admin($session->role);

$data = [];
$data['success'] = $_GET['success'] ?? null;
$data['failure'] = $_GET['failure'] ?? null;

$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

// Website scope from session
$sessionWebsiteId = (int) ($_SESSION['website'] ?? 1);
if ($sessionWebsiteId <= 0) {
    $sessionWebsiteId = 1;
    $_SESSION['website'] = 1;
}

$sessionMemberId = (int) ($_SESSION['id'] ?? 0);
$isUber = $sessionMemberId === 1; // your current uber rule

// GET: show form (you likely load target member by route or query)
if (!$isPost) {
    // Your existing GET code here to fetch and display the member/role form
    ($targetId = (int) ($parts[2] ?? 0)) or $_GET['id'];
    $data['member'] = $cms->getMember()->get($targetId);
    $data['website'] = $cms->getWebsite()->getById($_SESSION['website']);
    echo $twig->render('admin/edit-role.html', $data);
    exit();
}

// ---------------------------
// POST: update role
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1) Parse inputs safely
    $role = trim((string) ($_POST['role'] ?? ''));

    // Your form uses ID (uppercase). Keep that for now but normalize.
    // $targetId = (int) ($_POST['id'] ?? 0);
    $targetId = (int) ($parts[2] ?? 0);

    if ($targetId <= 0) {
        redirect('admin/edit-role/' . $targetId, ['failure' => 'Invalid member id.']);
        exit();
    }

    // 2) Load target member from DB (never trust POST for website/account)
    $target = $cms->getMember()->get($targetId);
    if (!$target || empty($target['id'])) {
        redirect('admin/edit-role/' . $targetId, ['failure' => 'Member not found.']);
        exit();
    }

    // 3) Website scope check (unless uber)
    $targetWebsiteId = (int) ($target['website'] ?? 0);
    if (!$isUber && $targetWebsiteId !== $sessionWebsiteId) {
        redirect('admin/edit-role/' . $targetId, ['failure' => 'Out-of-scope member.']);
        exit();
    }

    // 4) Protect special cases
    if (!$isUber && $targetId === 1) {
        redirect('admin/edit-role/' . $targetId, ['failure' => 'Protected account.']);
        exit();
    }
    if ($targetId === $sessionMemberId) {
        redirect('admin/edit-role/' . $targetId, ['failure' => 'You cannot change your own role.']);

        exit();
    }

    // 5) Validate role allowlist (do NOT allow uber assignment here)
    $allowedRoles = ['member', 'family', 'admin', 'pending', 'suspended'];
    if (!in_array($role, $allowedRoles, true)) {
        redirect('admin/edit-role/' . $targetId, ['failure' => 'Invalid role.']);
        exit();
    }

    // 6) Allowlist update payload (prevents joined/HY093 and mass assignment)
    $update = [
        'id' => $targetId,
        'role' => $role,
    ];

    // 7) Update
    $cms->getMember()->updateRole($targetId, $role);
}
redirect('admin/members', ['success' => 'Role updated.']);
exit();
