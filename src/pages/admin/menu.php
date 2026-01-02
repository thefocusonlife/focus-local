<?php
// Part A: Setup
use PhpBook\Validate\Validate; // Import Validate namespace
is_admin($session->role); // Check if admin
include APP_ROOT . '/src/pages/menu-path.php'; // menu-path includeinclude
// initialize variables
if ($parts[2] == 0) {
    $menu['id'] = 0;
    $menu['website'] = $_SESSION['website'];
    $menu['description'] = '';
    $menu['account_id'] = $_SESSION['account_id'];
    $menu['seo_name'] = '';
    $menu['navigaton'] = 1;
    $menu['position'] = intval($cms->getMenu()->count()) + 1;
}
$errors = [];
// Initialize variables that the PHP code needs
$saved = null; // Did menu save

// Initialize variables that are needed for the the HTML page

if ($parts[2] != 0 and $_SERVER['REQUEST_METHOD'] != 'POST') {
    // If form submitted
    // If id and not submitted
    $menu = $cms->getMenu()->get($id); // Get menu data
}
/*
if($menu == null) {

        $menu['account_id']     = $_SESSION['id'];

    }
*/
// PART B: Get and validate form data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // If form submitted
    $menu = [];
    if (isset($_POST['id']) > 0) {
        $menu['id'] = $_POST['id'];
    }
    $menu['name'] = $_POST['name']; // Get name
    // $menu['website']     = $_POST['account_id'];
    $menu['website'] = $_SESSION['website'];
    $menu['description'] = $_POST['description']; // Get description
    $menu['navigation'] = isset($_POST['navigation']) ? 1 : 0; // Get navigation
    $menu['account_id'] = intval($_SESSION['account_id']);
    $menu['seo_name'] = create_seo_name($menu['name']); // SEO-friendly name
    $menu['position'] = intval($_POST['position']); // postion in menu
    $errors['name'] = Validate::isText($menu['name'], 1, 24)
        ? ''
        : 'Name should be 1-24 characters.'; // Validate name
    $errors['description'] = Validate::isText($menu['description'], 1, 254)
        ? ''
        : 'Description should be 1-254 characters.'; // Validate description
    $invalid = implode($errors); // Join error messages

    // PART C: Check if data is valid, if so update database

    if ($invalid) {
        // If data is invalid
        $errors['warning'] = 'Please correct errors'; // Error message
    } else {
        // Otherwise create / update
        if ($id) {
            // If have id
            $saved = $cms->getMenu()->update($menu); // Try to update menu
        } else {
            // If no id
            $saved = $cms->getMenu()->create($menu); // Try to create menu
        }
        if ($saved === true) {
            // If succeeded
            redirect('admin/menus/', ['success' => 'Menu saved']); // Redirect
        }
        /* if ($saved === false) {                              // If duplicate menu
            $errors['warning'] = 'Menu name already in use'; // Store error message
        } */
    }
}

$data['menu'] = $menu; // Add menu to template
$data['errors'] = $errors; // Add errors to template
$data['website'] = $cms->getWebsite()->getById(intval($_SESSION['website']));
$data['sort_menu_id'] = (int) $menuId;

echo $twig->render('admin/menu.html', $data); // Render Twig template
