<?php

declare(strict_types=1);

require_once __DIR__ . '/guard.php';

function tfol_redirect(string $url, int $status = 303): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $url, true, $status);
    exit();
}

/**
 * Redirect to safe public page (guest context)
 * Default: main website index
 */
function redirectToPublic(): void
{
    $safe = '/index/1';

    if (headers_sent()) {
        echo '<script>window.location.href=' . json_encode($safe) . ';</script>';
        exit();
    }

    header('Location: ' . $safe);
    exit();
}

/**
 * Hard deny with no context leak
 * Use when access should never be allowed
 *
 * NOTE: renamed to avoid collision with canonical denyAccess()
 */
function denyAccessHard(): void
{
    if (defined('DEV') && DEV) {
        http_response_code(403);
        echo '<h2>403 – Access Denied</h2>';
        echo '<p>You do not have permission to access this resource.</p>';
        exit();
    }

    redirectToPublic();
}

/**
 * Redirect to member home (post-login safe landing)
 */
function redirectToMemberHome(): void
{
    if (empty($_SESSION['member_id'])) {
        redirectToLogin('/login', true); // canonical
    }

    $websiteId = (int) ($_SESSION['website'] ?? 1);
    if ($websiteId <= 0) {
        $websiteId = 1;
    }

    $target = '/member/' . $websiteId;

    if (headers_sent()) {
        echo '<script>window.location.href=' . json_encode($target) . ';</script>';
        exit();
    }

    header('Location: ' . $target);
    exit();
}

/**
 * DEV helper — logs and denies
 */
function denyWithLog(string $reason): void
{
    if (defined('DEV') && DEV) {
        error_log('[ACCESS DENIED] ' . $reason . ' | URI=' . ($_SERVER['REQUEST_URI'] ?? ''));
    }

    // If you truly want "no context leak", call denyAccessHard()
    denyAccessHard();
}
