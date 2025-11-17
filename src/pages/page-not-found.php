<?php
declare(strict_types=1);                                 // Use strict types
http_response_code(404);                                 // Set HTTP response code

$data['navigation']  = $cms->getMenu()->getAll();    // Get menus

echo $twig->render('page-not-found.html', $data);        // Render template