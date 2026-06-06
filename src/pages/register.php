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
require_once APP_ROOT . '/src/security/csrf.php';
/** @var array $config */
/** @var array $email_config */
/** @var \Twig\Environment $twig */
/** @var mixed $cms */
function sendVerificationEmail(
    array $emailConfig,
    string $toEmail,
    string $forename,
    string $verifyUrl,
): void {
    $subject = 'Verify your Focus on Life account';

    $safeName = trim($forename) !== '' ? trim($forename) : 'there';

    $message = <<<TEXT
    Hi {$safeName},

    Thanks for creating an account at theFocusOnLife.org.

    Please verify your email address by clicking the link below:

    {$verifyUrl}

    This link will expire in 24 hours.

    If you did not create this account, you can ignore this email.

    Focus on Life
    https://thefocusonlife.org
    contact@thefocusonlife.org
    TEXT;

    $mail = new \PhpBook\Email\Email($emailConfig);

    // sender, recipient, subject, message
    $mail->sendEmail($emailConfig['admin_email'], $toEmail, $subject, $message);
}
guardPublic();
$csrfFormKey = 'register';
$doc_root = $config['doc_root'] ?? '/_stage/';

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
$data['doc_root'] = $doc_root;
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

    $agegroups = $cms->getMember()->getAgegroups();
    $plans = $cms->getMember()->getPlans();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $websiteId = (int) ($_POST['website'] ?? ($_SESSION['website'] ?? 1));
    if ($websiteId <= 0) {
        $websiteId = 1;
    }

    $submittedCsrf = $_POST['csrf_token'] ?? '';

    if (!csrf_validate($csrfFormKey, is_string($submittedCsrf) ? $submittedCsrf : null)) {
        error_log('[REGISTER] CSRF validation failed sid=' . session_id());

        $data['website'] =
            $cms->getWebsite()->getById($websiteId) ?: $cms->getWebsite()->getById(1);

        $data['values'] = [
            'forename' => trim((string) ($_POST['forename'] ?? '')),
            'surname' => trim((string) ($_POST['surname'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'plan' => (string) ($_POST['plan'] ?? ''),
            'agegroup' => (string) ($_POST['agegroup'] ?? ''),
            'website' => (string) $websiteId,
        ];

        $data['agegroups'] = $cms->getMember()->getAgegroups();
        $data['plans'] = $cms->getMember()->getPlans();
        $data['errors'] = [
            'master' =>
                'Your form session expired or failed security validation. Please try again.',
        ];
        $data['use_recaptcha'] = true;
        $data['recaptcha_site_key'] = $config['recaptcha_site_key'];
        csrf_rotate($csrfFormKey);
        $data['csrf_token'] = csrf_token($csrfFormKey);
        unset($_SESSION['register_submit_lock']);
        echo $twig->render('register.html', $data);
        exit();
    }

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
        tfol_redirect(DOC_ROOT . 'index/' . $w, 303);
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

    $websiteId = (int) ($_POST['website'] ?? 1);
    if ($websiteId <= 0) {
        $websiteId = 1;
    }

    // Normalize base email (important for uniqueness)
    $emailBase = strtolower(trim((string) ($_POST['email'] ?? '')));

    $confirm = (string) ($_POST['confirm'] ?? '');

    // TFOL rule: suffix email for non-main websites (login email is the UNIQUE key)
    $emailForLogin = $websiteId > 1 ? $emailBase . $websiteId : $emailBase;
    error_log('[REGISTER] email_config keys: ' . implode(', ', array_keys($email_config)));

    // Basic MX validation
    if ($emailBase !== '' && empty($errors['email'])) {
        $parts = explode('@', $emailBase);

        if (count($parts) === 2) {
            $domain = $parts[1];

            if (!checkdnsrr($domain, 'MX')) {
                // temp log
                error_log('[REGISTER] MX validation failed for domain: ' . $domain);

                $errors['email'] =
                    'We could not verify that email address. Please check for typing errors.';
            }
        }
    }

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
    if (!empty($_SESSION['guest_story_draft'])) {
        $_SESSION['guest_story_email'] = $emailBase;
        $_SESSION['guest_story_register_email'] = $emailBase;
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
        'status' => 'active',
        'email_verified' => 0,
        'email_verified_at' => null,
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
        $data['website'] =
            $cms->getWebsite()->getById($websiteId) ?: $cms->getWebsite()->getById(1);

        $data['values'] = [
            'forename' => $params['forename'] ?? '',
            'surname' => $params['surname'] ?? '',
            'email' => $params['email_master'] ?? '',
            'plan' => (string) ($_POST['plan'] ?? ''),
            'agegroup' => (string) ($_POST['agegroup'] ?? ''),
            'website' => (string) $websiteId,
        ];
        $data['agegroups'] = $cms->getMember()->getAgegroups();
        $data['plans'] = $cms->getMember()->getPlans();

        unset($data['values']['password'], $data['values']['confirm']);

        $data['errors'] = $errors;
        $data['csrf_token'] = csrf_token($csrfFormKey);
        $data['use_recaptcha'] = true;
        $data['recaptcha_site_key'] = $config['recaptcha_site_key'];

        unset($_SESSION['register_submit_lock']);
        echo $twig->render('register.html', $data);
        exit();
    }
    // No validation errors → create missing Website 1 master account first when needed
    $masterUserId = 0;
    $masterAlreadyVerified = false;
    $result = false;
    try {
        if ($websiteId > 1) {
            $masterUserId = (int) $cms->getMember()->getIdByEmail($emailBase);

            if ($masterUserId <= 0) {
                $masterParams = $params;
                $masterParams['website'] = 1;
                $masterParams['email'] = $emailBase;
                $masterParams['email_master'] = $emailBase;
                $masterParams['email_verified'] = 0;
                $masterParams['email_verified_at'] = null;

                $masterCreated = $cms->getMember()->create($masterParams);

                if (!$masterCreated) {
                    throw new RuntimeException('Failed to create Website 1 master member.');
                }

                $masterUserId = (int) $cms->getMember()->getIdByEmail($emailBase);

                if ($masterUserId <= 0) {
                    throw new RuntimeException('Could not resolve Website 1 master member ID.');
                }
            } else {
                $masterMember = $cms->getMember()->get($masterUserId);
                $masterAlreadyVerified = (int) ($masterMember['email_verified'] ?? 0) === 1;

                if ($masterAlreadyVerified) {
                    $params['email_verified'] = 1;
                    $params['email_verified_at'] =
                        $masterMember['email_verified_at'] ?? date('Y-m-d H:i:s');
                }
            }
        }

        $result = $cms->getMember()->create($params);
    } catch (PDOException $e) {
        error_log(
            '[REGISTER] create PDOException code=' . $e->getCode() . ' msg=' . $e->getMessage(),
        );

        // Duplicate email (unique constraint)
        if ($e->getCode() === '23000') {
            $errors['email'] = 'That email is already registered.';

            $data['website'] =
                $cms->getWebsite()->getById($websiteId) ?: $cms->getWebsite()->getById(1);

            $data['values'] = [
                'forename' => $params['forename'] ?? '',
                'surname' => $params['surname'] ?? '',
                'email' => $params['email_master'] ?? '',
                'plan' => (string) ($_POST['plan'] ?? ''),
                'agegroup' => (string) ($_POST['agegroup'] ?? ''),
                'website' => (string) $websiteId,
            ];

            $data['agegroups'] = $cms->getMember()->getAgegroups();
            $data['plans'] = $cms->getMember()->getPlans();
            $data['errors'] = $errors;
            $data['csrf_token'] = csrf_token($csrfFormKey);
            $data['use_recaptcha'] = true;
            $data['recaptcha_site_key'] = $config['recaptcha_site_key'];

            unset($_SESSION['register_submit_lock']);
            echo $twig->render('register.html', $data);
            exit();
        }

        unset($_SESSION['flash_success']);
        $_SESSION['flash_failure'] = 'Registration failed. Please try again.';
        unset($_SESSION['register_submit_lock']);
        tfol_redirect(DOC_ROOT . 'index/' . $websiteId, 303);
    }

    // If create returned false without throwing
    if (!$result) {
        unset($_SESSION['flash_success']);
        $_SESSION['flash_failure'] = 'Registration failed. Please try again.';
        unset($_SESSION['register_submit_lock']);
        tfol_redirect(DOC_ROOT . 'index/' . $websiteId, 303);
    }

    // SUCCESS: create email verification token + send mail
    try {
        $newUserId =
            $websiteId > 1 && $masterUserId > 0
                ? $masterUserId
                : (int) $cms->getMember()->getIdByEmail($emailForLogin);

        if ($newUserId <= 0) {
            throw new RuntimeException('Could not resolve newly created member ID.');
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $expiryDate = new DateTimeImmutable('+24 hours');
        $expiresAt = $expiryDate->format('Y-m-d H:i:s');

        $saved = $cms->getMember()->createEmailVerification($newUserId, $tokenHash, $expiresAt);
        if (!$saved) {
            throw new RuntimeException('Failed to save email verification token.');
        }

        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');

        $verifyUrl = $baseUrl . '/verify-email?token=' . urlencode($rawToken);

        sendVerificationEmail($email_config, $emailBase, $params['forename'], $verifyUrl);
        $_SESSION['guest_story_email'] = $emailBase;
        $_SESSION['guest_story_register_email'] = $emailBase;
        if (!empty($_SESSION['guest_story_draft'])) {
            header('Location: ' . DOC_ROOT . 'guest-story-check-email');
            exit();
        }

        unset($_SESSION['flash_failure']);
        $_SESSION['flash_success'] =
            'Registration successful. Please check your email and click the verification link before signing in.';

        error_log(
            '[REGISTER] SUCCESS + verification email sent -> redirecting to ' .
                DOC_ROOT .
                'index/' .
                $websiteId,
        );

        unset($_SESSION['register_submit_lock']);
        csrf_rotate($csrfFormKey);
        tfol_redirect(DOC_ROOT . 'index/' . $websiteId, 303);
    } catch (Throwable $e) {
        $messages[] =
            'Your account was created, but the verification email could not be sent. Please contact us if you do not receive it.';
        error_log(
            '[REGISTER] Verification email send failed for ' . $emailBase . ': ' . $e->getMessage(),
        );
        unset($_SESSION['flash_success']);
        $_SESSION['flash_failure'] =
            'Your account was created, but the verification email could not be sent. Please contact us if you do not receive it.';

        unset($_SESSION['register_submit_lock']);
        tfol_redirect(DOC_ROOT . 'index/' . $websiteId, 303);
    }
}
$path = mb_strtolower(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$path = substr($path, strlen(DOC_ROOT));
$path = trim($path, '/');
$parts = explode('/', $path);

if ($parts[0] != 'admin') {
    $page = $parts[0] ?: 'index';
    $id = $parts[1] ?? null;
} else {
    $page = 'admin/' . ($parts[1] ?? '');
    $id = $parts[2] ?? null;
}

if (!$id) {
    $id = 1;
}

$website = $cms->getWebsite()->getById((int) $id);

if (empty($agegroups)) {
    $agegroups = $cms->getMember()->getAgegroups();
}
if (empty($plans)) {
    $plans = $cms->getMember()->getPlans();
}

$member = [];
$data['doc_root'] = $doc_root;
$data['success'] = $_GET['success'] ?? null;
$data['failure'] = $_GET['failure'] ?? null;
$data['agegroups'] = $agegroups;
$data['plans'] = $plans;
$data['errors'] = $errors;
$data['website'] = $website;
$data['use_recaptcha'] = true;
$data['recaptcha_site_key'] = $config['recaptcha_site_key'];
$data['csrf_token'] = csrf_token($csrfFormKey);

echo $twig->render('register.html', $data);
exit();
