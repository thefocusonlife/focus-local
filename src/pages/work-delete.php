<?php
declare(strict_types=1); // Use strict types
if (!$id) {
    // If no valid id
    include APP_ROOT . '/public/page-not-found.php'; // Page not found
}
$story = $cms->getStory()->get($id, false); // Get story data
if (!$story) {
    // If $story empty
    include APP_ROOT . '/public/page-not-found.php'; // Page not found
}
if ($story['member_id'] !== $cms->getSession()->id) {
    // If not by this member
    include APP_ROOT . '/public/page-not-found.php'; // Page not found
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // If form was submitted
    if (isset($story['image_id'])) {
        // If there was an image
        $path = APP_ROOT . '/public/uploads/' . $story['image_file']; // Set the image path

        $cms->getStory()->imageDelete($story['image_id'], $path, $story['id']); // Delete image
    }
    // if there are comments -- delete prior to deleting story
    $comment = $cms->getComment()->getAll($story['id']);
    if (isset($comment)) {
        foreach ($comment as $item) {
            $deleted = $cms->getComment()->delete($item['story_id']);
        }
    }
    //Delete Story
    $cms->getStory()->delete($id); // Delete story
    redirect('member/' . $cms->getSession()->id . '/', ['success' => 'Story deleted']); // Send to profile page
}

$data['navigation'] = $cms->getMenu()->getAll(); // All menus for navigation
$data['story'] = $story; // Data for Twig template

echo $twig->render('work-delete.html', $data); // Render Twig template
