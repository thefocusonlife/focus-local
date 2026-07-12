<?php
declare(strict_types=1);
$allowedLocations = [
    'bend',
    'redmond',
    'sisters',
    'madras',
    'prineville',
    'lapine',
    'centraloregon',
];

$location = strtolower((string) ($_GET['location'] ?? 'redmond'));

if (!in_array($location, $allowedLocations, true)) {
    $location = 'redmond';
}

require_once APP_ROOT . '/src/security/guard.php';

//guardMember();

$viewerId = (int) ($_SESSION['id'] ?? 0);
$role = strtolower((string) ($_SESSION['role'] ?? ''));

if ($viewerId <= 0 || $role === 'guest') {
    $_SESSION['return_to'] = '/bicycle-community?website=44';
    $_SESSION['flash_failure'] = 'You must be logged in to save bicycle community content.';
    redirect('login');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('bicycle-community?website=44');
    exit();
}

$id = (int) ($_POST['id'] ?? 0);
$websiteId = (int) ($_POST['website_id'] ?? ($_GET['website'] ?? ($_SESSION['website'] ?? 1)));

if ($websiteId !== 44) {
    $websiteId = 44;
}

$title = trim((string) ($_POST['title'] ?? ''));
$contentType = trim((string) ($_POST['content_type'] ?? 'story'));
$url = trim((string) ($_POST['url'] ?? ''));
$summary = trim((string) ($_POST['summary'] ?? ''));
$content = trim((string) ($_POST['content'] ?? ''));
$status = trim((string) ($_POST['status'] ?? 'published'));

$allowedTypes = ['story', 'link', 'newsletter'];
if (!in_array($contentType, $allowedTypes, true)) {
    $contentType = 'story';
}

$allowedStatuses = ['draft', 'published', 'archived'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'published';
}

if ($title === '') {
    $_SESSION['flash_failure'] = 'Title is required.';
    redirect('bicycle-community?website=44');
    exit();
}

if ($contentType === 'link' && $url === '') {
    $_SESSION['flash_failure'] = 'A URL is required for link items.';
    redirect('bicycle-community?website=44');
    exit();
}

if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
    $_SESSION['flash_failure'] = 'Please enter a valid URL.';
    redirect('bicycle-community?website=44');
    exit();
}

try {
    if ($id > 0) {
        $sql = "
            UPDATE bicycle_community
            SET
                title = :title,
                content_type = :content_type,
                url = :url,
                summary = :summary,
                content = :content,
                status = :status,
                updated = NOW()
            WHERE id = :id
              AND website_id = :website_id
        ";

        $cms->getDb()->runSql($sql, [
            'id' => $id,
            'website_id' => $websiteId,
            'title' => $title,
            'content_type' => $contentType,
            'url' => $url !== '' ? $url : null,
            'summary' => $summary !== '' ? $summary : null,
            'content' => $content !== '' ? $content : null,
            'status' => $status,
        ]);

        $_SESSION['flash_success'] = 'Community item updated successfully.';
    } else {
        $sql = "
            INSERT INTO bicycle_community (
                website_id,
                member_id,
                title,
                content_type,
                url,
                summary,
                content,
                status,
                created,
                updated
            ) VALUES (
                :website_id,
                :member_id,
                :title,
                :content_type,
                :url,
                :summary,
                :content,
                :status,
                NOW(),
                NOW()
            )
        ";

        $cms->getDb()->runSql($sql, [
            'website_id' => $websiteId,
            'member_id' => $viewerId,
            'title' => $title,
            'content_type' => $contentType,
            'url' => $url !== '' ? $url : null,
            'summary' => $summary !== '' ? $summary : null,
            'content' => $content !== '' ? $content : null,
            'status' => $status,
        ]);

        $_SESSION['flash_success'] = 'Community item added successfully.';
    }
} catch (Throwable $e) {
    error_log('[bicycle-community-save] ' . $e->getMessage());
    $_SESSION['flash_failure'] = 'Community item could not be saved.';
    redirect('bicycle-community?website=44');
    exit();
}

$websiteId = (int) ($_POST['website_id'] ?? 0);
redirect('index/44?location=' . urlencode($location));
exit();
