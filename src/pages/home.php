<?php
declare(strict_types=1);
// Use strict types
include APP_ROOT . '/src/pages/menu-path.php'; // get path for website and menus
$guidetext = '';

if (!$id) {
    $website = $cms->getWebsite()->getById(intval($_SESSION['website']));
} else {
    $website = $cms->getWebsite()->getById(intval($id));
}

$data['navigation'] = $cms->getMenu()->getAll2(1, 1);

$data['website'] = $website; // Get menus
echo $twig->render('home.html', $data); // Render Twig template
