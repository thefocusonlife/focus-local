<?php
declare(strict_types=1);
require_once APP_ROOT . '/src/security/guard.php';
guardPublic();

$terms[] = null;

$term = trim((string) filter_input(INPUT_GET, 'term'));
$data['term'] = $term;

$data['show'] = filter_input(INPUT_GET, 'show', FILTER_VALIDATE_INT) ?? 3;
$data['from'] = filter_input(INPUT_GET, 'from', FILTER_VALIDATE_INT) ?? 0;

$data['count'] = 0;
$data['stories'] = [];

if ($term !== '') {
    $data['count'] = $cms->getStory()->searchCount($term);

    if ($data['count'] > 0) {
        $data['stories'] = $cms->getStory()->search($term, $data['show'], $data['from']);
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
