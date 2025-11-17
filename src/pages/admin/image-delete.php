<?php
declare(strict_types = 1);     
include APP_ROOT . '/src/pages/menu-path.php'; 
is_admin($session->role);                                   // Check if admin
$story = [];  
$id = intval($parts[2]);

                                       // Initialize story array
if (!$id) {                                                 // If no id
    redirect('admin/stories/', ['failure' => 'Story not found']); // Redirect
}
$story = $cms->getStory()->get($id, false);             // Get story

if (!$story) {                                            // If no image
    redirect('admin/stories/', ['failure' => 'Story not found']); // Redirect
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {  
                   // If form was submitted
    
    $path = APP_ROOT . '/public/uploads/' . $story['image_file']; // Path to file
    $cms->getStory()->imageDelete($story['image_id'], $path, $id); // Delete image
    redirect('admin/story/' . $id);                       // Redirect
}

$data['story'] = $story;
$data['website'] = $cms->getWebsite()->get( $story['website']);                                // Story data for template
echo $twig->render('admin/image-delete.html', $data);       // Render Twig template