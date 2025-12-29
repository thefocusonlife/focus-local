<?php
declare(strict_types=1); // Use strict types
use PhpBook\Validate\Validate; // Import Validate class

require_once __DIR__ . '/../../config/recaptcha.php';

// include APP_ROOT . '/src/pages/menu-path.php';        // get path for website and menus

// If user is already logged in, redirect them to their member page
if ($cms->getSession()->role !== 'public' && $cms->getSession()->role !== 'guest') {
    redirect('member/' . $cms->getSession()->id);
    exit();
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
        } elseif ($member && $member['role'] == 'suspended') {
            // If member is suspended
            $errors['message'] = 'Account suspended'; // Store message
        } /*
                elseif ($member['website'] != 1 AND $website_id == 1) {
                    $errors['message'] = "Must use a registered theFocusOnLife email.";
                }
                */ elseif (
            $member &&
            $member['role'] == 'pending'
        ) {
            // If member is pending
            $errors['message'] =
                'Membership pending. Use Contact Us to inquire about your registration.'; // Store message
        } elseif ($member) {
            // Get website for this member (or fallback to 1)
            $websiteId = isset($member['website'])
                ? (int) $member['website']
                : (int) ($_SESSION['website'] ?? 1);

            $website = $cms->getWebsite()->getById($websiteId);

            // Create session

            // Otherwise for members
            $cms->getSession()->create($member, $website['id']); // Create session
            redirect('member/' . $member['id']); // Redirect to their page
        } else {
            // Otherwise
            $errors['message'] = 'Please try again.'; // Store error message
        }
    } // end $invalid branch
} // end POST: if ($_SERVER['REQUEST_METHOD'] == 'POST')

// Website context for this page
$websiteId = (int) ($id ?? ($_SESSION['website'] ?? 1));
$website = $cms->getWebsite()->getById($websiteId);
if (!$website) {
    redirect('index/1', ['failure' => 'Website not found.']);
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

// ✅ DO NOT create session here. login.php GET should not mutate session.
// if ($member) {
//     $cms->getSession()->create($member, (int)$website['id']);
// }

$data['navigation'] = $cms->getMenu()->getAll2((int) $website['id'], (int) $mem);
$data['success'] = $success;
$data['email'] = $email;
$data['errors'] = $errors;
$data['recaptcha_site_key'] = $config['recaptcha_site_key'] ?? '';
$data['website'] = $website;

echo $twig->render('login.html', $data);
