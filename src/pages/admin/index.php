<?php
//echo "admin/index.php -2";
is_admin($session->role); // Check if admin
$path = mb_strtolower($_SERVER['REQUEST_URI']); // Get path in lowercase
$path = substr($path, strlen(DOC_ROOT)); // Remove up to DOC_ROOT
$parts = explode('/', $path);
if ($parts[0] != 'admin') {
    // If an admin page
    $page = $parts[0] ?: 'index'; // Page name (or use index)
    $id = $parts[1] ?? null; // Get ID (or use null)
} else {
    // If not an admin page
    $page = 'admin/' . ($parts[1] ?? ''); // Page name
    $id = $parts[2] ?? null; // Get ID
}
if (!$id) {
    $id = 1;
}
$website = $cms->getWebsite()->getById(intval($_SESSION['website']));
//echo "admin/index.php -24";
$data['story_count'] = $cms->getStory()->count(); // Get number of stories
$data['menu_count'] = $cms->getMenu()->count(); // Get number of menus
$data['member_count'] = $cms->getMember()->count(); // Get number of menus
$data['website_count'] = $cms->getWebsite()->count(); // Get number of websites
$data['website'] = $website;
echo $twig->render('admin/index.html', $data); // Render Twig template
