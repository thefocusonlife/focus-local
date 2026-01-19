<?php
// is_admin($session->role);                              // Check if admin
$note = []; // Initialize note array
$deleted = null; // Did note delete
$id = intval($parts[2]);
if (!$id) {
    // If valid id
    redirect('admin/notes/', ['failure' => 'Note not found']); // Redirect with error
}

$note = $cms->getNote()->getById($id); // Get note

if (!$note) {
    // If valid id
    redirect('admin/notes/', ['failure' => 'Note not found']); // Redirect with error
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // If form was submitted
    if ($id) {
        // If valid id
        $deleted = $cms->getNote()->delete($id); // Delete menu
        if ($deleted === true) {
            // If it worked
            redirect('admin/notes/', ['success' => 'Note deleted']); // Redirect with error
        }
        if ($deleted === false) {
            // If contains stories
            redirect('admin/notes/', ['failure' => 'Unable to delet this note']); // Redirect
        }
    }
}
$data['note'] = $note; // Menu data for template
$data['website'] = $cms->getWebsite()->getById($note['website']);
echo $twig->render('admin/note-delete.html', $data); // Render Twig template
