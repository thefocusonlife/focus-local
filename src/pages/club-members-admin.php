<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? ''));

$allowedMemberAdmins = [1, 3, 339]; // user IDs allowed to view club members

if (!in_array($viewerId, $allowedMemberAdmins, true)) {
    $_SESSION['flash_failure'] = 'You do not have permission to view club members.';
    redirect('index');
    exit();
}

if ($viewerId <= 0 || $role === 'guest') {
    $_SESSION['return_to'] = '/club-members-admin?website=44';
    $_SESSION['flash_failure'] = 'You must be logged in to view club members.';
    redirect('login');
    exit();
}

$websiteId = (int) ($_GET['website'] ?? 44);
if ($websiteId !== 44) {
    $websiteId = 44;
}

$members = $cms->getClubMembers()->getAll($websiteId);

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

@media print {
    .admin-actions {
        display: none;
    }
}
</style>

<div class="club-members-admin">
    <h1>Club Members</h1>

   <div class="admin-actions">
    <a href="<?= DOC_ROOT ?>membership?website=44">Open Membership Form</a>
    <a href="<?= DOC_ROOT ?>club-members-export?website=44">Download CSV</a>
</div>

    <?php if (empty($members)): ?>
        <p>No club members found.</p>
    <?php else: ?>
        <table class="club-members-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Member</th>
                    <th>Contact</th>
                    <th>Address</th>
                    <th>Ride Info</th>
                    <th>Emergency Contact</th>
                    <th>Release</th>
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
                        </td>

                        <td>
                            <?= h($member['email'] ?? '') ?><br>
                            <?= h($member['phone'] ?? '') ?>
                        </td>

                        <td>
                            <?= h($member['address1'] ?? '') ?><br>

                            <?php if (!empty($member['address2'])): ?>
                                <?= h($member['address2']) ?><br>
                            <?php endif; ?>

                            <?= h($member['city'] ?? '') ?>
                            <?= h($member['state'] ?? '') ?>
                            <?= h($member['zip'] ?? '') ?>
                        </td>

                        <td>
                            <strong>Type:</strong> <?= h($member['primary_ride_type'] ?? '') ?><br>

                            <?php if (!empty($member['other_ride_type'])): ?>
                                <strong>Other:</strong> <?= h($member['other_ride_type']) ?><br>
                            <?php endif; ?>

                            <strong>Level:</strong> <?= h($member['riding_level'] ?? '') ?><br>
                            <strong>Distance:</strong> <?= h($member['preferred_distance'] ?? '') ?>

                            <?php if (!empty($member['medical_notes'])): ?>
                                <br><span class="small">
                                    <strong>Medical:</strong> <?= h($member['medical_notes']) ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= h($member['emergency_name'] ?? '') ?><br>
                            <?= h($member['emergency_phone'] ?? '') ?><br>
                            <span class="small">
                                <?= h($member['emergency_relationship'] ?? '') ?>
                            </span>
                        </td>

                        <td>
                            <?php if (!empty($member['liability_release_accepted'])): ?>
                                Accepted<br>
                                <span class="small">
                                    <?= h($member['liability_release_accepted_at'] ?? '') ?>
                                </span>
                            <?php else: ?>
                                Not accepted
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
