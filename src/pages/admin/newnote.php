<?php

declare(strict_types=1);
use PhpBook\Validate\Validate;

require_once APP_ROOT . '/src/security/guard.php';
guardMember();

// Initialize variables needed for the HTML page
$id = !empty($parts[2]) ? (int) $parts[2] : 0;

if ($id <= 0) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    return;
}

$note = [
    'id' => 0,
    'name' => '',
    'request_date' => 'current_time_stamp()',
    'image_file' => '',
    'alt' => '',
]; // Story data

$errors = [
    'warning' => '',
    'id' => '',
    'name' => '',
    'request_date' => '',
    'image_file' => '',
    'alt' => '',
];

$from_id = $cms->getSession()->id;
//user's id from session                                     // Use strict types
//if (!$id) {                                                  // If no valid id
//include APP_ROOT . '/src/pages/page-not-found.php';      // Page not found
//}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // If form was posted
    // $note['to_id']       = intval($_POST['to_member']);      // Get to member id
    $note['website'] = (int) ($_POST['website'] ?? 1);
    $note['from_id'] = (int) $_POST['from_member'];
    $note['to_id'] = (int) $_POST['to_member'];
    $note['to_name'] = $_POST['to_membername'];
    $note['from_name'] = $_POST['from_membername'];
    $note['note_type'] = (int) $_POST['noteid'];
    $note['request'] = $_POST['request'];
    $note['family_id'] = (int) $_POST['family_id'];
    $note['to_family_id'] = (int) $_POST['to_family_id'];
    $note['allow'] = (int) $_POST['allow'];

    // Validate form data
    $errors['request'] = Validate::isText($note['request'], 0, 1000)
        ? ''
        : 'request should be between 0 and 1000 characters';

    $invalid = implode($errors); // Join any error messages
    if ($invalid) {
        // If validation failed
        $errors['warning'] = 'Please correct form errors'; // Store a warning
    } else {
        // Otherwise

        $result = $cms->getNote()->create($note);
        if ($result === false) {
            // If result is false
            $errors['warning'] = 'Please correct form errors'; // Store a warning
        } else {
        } // Otherwise
        redirect('admin/newnote/', ['success' => 'Family updated']); // Redirect with message
    }
}

// Get member data
$to_member = $cms->getMember()->get($id);
$from_member = $cms->getMember()->get($from_id);
if (!$from_member) {
    // If array is empty
    include APP_ROOT . '/src/pages/page-not-found.php'; // Page not found
}
$notes = $cms->getNote()->get($from_id);
$notetype = $cms->getNotetype()->get(1);
$to_membername = $to_member['forename'] . ' ' . $to_member['surname'];
$from_membername = $from_member['forename'] . ' ' . $from_member['surname'];
//$data['navigation']  = $cms->getMenu()->getAll();             // Get menus not necessary?
$data['website'] = $cms->getWebsite()->getById((int) $from_member['website']);
$data['to_member'] = $to_member['id'];
$data['from_member'] = $from_member['id'];
$data['to_membername'] = $to_membername;
$data['from_membername'] = $from_membername;
$data['noteid'] = $notetype['id'];
$data['request'] = '.';
$data['description'] = $notetype['description'];
$data['family_id'] = $from_member['account_id'];
$data['to_family_id'] = $to_member['account_id'];
$data['allow'] = $note['allow'];
$data['success'] = $_GET['Request Sent'] ?? ''; // Success message if present

echo $twig->render('newnote.html', $data); // Render Twig template
