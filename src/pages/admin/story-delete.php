<?php
$deleted = null; // Did story delete
is_admin($session->role); // Check if admin
include APP_ROOT . '/src/pages/menu-path.php';

if (!empty($parts[2])) {
    $id = $parts[2];
}
if (!$id) {
    // If no id
    redirect('admin/stories/', ['failure' => 'Story not found']); // Redirect
}
$story = $cms->getStory()->get($id, false); // Get story
if (!$story) {
    // If no story
    redirect('admin/stories/', ['failure' => 'Story not found']); // Redirect
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // If form was submitted
    if (isset($story['image_id'])) {
        // If there was an image
        $path = APP_ROOT . '/public/uploads/' . $story['image_file']; // Set the image path
        $cms->getStory()->imageDelete($story['image_id'], $path, $id); // Delete image
    }
    // if there are comments -- delete prior to deleting story
    $comment = $cms->getComment()->getAll($story['id']);
    if (isset($comment)) {
        foreach ($comment as $item) {
            $deleted = $cms->getComment()->delete($item['story_id']);
        }
    }
    // Delete Story
    $deleted = $cms->getStory()->delete($id); // Delete story
    if ($deleted === true) {
        // If deleted
        redirect('admin/stories/', ['success' => 'Story deleted']); // Redirect
    } else {
        // Otherwise
        throw new Exception('Unable to delete story'); // Throw an exception
    }
}

$data['story'] = $story; // Story data for template

echo $twig->render('admin/story-delete.html', $data); // Render Twig template
