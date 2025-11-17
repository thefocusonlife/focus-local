<?php
use PhpBook\Validate\Validate;                           // Import Validate namespace
is_admin($session->role);                                // Check if admin

$story = [];                                           // Initialize story array
$errors  = [];                                           // Initialize error message

if (!$id) {                                              // If no id
    redirect('/admin/stories/', ['failure' => 'Story not found']); // Redirect
}

$story = $cms->getStory()->get($id, false);          // Get story
if (!$story) {                                         // If no story
    redirect('/admin/stories/', ['failure' => 'Story not found']); // Redirect
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {              // If form was submitted
    $story['image_alt'] = $_POST['image_alt'];         // Get alt text

    $invalid = (Validate::isText($story['image_alt'], 1, 254))
        ? '' : 'Alt text for image should be 1 - 254 characters.';   // Validate alt text

    if ($invalid) {                                      // If not valid
        $warning = 'Please correct error below';         // Create warning message
    } else {
        $cms->getStory()->altUpdate($story['image_id'],  $story['image_alt']); // Update alt text
        redirect('admin/story/' . $id);                // Send back to story page
    }

}
$data['story'] = $story;                             // Story data for template
$data['errors']  = $errors;                              // Error data for template

echo $twig->render('admin/alt-text-edit.html', $data);   // Render Twig template