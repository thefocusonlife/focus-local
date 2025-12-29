<?php
declare(strict_types=1); // Use strict types
file_put_contents(
    '/tmp/tfol-route.log',
    date('c') . ' PAGE-NOT-FOUND URI=' . ($_SERVER['REQUEST_URI'] ?? '') . "\n",
    FILE_APPEND,
);

http_response_code(404); // Set HTTP response code

$data['navigation'] = $cms->getMenu()->getAll(); // Get menus

echo $twig->render('page-not-found.html', $data); // Render template
