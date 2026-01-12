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

// HARD logout: destroy session state
$cms->getSession()->delete(); // should unset + destroy + clear cookie (or equivalent)

// Recreate a clean guest session in the same website context
$cms->getSession()->create(0, $websiteId);

// Redirect back to the same website index
redirect('index/' . $websiteId);
exit();
