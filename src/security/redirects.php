<?php
declare(strict_types=1);

/**
 * Redirect to login page and exit
 */
function redirectToLogin(): void
{
    if (headers_sent()) {
        echo '<script>window.location.href="/login";</script>';
        exit();
    }

    header('Location: /login');
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
        echo '<script>window.location.href="' . $safe . '";</script>';
        exit();
    }

    header('Location: ' . $safe);
    exit();
}

/**
 * Hard deny with no context leak
 * Use when access should never be allowed
 */
function denyAccess(): void
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
        redirectToLogin();
    }

    $websiteId = (int) ($_SESSION['website_id'] ?? 1);

    $target = '/member/' . $websiteId;

    if (headers_sent()) {
        echo '<script>window.location.href="' . $target . '";</script>';
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

    denyAccess();
}
