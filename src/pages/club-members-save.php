<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';

//guardMember();

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('bicycle-community?website=44');
    exit();
}

$websiteId = (int) ($_POST['website_id'] ?? ($_GET['website'] ?? ($_SESSION['website'] ?? 1)));

if ($websiteId !== 44) {
    $websiteId = 44;
}

$required = [
    'first_name' => 'First name is required.',
    'last_name' => 'Last name is required.',
    'email' => 'Email is required.',
    'phone' => 'Phone is required.',
    'emergency_name' => 'Emergency contact name is required.',
    'emergency_phone' => 'Emergency contact phone is required.',
];

foreach ($required as $field => $message) {
    if (trim((string) ($_POST[$field] ?? '')) === '') {
        $_SESSION['flash_failure'] = $message;
        redirect('membership/44');
        exit();
    }
}

$email = trim((string) ($_POST['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_failure'] = 'Please enter a valid email address.';
    redirect('membership/44');
    exit();
}

if (empty($_POST['liability_release_accepted'])) {
    $_SESSION['flash_failure'] = 'You must accept the liability release.';
    redirect('membership/44');
    exit();
}

$data = [
    'website_id' => $websiteId,
    'first_name' => trim((string) ($_POST['first_name'] ?? '')),
    'last_name' => trim((string) ($_POST['last_name'] ?? '')),
    'address1' => trim((string) ($_POST['address1'] ?? '')),
    'address2' => trim((string) ($_POST['address2'] ?? '')),
    'city' => trim((string) ($_POST['city'] ?? '')),
    'state' => trim((string) ($_POST['state'] ?? '')),
    'zip' => trim((string) ($_POST['zip'] ?? '')),
    'email' => $email,
    'phone' => trim((string) ($_POST['phone'] ?? '')),
    'emergency_name' => trim((string) ($_POST['emergency_name'] ?? '')),
    'emergency_phone' => trim((string) ($_POST['emergency_phone'] ?? '')),
    'emergency_relationship' => trim((string) ($_POST['emergency_relationship'] ?? '')),
    'primary_ride_type' => trim((string) ($_POST['primary_ride_type'] ?? '')),
    'other_ride_type' => trim((string) ($_POST['other_ride_type'] ?? '')),
    'riding_level' => trim((string) ($_POST['riding_level'] ?? '')),
    'preferred_distance' => trim((string) ($_POST['preferred_distance'] ?? '')),
    'medical_notes' => trim((string) ($_POST['medical_notes'] ?? '')),
    'liability_release_accepted' => 1,
];

try {
    $cms->getClubMembers()->create($data);

    $_SESSION['flash_success'] = 'Membership form submitted successfully.';
    redirect('membership/44?success=1');
    exit();
} catch (Throwable $e) {
    error_log('[club-members-save] ' . $e->getMessage());

    $_SESSION['flash_failure'] = 'Unable to save membership form. Please try again.';
    redirect('membership/44');
    exit();
}
