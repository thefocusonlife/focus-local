<?php
declare(strict_types=1); // Use strict types
use PhpBook\Validate\Validate; // Import Validate class

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

    // Enforce access rule: if non-members allowed, switch as Guest and go there
    if ((int) ($website['non_members'] ?? 0) === 1) {
        $cms->getSession()->resetToGuest((int) $website['id']);
        redirect('index/' . (int) $website['id']);
        exit();
    }

    // Members-only site
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
