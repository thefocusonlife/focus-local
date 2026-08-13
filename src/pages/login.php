<?php
declare(strict_types=1);

use PhpBook\Validate\Validate;

require_once __DIR__ . '/../../config/recaptcha.php';
$config = $config ?? [];

require_once APP_ROOT . '/src/security/redirects.php';
require_once APP_ROOT . '/src/tenancy/website_context.php';
require_once APP_ROOT . '/src/security/guard.php';
require_once APP_ROOT . '/src/security/csrf.php';

$websiteId = (int) ($_SESSION['return_website'] ?? ($_SESSION['website'] ?? 1));

guardPublic();

$csrfFormKey = 'login';
$parts = $parts ?? [];

// ----------------------------
// Guest Story context must win
// ----------------------------
$isGuestStory =
    !empty($_GET['guest_story']) ||
    !empty($_POST['guest_story']) ||
    !empty($_SESSION['guest_story']) ||
    !empty($_SESSION['guest_story_draft']);

if ($isGuestStory) {
    $_SESSION['website'] = 1;
    $_SESSION['websiteid'] = 1;
    $_SESSION['menu_website'] = 1;
    $_SESSION['guest_story'] = 1;
    setWebsiteCookie('tfol_tid', 1);
}

// ----------------------------
// Tenant context
// ----------------------------
[$websiteId, $website] = resolveWebsiteId(
    cms: $cms,
    parts: $parts,
    session: $_SESSION,
    cookie: $_COOKIE,
    get: $_GET,
    email: null,
    defaultWebsiteId: 0,
    cookieName: 'tfol_tid',
    persist: true,
);

// Force website 1 again after resolver in guest-story flow
if ($isGuestStory) {
    $websiteId = 1;
    $website = $cms->getWebsite()->getById(1);

    $_SESSION['website'] = 1;
    $_SESSION['websiteid'] = 1;
    $_SESSION['menu_website'] = 1;
    $_SESSION['guest_story'] = 1;
    setWebsiteCookie('tfol_tid', 1);
}

if ($websiteId <= 0 || empty($website['id'])) {
    redirect('index/1', ['failure' => 'Website context missing.']);
    exit();
}
$websiteId = isset($id) && is_numeric($id) ? (int) $id : (int) ($_SESSION['website'] ?? 1);

if ($websiteId < 1) {
    $websiteId = 1;
}

/*
 * A normal navigation-bar login is not part of an interrupted
 * member-required workflow.
 */
$directLogin = isset($_GET['direct']) && $_GET['direct'] === '1';

if ($directLogin) {
    unset(
        $_SESSION['member_required'],
        $_SESSION['member_required_reason'],
        $_SESSION['return_website'],
        $_SESSION['return_to'],
    );
}
$showLoginForm = isset($_GET['member_required']) && $_GET['member_required'] === '1';

if (
    !empty($_SESSION['member_required']) &&
    !empty($_SESSION['return_to']) &&
    !$showLoginForm &&
    !$directLogin
) {
    $requiredWebsiteId = (int) ($_SESSION['return_website'] ?? $websiteId);

    if ($requiredWebsiteId < 1) {
        $requiredWebsiteId = $websiteId;
    }

    header('Location: ' . DOC_ROOT . 'member-required/' . $requiredWebsiteId);
    exit();
}
// ----------------------------
// Redirect away if already logged in
// ----------------------------
$role = (string) ($_SESSION['role'] ?? 'guest');

if ($role !== 'guest') {
    $sid = (int) ($_SESSION['id'] ?? 0);

    if ($sid > 0 && !$isGuestStory) {
        $membershipWebsiteId = (int) ($_SESSION['membership_application_website'] ?? 0);

        $sessionWebsiteId = (int) ($_SESSION['website'] ?? 0);

        if ($membershipWebsiteId > 0 && $membershipWebsiteId === $sessionWebsiteId) {
            unset($_SESSION['membership_application_website']);

            redirect('membership?website=' . $membershipWebsiteId);
            exit();
        }

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

    // Website-email suffix routing is NOT allowed during guest-story flow
    $emailWebsiteId = null;

    if (!$isGuestStory) {
        $emailWebsiteId = extractWebsiteIdFromWebsiteEmail($email);

        if ($emailWebsiteId !== null && $emailWebsiteId !== (int) $websiteId) {
            $targetWebsite = $cms->getWebsite()->getById($emailWebsiteId);

            if (empty($targetWebsite) || empty($targetWebsite['id'])) {
                $errors['message'] = 'Website not found for that login email.';
            } else {
                $websiteId = (int) $targetWebsite['id'];
                $website = $targetWebsite;

                $_SESSION['website'] = $websiteId;
                $_SESSION['websiteid'] = $websiteId;
                $_SESSION['menu_website'] = $websiteId;
                setWebsiteCookie('tfol_tid', $websiteId);
            }
        }
    }

    $postedWebsiteId = (int) ($_POST['website'] ?? 0);

    if (
        !$isGuestStory &&
        $postedWebsiteId > 0 &&
        $postedWebsiteId !== (int) $website['id'] &&
        $emailWebsiteId === null
    ) {
        $errors['message'] = 'Invalid website context.';
    }

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
                $memberWebsiteId = (int) ($member['website'] ?? 0);
                $isUberAdmin = !empty($member['isUberAdmin']);
                if (
                    !$isGuestStory &&
                    !$isUberAdmin &&
                    ($memberWebsiteId <= 0 || $memberWebsiteId !== (int) $website['id'])
                ) {
                    $errors['message'] =
                        'This email is not valid for ' .
                        (string) $website['name'] .
                        '. Click Register for a free account.';
                } else {
                    if (session_status() !== PHP_SESSION_ACTIVE) {
                        session_start();
                    }

                    session_regenerate_id(true);

                    $dbMember = $cms->getMember()->get((int) ($member['id'] ?? 0));
                    $role = strtolower(
                        trim((string) ($dbMember['role'] ?? ($member['role'] ?? 'member'))),
                    );

                    $cms->getSession()->create($member, (int) $website['id']);

                    if ($isUberAdmin) {
                        $_SESSION['website'] = (int) $website['id'];
                        $_SESSION['websiteid'] = (int) $website['id'];
                        $_SESSION['menu_website'] = (int) $website['id'];

                        setWebsiteCookie('tfol_tid', (int) $website['id']);
                    }

                    $cms->getMember()->clearFailedLogin($loginEmail);

                    $_SESSION['member_id'] = (int) $member['id'];
                    $_SESSION['id'] = (int) $member['id'];
                    $_SESSION['role'] = $role;
                    $_SESSION['account_id'] = (int) ($member['account_id'] ?? $member['id']);
                    $_SESSION['follow_id'] = (int) $_SESSION['account_id'];

                    if ($isGuestStory && !empty($_SESSION['guest_story_draft'])) {
                        $_SESSION['website'] = 1;
                        $_SESSION['websiteid'] = 1;
                        $_SESSION['menu_website'] = 1;
                        $_SESSION['guest_story'] = 1;
                        setWebsiteCookie('tfol_tid', 1);

                        csrf_rotate($csrfFormKey);
                        header('Location: ' . DOC_ROOT . 'guest-story-complete');
                        exit();
                    }

                    $returnTo = (string) ($_SESSION['return_to'] ?? '');
                    unset($_SESSION['return_to']);

                    $membershipWebsiteId = (int) ($_SESSION['membership_application_website'] ?? 0);

                    if (
                        $returnTo === '' &&
                        $membershipWebsiteId > 0 &&
                        $membershipWebsiteId === (int) ($member['website'] ?? 0)
                    ) {
                        $returnTo = '/membership?website=' . $membershipWebsiteId;
                    }

                    unset($_SESSION['membership_application_website']);

                    $membershipWebsiteId = (int) ($_SESSION['membership_application_website'] ?? 0);

                    if (
                        $returnTo === '' &&
                        $membershipWebsiteId > 0 &&
                        $membershipWebsiteId === (int) ($member['website'] ?? 0)
                    ) {
                        $returnTo = '/membership?website=' . $membershipWebsiteId;
                    }

                    unset($_SESSION['membership_application_website']);

                    if (
                        defined('DOC_ROOT') &&
                        DOC_ROOT !== '' &&
                        str_starts_with($returnTo, DOC_ROOT)
                    ) {
                        $returnTo = '/' . ltrim(substr($returnTo, strlen(DOC_ROOT)), '/');
                    }

                    if ($returnTo !== '' && str_starts_with($returnTo, '/admin/')) {
                        $returnTo = '';
                    }

                    $returnWebsiteId = null;

                    if ($returnTo !== '' && preg_match('#^/index/(\\d+)$#', $returnTo, $matches)) {
                        $returnWebsiteId = (int) $matches[1];
                    } elseif ($returnTo !== '') {
                        $returnParts = parse_url($returnTo);
                        parse_str($returnParts['query'] ?? '', $query);

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
                        $targetWebsiteId = $isUberAdmin
                            ? (int) $website['id']
                            : (int) $member['website'];

                        $_SESSION['website'] = $targetWebsiteId;
                        $_SESSION['websiteid'] = $targetWebsiteId;
                        $_SESSION['menu_website'] = $targetWebsiteId;
                    }

                    csrf_rotate($csrfFormKey);

                    if ($returnTo !== '') {
                        redirect(ltrim($returnTo, '/'));
                        exit();
                    }

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
    $mem = 1;
} else {
    $memberRow = $cms->getMember()->get($sessionId);
    $mem = $memberRow ? (int) ($memberRow['account_id'] ?? 1) : 1;
}

if ($isGuestStory) {
    $websiteId = 1;
    $website = $cms->getWebsite()->getById(1);

    $_SESSION['website'] = 1;
    $_SESSION['websiteid'] = 1;
    $_SESSION['menu_website'] = 1;
    $_SESSION['guest_story'] = 1;
    setWebsiteCookie('tfol_tid', 1);
}

$data = [];
$data['navigation'] = $cms->getMenu()->getAll2((int) $website['id'], 1);
$data['success'] = $success;
$data['email'] = $email;
$data['errors'] = $errors;
$data['use_recaptcha'] = true;
$data['recaptcha_site_key'] = $config['recaptcha_site_key'] ?? '';
$data['website'] = $website;
$data['website_id'] = (int) $website['id'];
$data['doc_root'] = $config['doc_root'] ?? DOC_ROOT;
$data['show_resend_verification'] = $showResendVerification;
$data['csrf_token'] = csrf_token($csrfFormKey);
$data['login_email'] = $_SESSION['guest_story_email'] ?? $email;
$data['guest_story'] = $isGuestStory;

echo $twig->render('login.html', $data);
exit();
