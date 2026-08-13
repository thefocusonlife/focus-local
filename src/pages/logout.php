<?php
declare(strict_types=1);

// Preserve current website BEFORE touching session state
$websiteId = (int) ($_SESSION['website'] ?? 1);
if ($websiteId <= 0) {
    $websiteId = 1;
}
unset($_SESSION['return_to']);
unset($_SESSION['membership_application_website']);
$cms->getSession()->resetToGuest($websiteId, true);
redirect('index/' . $websiteId);
exit();
