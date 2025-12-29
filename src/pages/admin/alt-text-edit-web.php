<?php
use PhpBook\Validate\Validate; // Import Validate namespace
is_admin($session->role); // Check if admin

$story = []; // Initialize story array
$errors = []; // Initialize error message

if (!$id) {
    // If no id
    redirect('/admin/website/', ['failure' => 'Story not found']); // Redirect
}

$website = $cms->getWebsite()->get($id, false); // Get story
if (!$website) {
    // If no story
    redirect('/admin/website/', ['failure' => 'Story not found']); // Redirect
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // If form was submitted
    $website['alt'] = $_POST['alt']; // Get alt text

    $invalid = Validate::isText($website['alt'], 1, 254)
        ? ''
        : 'Alt text for image should be 1 - 254 characters.'; // Validate alt text

    if ($invalid) {
        // If not valid
        $warning = 'Please correct error below'; // Create warning message
    } else {
        $cms->getWebsite()->altUpdate($website['image_file'], $website['alt']); // Update alt text
        redirect('admin/website/' . $id); // Send back to story page
    }
}
$data['website'] = $website; // Story data for template
$data['errors'] = $errors; // Error data for template

echo $twig->render('admin/alt-text-edit.html', $data); // Render Twig template
