<?php
declare(strict_types=1); // Use strict types
use PhpBook\Validate\Validate; // Import Validate class

error_log('[LOGIN] session_status=' . session_status() . ' session_id=' . session_id());

require_once __DIR__ . '/../../config/recaptcha.php';

// ----------------------------
// Resolve website context
// ----------------------------
$websiteId = (int) ($parts[1] ?? 0);
if ($websiteId <= 0) {
    $websiteId = (int) ($_SESSION['website'] ?? 1);
}
$_SESSION['website'] = $websiteId;

// Fetch website using the method that works in select-website.php
$website = $cms->getWebsite()->get($websiteId);

// Fail fast if invalid
if (empty($website) || empty($website['id'])) {
    error_log('[LOGIN REDIRECT] line=' . __LINE__ . ' to=' . $target);

    // Resolve website id from route, then session fallback
    $websiteId = (int) ($parts[1] ?? 0);
    if ($websiteId <= 0) {
        $websiteId = (int) ($_SESSION['website'] ?? 1);
    }
    if ($websiteId <= 0) {
        $websiteId = 1;
    }
    $_SESSION['website'] = $websiteId;

    // Fetch website using the method you know works (select-website.php uses get())
    $website = $cms->getWebsite()->get($websiteId);

    // Fail fast (once)
    if (empty($website) || empty($website['id'])) {
        redirect('index/1', ['failure' => 'Website not found.']);
        exit();
    }

    exit();
}

// Guest context for login page navigation menus
$mem = 0;

$role = $_SESSION['role'] ?? 'guest';

// Only redirect away from login if the user is truly logged in (non-guest role)
if ($role !== 'guest') {
    $sid = (int) ($_SESSION['id'] ?? 0);
    if ($sid > 0) {
        redirect('member/' . $sid);
        exit();
    }
}

// If form has not been submitted yet, load the website info
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    $website = $cms->getWebsite()->getById(intval($id));
}

$email = ''; // Initialize email variable
$errors = []; // Initialize errors
$success = $_GET['success'] ?? null; // Get success message

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $website_id = (int) ($_POST['website'] ?? 0);

    $member = null;
    $okToAttemptLogin = true;

    // -----------------------------
    // reCAPTCHA v3 verification
    // -----------------------------
    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';

    if (empty($recaptchaToken)) {
        $errors['warning'] = 'Security check token missing. Please refresh the page and try again.';
        $okToAttemptLogin = false;
    } else {
        $secretKey = $config['recaptcha_secret_key'] ?? '';
        if (!verify_recaptcha_v3($recaptchaToken, 'login', $secretKey, 0.1)) {
            $errors['message'] = 'Login failed security check. Please try again.';
            $okToAttemptLogin = false;
        }
    }

    // Validate email and password
    $errors['email'] = Validate::isEmail($email) ? '' : 'Please enter a valid email address';

    $errors['password'] = Validate::isPassword($password)
        ? ''
        : 'Passwords must be at least 8 characters and have:<br>
            A lowercase letter<br>An uppercase letter<br>A number<br>
            And a special character';

    if ($okToAttemptLogin) {
        $invalid = implode($errors);
        if ($invalid) {
            $errors['message'] = 'Please try again.';
        } else {
            $member = $cms->getMember()->login2($email, $password);
        }
    }

    // Only continue if we actually attempted login
    if ($okToAttemptLogin && $member) {
        if (($member['status'] ?? 'active') === 'suspended') {
            $errors['message'] = 'Account suspended';
        } elseif (($member['status'] ?? 'active') === 'pending') {
            $errors['message'] =
                'Membership pending. Use Contact Us to inquire about your registration.';
        } else {
            // -------- SUCCESS PATH --------
            $websiteId = (int) ($member['website'] ?? 0);

            if ($websiteId <= 0) {
                $errors['message'] = 'Login error: no website assigned to this account.';
            } else {
                $website = $cms->getWebsite()->getById($websiteId);

                if (empty($website) || empty($website['id'])) {
                    $errors['message'] = 'Website not found.';
                } else {
                    $cms->getSession()->create($member, (int) $website['id']);
                    redirect('member/' . (int) $member['id']);
                    exit();
                }
            }
        }
    } elseif ($okToAttemptLogin && empty($member) && empty($errors['message'])) {
        // Generic failure (avoid leaking whether email exists / belongs to site)
        $errors['message'] = 'Invalid email or password.';
        // If you still want your old message during dev:
        // $w = $cms->getWebsite()->getById($website_id);
        // $errors['message'] = 'This email not valid for ' . ($w['name'] ?? 'this website');
    }
}

// Website context for this page
$websiteId = (int) ($id ?? ($_SESSION['website'] ?? 1));
$website = $cms->getWebsite()->getById($websiteId);

if (empty($website) || empty($website['id'])) {
    redirect('index/1', ['failure' => 'Website not found.']);
    exit();
}

// Session/member context for navigation

$sessionId = (int) ($_SESSION['id'] ?? 0);

if ($sessionId <= 0) {
    $member = 0;
    $mem = 1;
} else {
    $member = $cms->getMember()->get($sessionId);
    if (!$member) {
        // reset session state here if you can
        $member = 0;
        $mem = 1;
    } else {
        $mem = (int) $member['account_id'];
    }
}

$data['navigation'] = $cms->getMenu()->getAll2((int) $website['id'], (int) $mem);
$data['success'] = $success;
$data['email'] = $email;
$data['errors'] = $errors;
$data['recaptcha_site_key'] = $config['recaptcha_site_key'] ?? '';
$data['website'] = $website;

echo $twig->render('login.html', $data);
