<?php
is_admin($session->role);                                 // Check if admin
$menu = [];                                           // Initialize menu array
$deleted   = null;                                        // Did menu delete

if (!$id) {                                               // If valid id
    redirect('admin/menus/', ['failure' => 'Menu not found']); // Redirect with error
}

$menu = $cms->getMenu()->get($id);                // Get menu
if (!$menu) {                                         // If valid id
    redirect('admin/menus/', ['failure' => 'Menu not found']); // Redirect with error
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {               // If form was submitted
    if ($id) {                                            // If valid id
        $deleted  = $cms->getMenu()->delete($id);     // Delete menu
        if ($deleted  === true) {                         // If it worked
            redirect('admin/menus/', ['success' => 'Menu deleted']); // Redirect with error
        }
        if ($deleted  === false) {                        // If contains stories
            redirect('admin/menus/', ['failure' => 'Menu contains stories that 
            must be moved or deleted before you can delete the menu']); // Redirect
        }
    }
}

$data['menu'] = $menu;                            // Menu data for template
echo $twig->render('admin/menu-delete.html', $data);  // Render Twig template