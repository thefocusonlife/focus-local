<?php
declare(strict_types=1);

// Preserve current website BEFORE touching session state
$websiteId = (int) ($_SESSION['website'] ?? 1);
if ($websiteId <= 0) {
    $websiteId = 1;
}

$cms->getSession()->resetToGuest($websiteId, true);
redirect('index/' . $websiteId);
exit();
