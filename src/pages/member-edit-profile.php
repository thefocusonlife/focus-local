<?php
declare(strict_types=1); // Use strict types
use PhpBook\Validate\Validate; // Use Validate class

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/src/security/guard.php';
include APP_ROOT . '/src/pages/menu-path.php';
guardMember();
// Must be logged in
$sessionId = (int) ($_SESSION['id'] ?? 0);
if ($sessionId <= 0) {
    redirect('login/', ['failure' => 'Please log in']);
}

// Target member id from route
$targetId = (int) ($id ?? 0);
if ($targetId <= 0) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

// Authorization: owner OR Uber
if ($sessionId !== 1 && $sessionId !== $targetId) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

$errors = [];

//if ($_SERVER['REQUEST_METHOD'] != 'POST') {
// If form not posted
$member = $cms->getMember()->get($targetId); //  Get member details
$agegroups = $cms->getMember()->getAgegroups();
$plans = $cms->getMember()->getPlans();
//}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // If form was posted
    // Load current member so we can preserve fields not present on the form
    $current = $cms->getMember()->get($targetId);
    if (!$current || !isset($current['id'])) {
        // handle not found
        $errors['message'] = 'Member not found.';
    } else {
        // Build whitelisted params (POST overrides, DB supplies defaults)
        $params = [
            'id' => $targetId,

            // From the profile form
            'forename' => trim((string) ($_POST['forename'] ?? ($current['forename'] ?? ''))),
            'surname' => trim((string) ($_POST['surname'] ?? ($current['surname'] ?? ''))),

            // Email is NOT edited on this form; preserve current value
            'email' => trim((string) ($current['email'] ?? '')),

            // Checkboxes often don’t post when unchecked; normalize to 0/1
            'publik' => isset($_POST['publik']) ? 1 : 0,
            'public_ride_leaderboard' => isset($_POST['public_ride_leaderboard']) ? 1 : 0,
            'termsok' => isset($_POST['termsok']) ? 1 : (int) ($current['termsok'] ?? 0),

            // Preserve non-form fields from DB
            'account_id' => (int) ($current['account_id'] ?? 0),
            'photo_limit' => (int) ($current['photo_limit'] ?? 0),
            'agegroup' => (int) ($current['agegroup'] ?? 0),
            'plan' => (int) ($current['plan'] ?? 0),
        ];

        // (Optional) basic validation examples
        if ($params['forename'] === '' || $params['surname'] === '') {
            $errors['message'] = 'Forename and surname are required.';
        } elseif ($params['email'] === '' || !filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['message'] = 'Stored email address is invalid.';
        }

        if (empty($errors)) {
            $ok = $cms->getMember()->update($params);
            if (!$ok) {
                $errors['message'] = 'Update failed (email may already be in use).';
            } else {
                // If you keep member info in session, refresh it here so UI updates immediately
                $_SESSION['member']['forename'] = $params['forename'];
                $_SESSION['member']['surname'] = $params['surname'];
                $_SESSION['member']['email'] = $params['email'];
                $_SESSION['member']['publik'] = $params['publik'];

                $cms->redirect('admin/members/', ['success' => 'Profile updated']);

                exit();
            }
        }
    }
}
$data['navigation'] = $cms->getMenu()->getAll2($member['website'], $member['account_id']); // All menus for navigation
$data['member'] = $member; // Member data
$data['agegroups'] = $agegroups;
$data['plans'] = $plans;
$data['errors'] = $errors; // Error messages
$data['website'] = $cms->getWebsite()->get($member['website']);

echo $twig->render('member-edit-profile.html', $data); // Render Twig template
