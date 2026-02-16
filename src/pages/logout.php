<?php
declare(strict_types=1);

error_log(
    '[LOGOUT] hit logout.php; session id=' . session_id() . ' user=' . ($_SESSION['id'] ?? 'NULL'),
);

// Preserve current website BEFORE touching session state
$websiteId = (int) ($_SESSION['website'] ?? 1);
if ($websiteId <= 0) {
    $websiteId = 1;
}

$cms->getSession()->resetToGuest($websiteId, true);
redirect('index/' . $websiteId);
exit();
