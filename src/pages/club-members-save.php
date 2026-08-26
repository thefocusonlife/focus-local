<?php

declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('websites');
    exit();
}

$websiteId = filter_input(INPUT_POST, 'website_id', FILTER_VALIDATE_INT);

if (!$websiteId || $websiteId < 1) {
    $_SESSION['flash_failure'] = 'A valid club website is required.';
    redirect('websites');
    exit();
}

$membershipUrl = 'membership?website=' . $websiteId;
$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? 'guest'));

$isGuest = $viewerId < 1 || $viewerId === 2 || $role === 'guest';

if ($isGuest) {
    $_SESSION['return_to'] = DOC_ROOT . 'membership?website=' . $websiteId;

    $_SESSION['flash_failure'] = 'Please register or log in before applying for club membership.';

    redirect('register/' . $websiteId);
    exit();
}

$viewer = $cms->getMember()->get($viewerId);
$viewerWebsiteId = (int) ($viewer['website'] ?? 0);
$isUberAdmin = $viewerId === 1;

if (!$viewer || (!$isUberAdmin && $viewerWebsiteId !== $websiteId)) {
    $_SESSION['flash_failure'] = 'This account does not belong to the selected club website.';

    $safeWebsiteId = $viewerWebsiteId > 0 ? $viewerWebsiteId : 1;

    redirect('index/' . $safeWebsiteId);
    exit();
}

$required = [
    'first_name' => 'First name is required.',
    'last_name' => 'Last name is required.',
    'email' => 'Email is required.',
    'date_of_birth' => 'Date of birth is required.',
];

foreach ($required as $field => $message) {
    if (trim((string) ($_POST[$field] ?? '')) === '') {
        $_SESSION['flash_failure'] = $message;
        redirect($membershipUrl);
        exit();
    }
}

$email = trim((string) ($_POST['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_failure'] = 'Please enter a valid email address.';

    redirect($membershipUrl);
    exit();
}

$dateOfBirth = trim((string) ($_POST['date_of_birth'] ?? ''));

$birthDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateOfBirth);

$dateErrors = \DateTimeImmutable::getLastErrors();

$dateIsInvalid =
    $birthDate === false ||
    (is_array($dateErrors) && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0));

if ($dateIsInvalid) {
    $_SESSION['flash_failure'] = 'Please enter a valid date of birth.';

    redirect($membershipUrl);
    exit();
}

$today = new \DateTimeImmutable('today');

if ($birthDate > $today) {
    $_SESSION['flash_failure'] = 'Date of birth cannot be in the future.';

    redirect($membershipUrl);
    exit();
}

$adultCutoff = $today->modify('-18 years');
$isMinor = $birthDate > $adultCutoff;

$guardianName = trim((string) ($_POST['guardian_name'] ?? ''));

$guardianEmail = trim((string) ($_POST['guardian_email'] ?? ''));

$guardianPhone = trim((string) ($_POST['guardian_phone'] ?? ''));

$emergencyName = trim((string) ($_POST['emergency_name'] ?? ''));

$emergencyPhone = trim((string) ($_POST['emergency_phone'] ?? ''));

$parentalAuthorizationAccepted = !empty($_POST['parental_authorization_accepted']);

if ($isMinor) {
    $minorRequired = [
        [
            'value' => $guardianName,
            'message' => 'Parent or guardian name is required.',
        ],
        [
            'value' => $guardianEmail,
            'message' => 'Parent or guardian email is required.',
        ],
        [
            'value' => $guardianPhone,
            'message' => 'Parent or guardian phone is required.',
        ],
        [
            'value' => $emergencyName,
            'message' => 'Emergency contact name is required.',
        ],
        [
            'value' => $emergencyPhone,
            'message' => 'Emergency contact phone is required.',
        ],
    ];

    foreach ($minorRequired as $requiredField) {
        if ($requiredField['value'] === '') {
            $_SESSION['flash_failure'] = $requiredField['message'];

            redirect($membershipUrl);
            exit();
        }
    }
    if (!filter_var($guardianEmail, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_failure'] = 'Please enter a valid parent or guardian email address.';

        redirect($membershipUrl);
        exit();
    }

    if (!$parentalAuthorizationAccepted) {
        $_SESSION['flash_failure'] = 'A parent or guardian must authorize membership for a minor.';

        redirect($membershipUrl);
        exit();
    }
}

$data = [
    'website_id' => $websiteId,
    'member_id' => $viewerId,
    'first_name' => trim((string) ($_POST['first_name'] ?? '')),
    'last_name' => trim((string) ($_POST['last_name'] ?? '')),
    'email' => $email,
    'phone' => trim((string) ($_POST['phone'] ?? '')),
    'date_of_birth' => $dateOfBirth,
    'guardian_name' => $isMinor ? $guardianName : null,
    'guardian_email' => $isMinor ? $guardianEmail : null,
    'guardian_phone' => $isMinor ? $guardianPhone : null,
    'emergency_name' => $emergencyName,
    'emergency_phone' => $emergencyPhone,
    'emergency_relationship' => trim((string) ($_POST['emergency_relationship'] ?? '')),
    'parental_authorization_accepted' => $isMinor && $parentalAuthorizationAccepted ? 1 : 0,
    'membership_status' => 'pending',
];

try {
    $existingMembership = $cms->getClubMembers()->getByMemberId($viewerId, $websiteId);

    if ($existingMembership) {
        $cms->getClubMembers()->updateByMemberId($viewerId, $websiteId, $data);

        $_SESSION['flash_success'] = 'Your membership application was updated successfully.';
    } else {
        $cms->getClubMembers()->create($data);

        $_SESSION['flash_success'] = 'Your membership application was submitted successfully.';
    }

    redirect($membershipUrl . '&success=1');
    exit();
} catch (\Throwable $e) {
    error_log('[club-members-save] ' . $e->getMessage());

    $_SESSION['flash_failure'] = 'Unable to save your membership application. Please try again.';

    redirect($membershipUrl);
    exit();
}
