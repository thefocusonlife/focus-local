<?php
declare(strict_types=1);

function extractWebsiteIdFromWebsiteEmail(string $email): ?int
{
    $email = trim($email);
    if ($email === '') {
        return null;
    }

    // Extract trailing 1–4 digits as website id
    if (preg_match('/(\d{1,4})$/', $email, $m)) {
        $id = (int) $m[1];
        return $id > 0 ? $id : null;
    }

    return null;
}

function setWebsiteCookie(string $cookieName, int $websiteId): void
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $maxAge = 60 * 60 * 24 * 365; // 1 year

    setcookie(
        $cookieName,
        (string) $websiteId,
        [
            'expires'  => time() + $maxAge,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

/**
 * Resolve website context consistently across pages.
 *
 * Priority:
 *  1) Route param ($parts[1])
 *  2) Query param (?tid=)
 *  3) Cookie (tfol_tid by default)
 *  4) Session ($_SESSION['website'])
 *  5) Email suffix (user@gmail.com16) when provided
 *  6) Default (optional)
 *
 * Returns: [int $websiteId, array $websiteRow]
 */
function resolveWebsiteId(
    object $cms,
    array $parts,
    array &$session,
    array $cookie,
    array $get = [],
    ?string $email = null,
    int $defaultWebsiteId = 0,
    string $cookieName = 'tfol_tid',
    bool $persist = true
): array {
    $candidates = [];

    // 1) Route
    $routeId = (int) ($parts[1] ?? 0);
    if ($routeId > 0) $candidates[] = $routeId;

    // 2) Query (?tid=16)
    $tid = $get['tid'] ?? null;
    if (is_string($tid) && $tid !== '' && ctype_digit($tid)) {
        $qId = (int) $tid;
        if ($qId > 0) $candidates[] = $qId;
    }

    // 3) Cookie
    $cookieVal = $cookie[$cookieName] ?? null;
    if (is_string($cookieVal) && $cookieVal !== '' && ctype_digit($cookieVal)) {
        $cId = (int) $cookieVal;
        if ($cId > 0) $candidates[] = $cId;
    }

    // 4) Session
    $sessVal = $session['website'] ?? null;
    if (is_int($sessVal) || (is_string($sessVal) && ctype_digit($sessVal))) {
        $sId = (int) $sessVal;
        if ($sId > 0) $candidates[] = $sId;
    }

    // 5) Email suffix
    if ($email !== null) {
        $eId = extractWebsiteIdFromWebsiteEmail($email);
        if ($eId !== null) $candidates[] = $eId;
    }

    // 6) Default
    if ($defaultWebsiteId > 0) $candidates[] = $defaultWebsiteId;

    // First valid existing website wins
    foreach ($candidates as $id) {
        $website = $cms->getWebsite()->getById((int) $id);
        if (!empty($website) && !empty($website['id'])) {
            $resolvedId = (int) $website['id'];

            if ($persist) {
                $session['website'] = $resolvedId;
                setWebsiteCookie($cookieName, $resolvedId);
            }

            return [$resolvedId, $website];
        }
    }

    return [0, []];
}
