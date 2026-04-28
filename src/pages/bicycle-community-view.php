<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';

$id = (int) ($_GET['id'] ?? 0);
$websiteId = 44;

if ($id <= 0) {
    redirect('index/44');
    exit();
}

$sql = "
    SELECT
        bc.*,
        CONCAT(m.forename, ' ', m.surname) AS member_name
    FROM bicycle_community bc
    LEFT JOIN member m
        ON m.id = bc.member_id
    WHERE bc.id = :id
      AND bc.website_id = :website_id
      AND bc.status = 'published'
    LIMIT 1
";

$item = $cms
    ->getDb()
    ->runSql($sql, [
        'id' => $id,
        'website_id' => $websiteId,
    ])
    ->fetch();
if (!$item) {
    $_SESSION['flash_failure'] = 'Community item not found.';
    redirect('index/44');
    exit();
}

echo $twig->render('bicycle-community-view.html', [
    'item' => $item,
    'websiteId' => $websiteId,
]);
