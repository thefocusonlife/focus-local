<?php
is_admin($session->role); // Check if admin
$data['success'] = $_GET['success'] ?? null; // Check for success message
$data['failure'] = $_GET['failure'] ?? null; // Check for failure message
$data['websites'] = $cms->getWebsite()->getAll(); // Get story summaries

echo $twig->render('admin/websites.html', $data); // Render Twig template
