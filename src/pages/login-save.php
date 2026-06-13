<?php
declare(strict_types=1); // Use strict types
use PhpBook\Validate\Validate; // Import Validate class

include APP_ROOT . '/src/pages/menu-path.php'; // get path for website and menus

if ($cms->getSession()->role !== 'public') {
    // If user is already logged in
    redirect('member/' . $cms->getSession()->id); // Redirect to their page
    exit(); // Stop code running
}

$email = ''; // Initialize email variable
$errors = []; // Initialize errors
$success = $_GET['success'] ?? null; // Get success message
//var_dump_pre($website);
//var_dump_pre($_SERVER['REQUEST_METHOD']);
//echo "login -54";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // If form submitted
    $email = $_POST['email']; // Get email address
    $password = $_POST['password'];
    $website = intval($_POST['website']); // Get password
    //  var_dump_pre($website);
    //echo "login -59";

    $errors['email'] = Validate::isEmail($email) ? '' : 'Please enter a valid email address'; // Validate email
    $errors['password'] = Validate::isPassword($password)
        ? ''
        : 'Passwords must be at least 8 characters and have:<br>
                A lowercase letter<br>An uppercase letter<br>A number
                <br>And a special character'; // Validate password
    $invalid = implode($errors);

    if ($invalid) {
        // If data is not valid
        $errors['message'] = 'Please try again.'; // Store error message
    } else {
        //temp log
        error_log('LOGIN-SAVE return_to=' . ($_SESSION['return_to'] ?? 'EMPTY'));
        // 1. Stories Worth Saving special flow first
        if (!empty($_GET['guest_story']) || !empty($_SESSION['guest_story_draft'])) {
            redirect('guest-story-complete');
            exit();
        }

        // 2. Universal return_to flow second
        if (!empty($_SESSION['return_to'])) {
            $returnTo = (string) $_SESSION['return_to'];
            unset($_SESSION['return_to']);

            header('Location: ' . $returnTo);
            exit();
        }

        // 3. Normal/default login behavior last
        redirect('member');
        exit();
        $website = (string) ($isGuestStory ? 1 : $_POST['website'] ?? 1);
        $member = $cms->getMember()->login($email, $website, $password); // Get member details
        if ($member and $member['role'] == 'suspended') {
            // If member is suspended
            $errors['message'] = 'Account suspended'; // Store message
        } elseif ($member and $member['role'] == 'pending') {
            // If member is suspended
            $errors['message'] =
                'Membership pending.  Use Contact Us to inquire about your registration.'; // Store message
        } elseif ($member) {
            // Otherwise for members
            $cms->getSession()->create($member, $website); // Create session
            redirect('member/' . $member['id']); // Redirect to their page
        } else {
            // Otherwise
            $errors['message'] = 'Please try again.'; // Store error message
        }
    }
}
if ($_SESSION['id'] == 0) {
    $member = 0;
    $mem = intval($website['id']);
} else {
    $member = $cms->getMember()->get(intval($_SESSION['id']));
    $men = intval($member['account_id']);
}
$cms->getSession()->create($member, $website['id']);
$data['navigation'] = $cms->getMenu()->getAll2($_SESSION['website'], $mem); // Get navigation menus
$data['success'] = $success; // Success message
$data['email'] = $email; // Email address if validation failed
$data['errors'] = $errors; // Errors array
$data['website'] = $website;
//var_dump_pre($data);
//echo "login -108";
echo $twig->render('login.html', $data); // Render Twig template
