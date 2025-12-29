<?php
declare(strict_types=1);

$key = (int) ($_SESSION['website'] ?? 1);

// End session (must truly clear $_SESSION and cookie)
$cms->getSession()->delete();

redirect('index/' . $key);

exit();
