<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';
require_once APP_ROOT . '/src/security/csrf.php';

$websiteId = filter_input(INPUT_GET, 'website', FILTER_VALIDATE_INT);
$supportedWebsiteIds = [44, 51];

if (!is_int($websiteId) || !in_array($websiteId, $supportedWebsiteIds, true)) {
    $_SESSION['flash_failure'] = 'A valid club website is required.';
    redirect('websites');
    exit();
}

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

if ($viewerId <= 0 || $viewerId === 2 || $role === 'guest') {
    $_SESSION['return_to'] = '/club-members-admin?website=' . $websiteId;

    $_SESSION['flash_failure'] = 'You must be logged in to view club members.';

    redirect('login');
    exit();
}

$allowedMemberAdmins = [
    44 => [1, 3, 339, 500],
    51 => [1],
];

if (!in_array($viewerId, $allowedMemberAdmins[$websiteId], true)) {
    $_SESSION['flash_failure'] = 'You do not have permission to view club members.';

    redirect('index/' . $websiteId);
    exit();
}

$clubNames = [
    44 => 'Central Oregon Bicycle Community',
    51 => 'Central Oregon Chess',
];

$clubName = $clubNames[$websiteId];

$csrfFormKey = 'club_member_status_' . $websiteId;
$csrfToken = csrf_token($csrfFormKey);

$statusLabels = [
    'pending' => 'Pending',
    'active' => 'Active',
    'inactive' => 'Inactive',
    'declined' => 'Declined',
];

$membersStmt = $cms->getClubMembers()->getAll($websiteId);

$members = $membersStmt instanceof PDOStatement ? $membersStmt->fetchAll(PDO::FETCH_ASSOC) : [];

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<style>
.club-members-admin {
    max-width: 1200px;
    margin: 30px auto;
    padding: 24px;
    background: #fff;
}

.club-members-admin h1 {
    margin-top: 0;
}

.club-members-admin .admin-actions {
    margin-bottom: 18px;
}

.club-members-admin .admin-actions a {
    display: inline-block;
    padding: 8px 14px;
    background: #1f5d86;
    color: #fff;
    text-decoration: none;
    border-radius: 4px;
}

.club-members-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.club-members-table th,
.club-members-table td {
    border: 1px solid #ccc;
    padding: 8px 10px;
    vertical-align: top;
    text-align: left;
}

.club-members-table th {
    background: #eee;
}

.club-members-table tr:nth-child(even) {
    background: #f8f8f8;
}

.club-members-table .small {
    color: #666;
    font-size: 12px;
}

.club-members-admin .success-message {
    margin-bottom: 18px;
    padding: 10px 12px;
    color: #155724;
    background: #d4edda;
    border: 1px solid #c3e6cb;
}

.club-members-admin .failure-message {
    margin-bottom: 18px;
    padding: 10px 12px;
    color: #721c24;
    background: #f8d7da;
    border: 1px solid #f5c6cb;
}

@media print {
    .admin-actions {
        display: none;
    }
}
</style>

<div class="club-members-admin">
    <h1><?= h($clubName) ?> Membership Administration</h1>

    <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="success-message">
        <?= h($_SESSION['flash_success']) ?>
    </div>

    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_failure'])): ?>
    <div class="failure-message">
        <?= h($_SESSION['flash_failure']) ?>
    </div>

    <?php unset($_SESSION['flash_failure']); ?>
<?php endif; ?>

    <div class="admin-actions">
        <a href="<?= DOC_ROOT ?>membership?website=<?= $websiteId ?>">
            Open Membership Form
        </a>

        <a href="<?= DOC_ROOT ?>club-members-export?website=<?= $websiteId ?>">
            Download CSV
        </a>
    </div>

    <?php if (empty($members)): ?>
        <p>No club members found.</p>
    <?php else: ?>
        <table class="club-members-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Member</th>
                    <th>Status</th>
                    <th>Contact</th>
                    <th>Birth / Guardian</th>
                    <th>Emergency Contact</th>
                    <th>Authorization</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td>
                            <?= h($member['created_at'] ?? '') ?>
                        </td>

                        <td>
                            <strong>
                                <?= h($member['first_name'] ?? '') ?>
                                <?= h($member['last_name'] ?? '') ?>
                            </strong>

                            <br>

                            <span class="small">
                                Member ID:
                                <?= h($member['member_id'] ?? '') ?>
                            </span>
                        </td>

                        <td>
                            <?php $currentStatus =
                                (string) ($member['membership_status'] ?? 'pending'); ?>

                            <form
                                method="post"
                                action="<?= DOC_ROOT ?>club-members-status-save"
                                class="membership-status-form"
                            >
                                <input
                                    type="hidden"
                                    name="website_id"
                                    value="<?= $websiteId ?>"
                                >

                                <input
                                    type="hidden"
                                    name="membership_id"
                                    value="<?= h($member['id'] ?? '') ?>"
                                >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= h($csrfToken) ?>"
                                >

                                <select name="membership_status">
                                    <?php foreach (
                                        $statusLabels
                                        as $statusValue => $statusLabel
                                    ): ?>
                                        <option
                                            value="<?= h($statusValue) ?>"
                                            <?= $currentStatus === $statusValue ? 'selected' : '' ?>
                                        >
                                            <?= h($statusLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button type="submit">
                                    Update
                                </button>
                            </form>
                        </td>

                        <td>
                            <?= h($member['email'] ?? '') ?>

                            <?php if (!empty($member['phone'])): ?>
                                <br><?= h($member['phone']) ?>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if (!empty($member['date_of_birth'])): ?>
                                <strong>DOB:</strong>
                                <?= h($member['date_of_birth']) ?><br>
                            <?php endif; ?>

                            <?php if (!empty($member['guardian_name'])): ?>
                                <strong>Guardian:</strong>
                                <?= h($member['guardian_name']) ?><br>

                                <?= h($member['guardian_email'] ?? '') ?>

                                <?php if (!empty($member['guardian_phone'])): ?>
                                    <br><?= h($member['guardian_phone']) ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= h($member['emergency_name'] ?? '') ?>

                            <?php if (!empty($member['emergency_phone'])): ?>
                                <br><?= h($member['emergency_phone']) ?>
                            <?php endif; ?>

                            <?php if (!empty($member['emergency_relationship'])): ?>
                                <br>
                                <span class="small">
                                    <?= h($member['emergency_relationship']) ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if (!empty($member['parental_authorization_accepted'])): ?>
                                Accepted

                                <?php if (!empty($member['parental_authorization_accepted_at'])): ?>
                                    <br>
                                    <span class="small">
                                        <?= h($member['parental_authorization_accepted_at']) ?>
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                Not recorded
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
