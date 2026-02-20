<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include APP_ROOT . '/src/pages/menu-path.php';

$websiteId = (int) ($_SESSION['website'] ?? 1);
if ($websiteId <= 0) {
    $websiteId = 1;
}

// If splash already seen in this session, skip rendering it
if (!empty($_SESSION['splash_seen'])) {
    redirect('index/' . $websiteId);
    exit();
}

// Mark splash as seen (so it won't show again)
$_SESSION['splash_seen'] = true;
