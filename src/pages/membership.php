<?php
declare(strict_types=1);

$websiteId = (int) ($_GET['website'] ?? 44);
if ($websiteId !== 44) {
    $websiteId = 44;
}
?>

<style>
.membership-form-wrap {
    max-width: 900px;
    margin: 30px auto;
    padding: 24px;
    background: #fff;
    border: 1px solid #ccc;
}

.membership-form-wrap h1 {
    margin-top: 0;
}

.membership-form-wrap h2 {
    margin-top: 25px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 5px;
}

.membership-form-wrap label {
    display: block;
    margin-top: 12px;
    font-weight: bold;
}

.membership-form-wrap input,
.membership-form-wrap select,
.membership-form-wrap textarea {
    width: 100%;
    max-width: 500px;
    padding: 6px;
    margin-top: 4px;
}

.membership-form-wrap .form-row {
    display: flex;
    gap: 20px;
}

.membership-form-wrap .form-row > div {
    flex: 1;
}

.membership-form-wrap button {
    margin-top: 20px;
    padding: 8px 14px;
    cursor: pointer;
}
</style>

<div class="membership-form-wrap">

<h1>Membership Form</h1>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <p class="success">
        <?= htmlspecialchars((string) $_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') ?>
    </p>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_failure'])): ?>
    <p class="error">
        <?= htmlspecialchars((string) $_SESSION['flash_failure'], ENT_QUOTES, 'UTF-8') ?>
    </p>
    <?php unset($_SESSION['flash_failure']); ?>
<?php endif; ?>

<form method="post" action="<?= DOC_ROOT ?>club-members-save">
    <input type="hidden" name="website_id" value="<?= $websiteId ?>">
<div class="membership-form-wrap">
    <h2>Member Information</h2>

    <label>First Name *</label>
    <input type="text" name="first_name" required>

    <label>Last Name *</label>
    <input type="text" name="last_name" required>

    <label>Address</label>
    <input type="text" name="address1">

    <label>Address 2</label>
    <input type="text" name="address2">

    <label>City</label>
    <input type="text" name="city">

    <label>State</label>
    <input type="text" name="state">

    <label>Zip</label>
    <input type="text" name="zip">

    <label>Email *</label>
    <input type="email" name="email" required>

    <label>Phone *</label>
    <input type="text" name="phone" required>

    <h2>Emergency Contact</h2>

    <label>Emergency Contact Name *</label>
    <input type="text" name="emergency_name" required>

    <label>Emergency Contact Phone *</label>
    <input type="text" name="emergency_phone" required>

    <label>Relationship</label>
    <input type="text" name="emergency_relationship">

    <h2>Riding Information</h2>

    <label>Primary Ride Type</label>
    <select name="primary_ride_type">
        <option value="">Select one</option>
        <option value="road">Road</option>
        <option value="mountain bike">Mountain Bike</option>
        <option value="gravel">Gravel</option>
        <option value="touring">Touring</option>
        <option value="commuting">Commuting</option>
        <option value="e-bike">E-bike</option>
        <option value="other">Other</option>
    </select>

    <label>Other Ride Type</label>
    <input type="text" name="other_ride_type">

    <label>Riding Level</label>
    <select name="riding_level">
        <option value="">Select one</option>
        <option value="beginner">Beginner</option>
        <option value="intermediate">Intermediate</option>
        <option value="advanced">Advanced</option>
    </select>

    <label>Preferred Distance</label>
    <input type="text" name="preferred_distance" placeholder="Example: 10-25 miles">

    <label>Medical Notes</label>
    <textarea name="medical_notes"></textarea>

    <h2>Liability Release</h2>

    <p>
        I understand that bicycling involves risk of injury, accident, or death.
        I voluntarily participate in club rides and activities at my own risk.
        I agree to ride responsibly, follow traffic laws, wear a helmet, and release
        the Central Oregon Bicycle Community, organizers, ride leaders, volunteers, and
        associated website operators from liability to the fullest extent allowed by law.
    </p>

    <label>
        <input type="checkbox" name="liability_release_accepted" value="1" required>
        I accept the liability release *
    </label>

    <br><br>

    <button type="submit">Submit Membership Form</button>
    <button type="button" onclick="window.print()">Print</button>
    </div>
</form>
