<?php
declare(strict_types=1); // Use strict types
use PhpBook\Validate\Validate; // Import Validate class

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
    // If form submitted
    $email = $_POST['email']; // Get email address
    $password = $_POST['password']; // Get password
    $website_id = intval($_POST['website']);
    // -----------------------------
    // reCAPTCHA v3 verification
    // -----------------------------
    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
    // error_log('LOGIN recaptcha token: ' . substr($recaptchaToken, 0, 40));

    if (empty($recaptchaToken)) {
        // Front-end didn't provide a token at all
        $errors['warning'] = 'Security check token missing. Please refresh the page and try again.';
    } else {
        $secretKey = $config['recaptcha_secret_key'] ?? '';

        // Use a slightly lower threshold for login to reduce false negatives
        if (!verify_recaptcha_v3($recaptchaToken, 'login', $secretKey, 0.1)) {
            // reCAPTCHA failed – do NOT attempt login
            $errors['message'] = 'Login failed security check. Please try again.';
        } // end verify_recaptcha_v3()
    } // end empty token check

    // Validate email and password
    $errors['email'] = Validate::isEmail($email) ? '' : 'Please enter a valid email address';

    $errors['password'] = Validate::isPassword($password)
        ? ''
        : 'Passwords must be at least 8 characters and have:<br>
                A lowercase letter<br>An uppercase letter<br>A number
                <br>And a special character';

    $invalid = implode($errors);

    if ($invalid) {
        // If data is not valid
        $errors['message'] = 'Please try again.'; // Store error message
    } else {
        $member = $cms->getMember()->login2($email, $password); // Get member details

        if (empty($member)) {
            $w = $cms->getWebsite()->getById($website_id);
            $errors['message'] = 'This email not valid for ' . $w['name'];
        } elseif ($member && ($member['status'] ?? 'active') === 'suspended') {
            $errors['message'] = 'Account suspended';
        } elseif ($member && ($member['status'] ?? 'active') === 'pending') {
            $errors['message'] =
                'Membership pending. Use Contact Us to inquire about your registration.';
        } elseif ($member) {
            // Get website for this member (or fallback to 1)
            $websiteId = isset($member['website'])
                ? (int) $member['website']
                : (int) ($_SESSION['website'] ?? 1);

            $website = $cms->getWebsite()->getById($websiteId);

            if (empty($website) || empty($website['id'])) {
                $errors['message'] = 'Website not found.';
            } else {
                // ✅ SUCCESS: create session and redirect to member home
                $cms->getSession()->create($member, (int) $website['id']);
                redirect('member/' . (int) $member['id']);
                exit();
            }
        }
    }
}

// Website context for this page
$$websiteId = (int) ($id ?? ($_SESSION['website'] ?? 1));
$website = $cms->getWebsite()->getById($websiteId);

if (empty($website) || empty($website['id'])) {
    redirect('index/1', ['failure' => 'Website not found.']);
    exit();
}

// Session/member context for navigation
$sessionId = (int) ($_SESSION['id'] ?? 0);

if ($sessionId === 2 || $sessionId === 0) {
    // Guest-ish: no member row
    $member = 0;
    $mem = 1; // safest default account_id for menus; adjust if your public menus use a different account
} else {
    $member = $cms->getMember()->get($sessionId);
    if (!$member) {
        // session is stale; treat as guest
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
