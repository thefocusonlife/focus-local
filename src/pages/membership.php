<?php

declare(strict_types=1);

$websiteId = filter_input(INPUT_GET, 'website', FILTER_VALIDATE_INT);

if (!$websiteId || $websiteId < 1) {
    redirect('websites');
    exit();
}

$websiteIdHtml = htmlspecialchars((string) $websiteId, ENT_QUOTES, 'UTF-8');

$viewerId = (int) ($_SESSION['id'] ?? 0);

$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

$isGuest = $viewerId < 1 || $viewerId === 2 || $role === 'guest';

if ($isGuest) {
    $_SESSION['membership_application_website'] = $websiteId;

    redirect('register/' . $websiteId);
    exit();
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
    padding-bottom: 5px;
    border-bottom: 1px solid #ddd;
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

.membership-form-wrap input[type="checkbox"] {
    width: auto;
    margin-right: 8px;
}

.membership-form-wrap .field-note {
    max-width: 650px;
    margin-top: 4px;
    color: #555;
    font-size: 0.95rem;
}

.membership-form-wrap .guardian-section {
    margin-top: 25px;
    padding: 18px;
    background: #faf7ef;
    border: 1px solid #d8cda9;
}

.membership-form-wrap button {
    margin-top: 20px;
    padding: 8px 14px;
    cursor: pointer;
}

.membership-form-wrap .error {
    color: #8b0000;
}

.membership-form-wrap .success {
    color: #176b2c;
}
</style>

<div class="membership-form-wrap">
    <h1>Club Membership Application</h1>

    <p>
        Complete this form to apply for club membership.
        New applications are reviewed before becoming active.
    </p>

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

    <form
        method="post"
        action="<?= DOC_ROOT ?>club-members-save"
    >
        <input
            type="hidden"
            name="website_id"
            value="<?= $websiteIdHtml ?>"
        >

        <h2>Member Information</h2>

        <label for="first_name">First Name *</label>
        <input
            id="first_name"
            type="text"
            name="first_name"
            maxlength="100"
            required
        >

        <label for="last_name">Last Name *</label>
        <input
            id="last_name"
            type="text"
            name="last_name"
            maxlength="100"
            required
        >

        <label for="email">Email *</label>
        <input
            id="email"
            type="email"
            name="email"
            maxlength="255"
            required
        >

        <label for="phone">Phone</label>
        <input
            id="phone"
            type="tel"
            name="phone"
            maxlength="30"
        >

        <label for="date_of_birth">Date of Birth *</label>
        <input
            id="date_of_birth"
            type="date"
            name="date_of_birth"
            max="<?= date('Y-m-d') ?>"
            required
        >

        <p class="field-note">
            Date of birth is used to determine whether parental
            authorization is required.
        </p>

        <div class="guardian-section">
            <h2>Members Under 18</h2>

            <p>
                These fields and parental authorization are required
                only when the applicant is under 18.
            </p>

            <label for="guardian_name">
                Parent or Guardian Name
            </label>
            <input
                id="guardian_name"
                type="text"
                name="guardian_name"
                maxlength="200"
            >

            <label for="guardian_email">
                Parent or Guardian Email
            </label>
            <input
                id="guardian_email"
                type="email"
                name="guardian_email"
                maxlength="255"
            >

            <label for="guardian_phone">
                Parent or Guardian Phone
            </label>
            <input
                id="guardian_phone"
                type="tel"
                name="guardian_phone"
                maxlength="30"
            >

            <h2>Emergency Contact</h2>

            <p class="field-note">
                Required for members under 18 and optional for adults.
            </p>

            <label for="emergency_name">
                Emergency Contact Name
            </label>
            <input
                id="emergency_name"
                type="text"
                name="emergency_name"
                maxlength="200"
            >

            <label for="emergency_phone">
                Emergency Contact Phone
            </label>
            <input
                id="emergency_phone"
                type="tel"
                name="emergency_phone"
                maxlength="30"
            >

            <label for="emergency_relationship">
                Relationship
            </label>
            <input
                id="emergency_relationship"
                type="text"
                name="emergency_relationship"
                maxlength="100"
            >

            <label for="parental_authorization_accepted">
                <input
                    id="parental_authorization_accepted"
                    type="checkbox"
                    name="parental_authorization_accepted"
                    value="1"
                >
                I am the applicant’s parent or legal guardian, and I
                authorize the applicant to participate in club
                activities.
            </label>
        </div>

        <button type="submit">
            Submit Membership Application
        </button>

        <button type="button" onclick="window.print()">
            Print
        </button>
    </form>
</div>
