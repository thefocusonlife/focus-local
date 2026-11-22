<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/guard.php';
guardMember();

require_once APP_ROOT . '/src/security/redirects.php';

/*
 * Legacy notification URL.
 * The active Notifications controller uses the logged-in member,
 * rather than a member ID supplied in the URL.
 */
header('Location: ' . DOC_ROOT . 'admin/notes/', true, 303);
exit();
