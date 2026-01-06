<?php
declare(strict_types=1); // Use strict types
use PhpBook\Validate\Validate; // Import Validate class
// menu-path include

//$websites = [];                                            // Initialize member array
//$errors = [];
//var_dump_pre($_SESSION);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $w = (int) ($_POST['website'] ?? 0);
    if ($w <= 0) {
        redirect('index/99999', ['failure' => 'No website selected.']);
        exit();
    }

    $website = $cms->getWebsite()->get($w);
    if (empty($website) || empty($website['id'])) {
        redirect('index/99999', ['failure' => 'Website not found.']);
        exit();
    }

    /**
     * P1 FIX: switching websites from Home should NOT carry member context.
     * Force Guest context + clear cached per-website/per-member session state.
     */
    $_SESSION['id'] = 0;
    $_SESSION['account_id'] = 0;

    $_SESSION['role'] = 'guest';

    // Clear cached objects / navigation state (adjust as you discover more keys)
    unset(
        $_SESSION['role'],
        $_SESSION['forename'],
        $_SESSION['surname'],
        $_SESSION['email'],
        $_SESSION['logged_in'], // if you use it
        $_SESSION['member'],
        $_SESSION['member_id'],
        $_SESSION['menu_owner_id'],
        $_SESSION['section'],
        $_SESSION['menu_id'],
        $_SESSION['active_sorttype_id'],
        $_SESSION['sorttype'],
        $_SESSION['website_id'],
        $_SESSION['website'], // you use this later for $cms->getWebsite()->get(...)
    );

    // Now set ONLY the newly selected website in session
    $_SESSION['website'] = (int) $website['id'];
    $_SESSION['role'] = 'guest';

    // Optional but recommended when identity/context changes
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    // Respect access rules
    if ((int) $website['non_members'] === 1) {
        redirect('index/' . (int) $website['id']);
        exit();
    }

    redirect('index/99999', [
        'failure' => 'You must register as a member to access GET FOCUSED websites.
      Click the "Register" link on top of this page to see pricing.',
    ]);
    exit();
}

$websites = $cms->getWebsite()->getAll();
$data['source'] = 'select-website';
$data['websites'] = $websites;
$data['website'] = $cms->getWebsite()->get(intval($_SESSION['website']));

echo $twig->render('select-website.html', $data); // Render Twig template
