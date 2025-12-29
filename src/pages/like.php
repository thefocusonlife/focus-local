<?php
declare(strict_types=1); // Use strict types

if (!$id or $session->id == 0) {
    // If no valid id
    include APP_ROOT . '/src/pages/page-not-found.php'; // Page not found
}

$liked = $cms->getLike()->get([$id, $_SESSION['id']]); // Does member like

if ($liked) {
    // If they like it already
    echo 'like.php -15';

    $cms->getLike()->delete([$id, $_SESSION['id']]); // Remove like
} else {
    // Otherwise
    $cms->getLike()->create([$id, $_SESSION['id']]); // Add like
}
redirect('story/' . $id . '/' . $parts[2] . '/'); // Redirect to story page
