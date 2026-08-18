<?php
// src/pages/terms-privacy.php
require_once APP_ROOT . '/src/security/guard.php';
guardPublic();
// 1. Navigation menu (same pattern as login.php)
$mem = $_SESSION['id'] ?? 0;
$data['navigation'] = $cms->getMenu()->getAll2($_SESSION['website'], $mem);

// 2. Website object (required by layout.html because it loads logo, favicon, etc.)
if (!isset($website)) {
    $website = $cms->getWebsite()->getById($_SESSION['website'] ?? 1);
}
$data['website'] = $website;

// 3. Session object (your layout uses session.forename, session.role, etc.)
$data['session'] = $_SESSION;

// 4. Render the Twig template
echo $twig->render('terms.html', $data);
