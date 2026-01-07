<?php
declare(strict_types=1);

// Preserve current website BEFORE touching session state
$websiteId = (int) ($_SESSION['website'] ?? 1);
if ($websiteId <= 0) {
    $websiteId = 1;
}

// Soft logout: reset identity to Guest but keep website context
$cms->getSession()->resetToGuest($websiteId);

// Redirect back to the same website index
redirect('index/' . $websiteId);
exit();
