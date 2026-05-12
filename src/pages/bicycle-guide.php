<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';

$websiteId = (int) ($_GET['website'] ?? ($_SESSION['website'] ?? 44));

if ($websiteId !== 44) {
    $websiteId = 44;
}

$data = [];

$data['website'] = $cms->getWebsite()->getById($websiteId);
$data['websiteId'] = $websiteId;

echo $twig->render('bicycle-guide.html', $data);
