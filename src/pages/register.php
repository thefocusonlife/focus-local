<?php
declare(strict_types=1); // Use strict types
use PhpBook\Validate\Validate; // Import Validate class

require_once __DIR__ . '/../../config/recaptcha.php';
// DEBUG: confirm DB in use
$dbname = $cms->getDb()->runSQL('SELECT DATABASE() AS db')->fetch();
error_log('[DB] connected=' . ($dbname['db'] ?? 'UNKNOWN'));
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
//echo $twig->render('plans.html');
//exit;

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    // If form doesn't exist
    $agegroups = $cms->getMember()->getAgegroups();
    $plans = $cms->getMember()->getPlans();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // -----------------------------
    // reCAPTCHA v3 verification
    // -----------------------------
    /*    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
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
*/
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

    $websiteId = (int) ($_POST['website'] ?? 0);
    $emailBase = trim((string) ($_POST['email'] ?? ''));
    $confirm = (string) ($_POST['confirm'] ?? '');
    // TFOL rule: suffix email for non-main websites
    $emailForLogin = $websiteId !== 1 ? $emailBase . $websiteId : $emailBase;

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
        'plan' => (int) 1,
        'pagelimit' => 50,
        // If $menuId exists here, keep your current logic
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
    $errors['email'] = Validate::isEmail($params['email_master'])
        ? ''
        : 'Please enter a valid email';

    $errors['password'] = Validate::isPassword($params['password'])
        ? ''
        : 'Passwords must be at least 8 characters and have:<br>
                A lowercase letter<br>An uppercase letter<br>A number
                <br>And a special character';
    $errors['confirm'] = $params['password'] === $confirm ? '' : 'Passwords do not match';

    $invalid = implode($errors); // Join error messages

    if (!$invalid) {
        // If no errors
        error_log(
            '[REGISTER] website=' .
                (int) ($params['website'] ?? 0) .
                ' email_master=' .
                var_export($params['email_master'] ?? null, true) .
                ' email=' .
                var_export($params['email'] ?? null, true),
        );

        $result = $cms->getMember()->create($params);

        if ($result === false) {
            // If result is false
            //$errors['email'] = 'Email address already used click Back refresh page re-enter appending Website ID shown below'; // Store a warning
            if ($result === false) {
                $w = (int) ($member['website'] ?? 1);
                redirect('register/' . $w, [
                    'failure' =>
                        'That email is already registered on the main site. Please log in instead.',
                ]);
                // Website 1: already registered
                if ($w === 1) {
                    redirect('register/' . $w, [
                        'failure' =>
                            'That email is already registered on the main site. Please log in instead.',
                    ]);
                }

                // Website > 1: could be either already registered on that site OR your “append id” rule
                redirect('register/' . $w, [
                    'failure' =>
                        'That email is already registered for this website. Please log in.',
                ]);
            }
        } else {
            // Otherwise send to login
            // SUCCESS

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Hybrid backup completed successfully to BACKUP_A.',
            ];
            // Redirect to guest home (index/1)
            header('Location: ' . $doc_root . 'index/' . (int) $params['website']);
            exit();
        }
    }
}
$path = mb_strtolower($_SERVER['REQUEST_URI']); // Get path in lowercase
$path = substr($path, strlen(DOC_ROOT)); // Remove up to DOC_ROOT
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

echo $twig->render('register.html', $data); // Render Twig template

exit();
