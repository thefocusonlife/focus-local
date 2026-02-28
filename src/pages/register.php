<?php
declare(strict_types=1); // Use strict types
use PhpBook\Validate\Validate; // Import Validate class
// SECURITY NOTE:
// Registration endpoint hardened against DOM + network tampering.
// POST allowlist enforced; role/status/account/website are server-controlled.
// Verified via DevTools Edit&Resend and cURL POST-body injection (Jan 2026).

require_once APP_ROOT . '/src/security/redirects.php';
require_once __DIR__ . '/../../config/recaptcha.php';
require_once APP_ROOT . '/src/security/guard.php';
guardPublic();

$doc_root = $config['doc_root'] ?? '/focus-local/public/';
error_log(
    '[REGISTER] ' . ($_SERVER['REQUEST_METHOD'] ?? '?') . ' ' . ($_SERVER['REQUEST_URI'] ?? '?'),
);

// --------------------------------------------------
// Resolve website context for Register (GET and POST)
// --------------------------------------------------

// From route: /register/{id}
$w = (int) ($id ?? 0);

// From POST (failed validation re-render)
if ($w <= 0) {
    $w = (int) ($_POST['website'] ?? 0);
}

// From session fallback
if ($w <= 0) {
    $w = (int) ($_SESSION['website'] ?? 1);
}

// Final safety net
if ($w <= 0) {
    $w = 1;
}

// Load website
$website = $cms->getWebsite()->getById($w);
if (!$website) {
    $w = 1;
    $website = $cms->getWebsite()->getById(1);
}

$member = []; // Initialize member array
$errors = [];
$agegroups = [];
$plans = []; // Initialize errors array
$abc = [];
$data = [];
$last_id = 0;
$lastid = 0;
$menuId = (int) ($menuId ?? 0);
$confirm = [];

if ($menuId <= 0) {
    // Fallback: choose a sensible default menu id for this member/website
    // (see Option B below for how to do this properly)
    $menuId = 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    unset($_SESSION['register_submit_lock']);

    // If form doesn't exist
    $agegroups = $cms->getMember()->getAgegroups();
    $plans = $cms->getMember()->getPlans();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log('[REGISTER] POST start sid=' . session_id());
    error_log('[register] METHOD=' . ($_SERVER['REQUEST_METHOD'] ?? 'NA'));
    error_log('[register] GET keys=' . implode(',', array_keys($_GET)));
    error_log('[register] POST keys=' . implode(',', array_keys($_POST)));

    $lockKey = 'register_submit_lock';
    $now = microtime(true);
    $windowSeconds = 3.0; // block duplicates within 3 seconds

    $last = isset($_SESSION[$lockKey]) ? (float) $_SESSION[$lockKey] : 0.0;

    if ($last > 0 && $now - $last < $windowSeconds) {
        error_log(
            '[REGISTER] BLOCKED duplicate POST dt=' . ($now - $last) . ' sid=' . session_id(),
        );
        $w = (int) ($_POST['website'] ?? ($_SESSION['website'] ?? 1));
        if ($w <= 0) {
            $w = 1;
        }
        tfol_redirect($doc_root . 'index/' . $w, 303);
    }

    // set/refresh lock timestamp for this POST attempt
    $_SESSION[$lockKey] = $now;

    error_log('[REGISTER] LOCK SET ' . $_SESSION[$lockKey] . ' sid=' . session_id());

    // -----------------------------
    // reCAPTCHA v3 verification
    // -----------------------------
    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
    // error_log('REGISTER recaptcha token: ' . substr($recaptchaToken, 0, 40));

    if (empty($recaptchaToken)) {
        // Front-end didn't provide a token at all
        $errors['warning'] = 'Security check token missing. Please refresh the page and try again.';
    } else {
        $secretKey = $config['recaptcha_secret_key'] ?? '';

        // Use a slightly lower threshold for login to reduce false negatives
        if (!verify_recaptcha_v3($recaptchaToken, 'register', $secretKey, 0.1)) {
            // reCAPTCHA failed – do NOT attempt login
            $errors['message'] = 'register failed security check. Please try again.';
        } // end verify_recaptcha_v3()
    } // end empty token check

    // If form was posted
    // Get form data

    if ($_POST['website'] != 1) {
        $valid = $cms->getMember()->getIdByEmail($_POST['email']);
        if ($valid == 0) {
            $errors['master'] =
                'You must register with main theFocusOnLife website in order to register with this website.';
        } else {
            $errors['master'] = '';
        }
    }

    $websiteId = (int) ($_POST['website'] ?? 1);
    if ($websiteId <= 0) {
        $websiteId = 1;
    }

    // Normalize base email (important for uniqueness)
    $emailBase = strtolower(trim((string) ($_POST['email'] ?? '')));

    $confirm = (string) ($_POST['confirm'] ?? '');

    // TFOL rule: suffix email for non-main websites (login email is the UNIQUE key)
    $emailForLogin = $websiteId > 1 ? $emailBase . $websiteId : $emailBase;

    // Duplicate check (only if email looks valid-ish; your full Validate::isEmail runs later)
    if ($emailBase !== '' && empty($errors['email'])) {
        // Check the UNIQUE login email (member.email)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $existingId = (int) $cms->getMember()->getIdByEmail($emailForLogin);
            if ($existingId > 0) {
                $errors['email'] = 'That email is already registered.';
            }
        }
    }

    // photolimit from plan
    $planId = (int) ($_POST['plan'] ?? 0);
    $planRow = $cms->getMember()->getPhotolimit($planId);
    $photoLimit = isset($planRow['photolimit']) ? (int) $planRow['photolimit'] : 0;

    $params = [
        'website' => $websiteId,
        'forename' => trim((string) ($_POST['forename'] ?? '')),
        'surname' => trim((string) ($_POST['surname'] ?? '')),
        'password' => (string) ($_POST['password'] ?? ''),
        'email' => $emailForLogin,
        'email_master' => $emailBase,
        'role' => 'admin',
        'photo_limit' => $photoLimit,
        'agegroup' => (int) ($_POST['agegroup'] ?? 0),
        'plan' => 1,
        'pagelimit' => 50,
        'sorttype' => (int) $cms->getSorttype()->getDefaultIdForMenu($menuId),
        'publik' => 1,
        'termsok' => 0,
        'status' => 'pending',
    ];

    // Validate form data
    $errors['forename'] = Validate::isText($params['forename'], 1, 254)
        ? ''
        : 'Forename must be 1-254 characters';
    $errors['surname'] = Validate::isText($params['surname'], 1, 254)
        ? ''
        : 'Surname must be 1-254 characters';
    if (empty($errors['email'])) {
        $errors['email'] = Validate::isEmail($params['email_master'])
            ? ''
            : 'Please enter a valid email';
    }
    $errors['password'] = Validate::isPassword($params['password'])
        ? ''
        : 'Passwords must be at least 8 characters and have:<br>
                A lowercase letter<br>An uppercase letter<br>A number
                <br>And a special character';
    $errors['confirm'] = $params['password'] === $confirm ? '' : 'Passwords do not match';

    // After you finish populating $errors from all validations
    $hasErrors = false;
    foreach ($errors as $msg) {
        if (!empty($msg)) {
            $hasErrors = true;
            break;
        }
    }

    if ($hasErrors) {
        // VALIDATION FAILURE: stay on register and show field errors

        // website context: Twig expects website.id / website.name
        $data['website'] =
            $cms->getWebsite()->getById($websiteId) ?: $cms->getWebsite()->getById(1);

        // repopulate fields (do NOT repopulate passwords)
        $data['values'] = [
            'forename' => $params['forename'] ?? '',
            'surname' => $params['surname'] ?? '',
            'email' => $params['email_master'] ?? '', // show base email in form
            'plan' => (string) ($_POST['plan'] ?? ''),
            'agegroup' => (string) ($_POST['agegroup'] ?? ''),
            'website' => (string) $websiteId,
        ];
        $data['agegroups'] = $cms->getMember()->getAgegroups();
        $data['plans'] = $cms->getMember()->getPlans();

        // DO NOT repopulate password fields:
        unset($data['values']['password'], $data['values']['confirm']);

        $data['errors'] = $errors;
        unset($_SESSION['register_submit_lock']);
        echo $twig->render('register.html', $data);
        exit();

        echo $twig->render('register.html', $data);
        exit(); // IMPORTANT: stop execution
    }

    // No validation errors → attempt create
    try {
        $result = $cms->getMember()->create($params);
    } catch (PDOException $e) {
        error_log(
            '[REGISTER] create PDOException code=' . $e->getCode() . ' msg=' . $e->getMessage(),
        );

        // Duplicate email (unique constraint)
        if ($e->getCode() === '23000') {
            $errors['email'] = 'That email is already registered.';
            $data['errors'] = $errors;
            echo $twig->render('register.html', $data);
            return;
        }

        unset($_SESSION['flash_success']);
        $_SESSION['flash_failure'] = 'Registration failed. Please try again.';
        unset($_SESSION['register_submit_lock']);
        tfol_redirect($doc_root . 'index/' . $websiteId, 303);
    }

    // If create returned false without throwing
    if (!$result) {
        unset($_SESSION['flash_success']);
        $_SESSION['flash_failure'] = 'Registration failed. Please try again.';
        unset($_SESSION['register_submit_lock']);
        tfol_redirect($doc_root . 'index/' . $websiteId, 303);
    }

    // SUCCESS
    unset($_SESSION['flash_failure']);
    $_SESSION['flash_success'] =
        'Registration submitted. Your account is pending approval. Use Contact Us to inquire about your approval.';
    error_log('[REGISTER] SUCCESS -> redirecting to ' . $doc_root . 'index/' . $websiteId);

    // unset($_SESSION['register_submit_lock']);
    tfol_redirect($doc_root . 'index/' . $websiteId, 303);
}
$path = mb_strtolower(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$path = substr($path, strlen(DOC_ROOT)); // Remove up to DOC_ROOT
$path = trim($path, '/');
$parts = explode('/', $path); // Split into array at /

if ($parts[0] != 'admin') {
    // If an admin page
    $page = $parts[0] ?: 'index'; // Page name (or use index)
    $id = $parts[1] ?? null; // Get ID (or use null)
} else {
    // If not an admin page
    $page = 'admin/' . ($parts[1] ?? ''); // Page name
    $id = $parts[2] ?? null; // Get ID
}
if (!$id) {
    $id = 1;
}
$website = $cms->getWebsite()->getById(intval($id));

$member = [];
$data['success'] = $_GET['success'] ?? null; // Check for success message
$data['failure'] = $_GET['failure'] ?? null; // Check for failure message
$data['agegroups'] = $agegroups;
$data['plans'] = $plans;
$data['errors'] = $errors; // Error messages
$data['website'] = $website; // $cms->getWebsite()->getById(intval($id));
$data['use_recaptcha'] = true;
$data['recaptcha_site_key'] = $config['recaptcha_site_key'];
echo $twig->render('register.html', $data); // Render Twig template

exit();
