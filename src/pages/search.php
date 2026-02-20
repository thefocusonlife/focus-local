<?php
declare(strict_types=1);
require_once APP_ROOT . '/src/security/guard.php';
guardPublic();

$terms[] = null;

$data['term'] = filter_input(INPUT_GET, 'term');
$x = $data['term'];
if ($x != null) {
    $terms = explode('+', $x);
}
$data['term1'] = $terms[0];
if (!empty($terms[1])) {
    $data['term2'] = $terms[1];
}

$data['show'] = filter_input(INPUT_GET, 'show', FILTER_VALIDATE_INT) ?? 3; // Limit
$data['from'] = filter_input(INPUT_GET, 'from', FILTER_VALIDATE_INT) ?? 0; // Offset

$data['count'] = 0; // Set count to 0
$data['stories'] = []; // Set stories to empty array

if ($data['term1']) {
    if (!isset($data['term2'])) {
        // If no search term
        $data['count'] = $cms->getStory()->searchCount($data['term1']); // Get number of matches
    } else {
        $data['count'] = $cms->getStory()->searchCount1($data['term1'], $data['term2']);
    }
    if ($data['count'] > 0) {
        if (!isset($data['term2'])) {
            // If there are matches
            $data['stories'] = $cms
                ->getStory()
                ->search($data['term1'], $data['show'], $data['from']); // Get matches
        } else {
            $data['stories'] = $cms
                ->getStory()
                ->search1($data['term1'], $data['term2'], $data['show'], $data['from']);
        }
    }
}

if ($data['count'] > $data['show']) {
    // If more than 3 results
    $data['total_pages'] = ceil($data['count'] / $data['show']); // Total pages
    $data['current_page'] = ceil($data['from'] / $data['show']) + 1; // Current page
}
$data['website'] = $cms->getWebsite()->getById(intval($_SESSION['website']));
$data['navigation'] = $cms->getMenu()->getAll2(1, 1); // Get menus

echo $twig->render('search.html', $data); // Render Twig template
