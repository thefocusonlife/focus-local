<?php
declare(strict_types=1);

use PhpBook\Validate\Validate;

require_once __DIR__ . '/../../config/recaptcha.php';
require_once APP_ROOT . '/src/security/redirects.php';
require_once APP_ROOT . '/src/tenancy/website_context.php';
require_once APP_ROOT . '/src/security/guard.php';
require_once APP_ROOT . '/src/security/csrf.php';
guardPublic();
$csrfFormKey = 'login';
// (Optional, if you created it already)
//require_once APP_ROOT . '/src/lib/debug.php';

// ----------------------------
// Tenant context (website)
// ----------------------------
[$websiteId, $website] = resolveWebsiteId(
    cms: $cms,
    parts: $parts,
    session: $_SESSION,
    cookie: $_COOKIE,
    get: $_GET,
    email: null, // only allow email override on POST
    defaultWebsiteId: 0, // force redirect if missing
    cookieName: 'tfol_tid',
    persist: true,
);

if ($websiteId <= 0) {
    redirect('index/1', ['failure' => 'Website context missing.']);
    exit();
}

// ----------------------------
// Redirect away if already logged in
// ----------------------------
$role = (string) ($_SESSION['role'] ?? 'guest');
if ($role !== 'guest') {
    $sid = (int) ($_SESSION['id'] ?? 0);
    if ($sid > 0) {
        redirect('member/' . $sid);
        exit();
    }
}

// ----------------------------
// Init view vars
// ----------------------------
$email = '';
$errors = [];
$success = $_GET['success'] ?? null;
$showResendVerification = false;

// ----------------------------
// POST handler
// ----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = $_POST['csrf_token'] ?? '';

    if (!csrf_validate($csrfFormKey, is_string($submittedCsrf) ? $submittedCsrf : null)) {
        error_log('[LOGIN] CSRF validation failed sid=' . session_id());

        $errors['message'] =
            'Your form session expired or failed security validation. Please try again.';

        csrf_rotate($csrfFormKey);
    }
    $email = (string) ($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    // --- Website-email suffix routing (e.g. user@gmail.com16) ---
    $emailWebsiteId = extractWebsiteIdFromWebsiteEmail($email);

    if ($emailWebsiteId !== null && $emailWebsiteId !== (int) $websiteId) {
        // Validate target website exists
        $targetWebsite = $cms->getWebsite()->getById($emailWebsiteId);
        if (empty($targetWebsite) || empty($targetWebsite['id'])) {
            $errors['message'] = 'Website not found for that login email.';
        } else {
            // Switch tenant context NOW (before login2)
            $websiteId = (int) $targetWebsite['id'];
            $website = $targetWebsite;

            $_SESSION['website'] = $websiteId;
            setWebsiteCookie('tfol_tid', $websiteId);

            // If you have any tenant-specific initialization beyond $website,
            // do it here (DB schema switch, config, etc.)
            // TenantContext::init($websiteId);
        }
    }

    // Never trust posted website; allow mismatch if we re-resolved via email suffix
    $postedWebsiteId = (int) ($_POST['website'] ?? 0);
    if (
        $postedWebsiteId > 0 &&
        $postedWebsiteId !== (int) $website['id'] &&
        $emailWebsiteId === null
    ) {
        $errors['message'] = 'Invalid website context.';
    }

    // -----------------------------
    // reCAPTCHA v3 verification (fail-fast)
    // -----------------------------
    if (empty($errors['message'])) {
        $recaptchaToken = (string) ($_POST['g-recaptcha-response'] ?? '');
        if ($recaptchaToken === '') {
            $errors['message'] =
                'Security check token missing. Please refresh the page and try again.';
        } else {
            $secretKey = (string) ($config['recaptcha_secret_key'] ?? '');
            if (!verify_recaptcha_v3($recaptchaToken, 'login', $secretKey, 0.1)) {
                $errors['message'] = 'Login failed security check. Please try again.';
            }
        }
    }

    // Validate email/password (only if security passed)
    if (empty($errors['message'])) {
        $errors['email'] = Validate::isEmail($email) ? '' : 'Please enter a valid email address';

        $errors['password'] = Validate::isPassword($password)
            ? ''
            : 'Passwords must be at least 8 characters and have:<br>
                A lowercase letter<br>An uppercase letter<br>A number
                <br>And a special character';

        $invalid = implode($errors);
        if ($invalid) {
            $errors['message'] = 'Please try again.';
        }
    }

    // Attempt login
    $loginEmail = trim($email);
    if (empty($errors['message'])) {
        if ($cms->getMember()->isLoginLocked($loginEmail)) {
            $errors['message'] = 'Too many failed login attempts. Please try again later.';
        } else {
            $member = $cms->getMember()->login2($loginEmail, $password);

            if (empty($member)) {
                $cms->getMember()->recordFailedLogin($loginEmail);
                $errors['message'] = 'Invalid email or password.';
            } elseif (($member['status'] ?? '') === 'suspended') {
                $errors['message'] = 'Account suspended.';
            } elseif (($member['status'] ?? '') === 'pending') {
                $errors['message'] =
                    'Membership pending. Use Contact Us to inquire about your registration.';
            } elseif ((int) ($member['email_verified'] ?? 0) !== 1) {
                $errors['message'] = 'Please verify your email address before signing in.';
                $showResendVerification = true;
            } else {
                // Enforce tenant membership (no fallback)
                $memberWebsiteId = (int) ($member['website'] ?? 0);
                if ($memberWebsiteId <= 0 || $memberWebsiteId !== (int) $website['id']) {
                    $errors['message'] = 'This email not valid for ' . (string) $website['name'];
                } else {
                    // ✅ SUCCESS: create session
                    if (session_status() !== PHP_SESSION_ACTIVE) {
                        session_start();
                    }

                    session_regenerate_id(true);

                    $dbMember = $cms->getMember()->get((int) ($member['id'] ?? 0));
                    $role = strtolower(
                        trim((string) ($dbMember['role'] ?? ($member['role'] ?? 'member'))),
                    );

                    $cms->getSession()->create($member, (int) $website['id']);
                    $cms->getMember()->clearFailedLogin($loginEmail);

                    // hard-assert the important bits (defensive)
                    $_SESSION['member_id'] = (int) $member['id'];
                    $_SESSION['id'] = (int) $member['id'];
                    $_SESSION['role'] = $role;
                    $_SESSION['account_id'] = (int) ($member['account_id'] ?? $member['id']);
                    $_SESSION['follow_id'] = (int) $_SESSION['account_id'];

                    // Redirect to intended deep-link if present (and safe), else safe fallback
                    $returnTo = (string) ($_SESSION['return_to'] ?? '');
                    unset($_SESSION['return_to']);

                    // Normalize: strip DOC_ROOT prefix if present, so comparisons are consistent
                    if (
                        defined('DOC_ROOT') &&
                        DOC_ROOT !== '' &&
                        str_starts_with($returnTo, DOC_ROOT)
                    ) {
                        $returnTo = '/' . ltrim(substr($returnTo, strlen(DOC_ROOT)), '/');
                    }

                    // Never redirect to admin pages via deep-link return_to
                    if ($returnTo !== '' && str_starts_with($returnTo, '/admin/')) {
                        $returnTo = '';
                    }

                    // Preserve website context for deep links like /index/44
                    $returnWebsiteId = null;

                    if ($returnTo !== '' && preg_match('#^/index/(\\d+)$#', $returnTo, $matches)) {
                        $returnWebsiteId = (int) $matches[1];
                    } elseif ($returnTo !== '') {
                        $parts = parse_url($returnTo);
                        parse_str($parts['query'] ?? '', $query);

                        if (isset($query['website'])) {
                            $returnWebsiteId = (int) $query['website'];
                        } elseif (isset($query['website_id'])) {
                            $returnWebsiteId = (int) $query['website_id'];
                        }
                    }

                    if ($returnWebsiteId !== null && $returnWebsiteId > 0) {
                        $_SESSION['website'] = $returnWebsiteId;
                        $_SESSION['websiteid'] = $returnWebsiteId;
                        $_SESSION['menu_website'] = $returnWebsiteId;
                    } else {
                        $_SESSION['website'] = (int) $member['website'];
                        $_SESSION['websiteid'] = (int) $member['website'];
                        $_SESSION['menu_website'] = (int) $member['website'];
                    }

                    if ($returnTo !== '') {
                        csrf_rotate($csrfFormKey);
                        redirect(ltrim($returnTo, '/'));
                        exit();
                    }

                    csrf_rotate($csrfFormKey);
                    redirect('member/' . $member['id']);
                    exit();
                }
            }
        }
    }
}
// ----------------------------
// Navigation context
// ----------------------------
$sessionId = (int) ($_SESSION['id'] ?? 0);

if ($sessionId === 2 || $sessionId === 0) {
    $memberRow = null;
    $mem = 1; // default account id for public menus (UberAdmin/shared)
} else {
    $memberRow = $cms->getMember()->get($sessionId);
    $mem = $memberRow ? (int) ($memberRow['account_id'] ?? 1) : 1;
}

$data = [];
$data['navigation'] = $cms->getMenu()->getAll2((int) $website['id'], 1);
$data['success'] = $success;
$data['email'] = $email;
$data['errors'] = $errors;
$data['use_recaptcha'] = true;
$data['recaptcha_site_key'] = $config['recaptcha_site_key'];
$data['website'] = $website;
$data['doc_root'] = $config['doc_root'] ?? '/_stage/';
$data['show_resend_verification'] = $showResendVerification;
$data['csrf_token'] = csrf_token($csrfFormKey);

echo $twig->render('login.html', $data);
exit();
