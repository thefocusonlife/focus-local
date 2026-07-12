<?php

declare(strict_types=1);

/** @var \Twig\Environment $twig */
/** @var array $config */
/** @var mixed $cms */
/** @var int|string|null $id */

$websiteId =
    isset($id) && is_numeric($id)
        ? (int) $id
        : (int) ($_GET['website'] ?? ($_SESSION['website'] ?? 1));

if ($websiteId < 1) {
    $websiteId = 1;
}
$website = $cms->getWebsite()->get($websiteId);
/*
 * A logged-in member should not normally reach this page.
 * Return them to the requested destination when possible.
 */
$isLoggedIn =
    !empty($_SESSION['id']) && strtolower((string) ($_SESSION['role'] ?? 'guest')) !== 'guest';

if ($isLoggedIn) {
    $returnTo = (string) ($_SESSION['return_to'] ?? '');

    if ($returnTo !== '') {
        unset($_SESSION['return_to']);

        header('Location: ' . $returnTo);
        exit();
    }

    header('Location: ' . DOC_ROOT . 'index/' . $websiteId);
    exit();
}

/*
 * Optional page-specific explanation supplied by the protected controller.
 *
 * Example:
 * $_SESSION['member_required_reason'] =
 *     'Sharing a ride activity requires a free TFOL account.';
 */
$reason = trim(
    (string) ($_SESSION['member_required_reason'] ?? 'This feature requires a free TFOL account.'),
);

/*
 * Keep return_to in the session. Login or registration will need it later.
 * The login query parameter bypasses the member-required redirect guard.
 */
$data = [
    'website_id' => $websiteId,
    'website' => $website,
    'reason' => $reason,
    'login_url' => DOC_ROOT . 'login/' . $websiteId . '?member_required=1',
    'register_url' => DOC_ROOT . 'register/' . $websiteId . '?member_required=1',
    'cancel_url' => DOC_ROOT . 'index/' . $websiteId,
];

echo $twig->render('member-required.html', $data);
