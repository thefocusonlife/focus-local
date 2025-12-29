<?php
declare(strict_types=1); // Use strict types
use PhpBook\Validate\Validate; // Import Validate class
// menu-path include

//$websites = [];                                            // Initialize member array
//$errors = [];
//var_dump_pre($_SESSION);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // If form was posted

    $w = intval($_POST['website']);
    $website = $cms->getWebsite()->get($w);
    if (isset($_SESSION) and $_SESSION['id'] > 0) {
        $member = $cms->getMember()->get(intval($_SESSION['id']));
        $m_w = $member['website'];
        redirect('index/' . $website['id']);
    } else {
    }

    if ($website['non_members'] == 1) {
        redirect('index/' . $website['id']);
        exit();
    } else {
        redirect('index/99999', [
            'failure' => 'You must register as a member to access GET FOCUSED websites. 
      Click the "Register" link on top of this page to see pricing.',
        ]);
        exit();
    }
}

$websites = $cms->getWebsite()->getAll();
$data['source'] = 'select-website';
$data['websites'] = $websites;
$data['website'] = $cms->getWebsite()->get(intval($_SESSION['website']));

echo $twig->render('select-website.html', $data); // Render Twig template
