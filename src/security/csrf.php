<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    throw new RuntimeException('CSRF helper requires an active session.');
}

/**
 * Get or create a CSRF token for a named form/action.
 */
function csrf_token(string $formKey): string
{
    $formKey = trim($formKey);
    if ($formKey === '') {
        throw new InvalidArgumentException('CSRF form key cannot be empty.');
    }

    if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }

    if (
        empty($_SESSION['csrf_tokens'][$formKey]) ||
        !is_string($_SESSION['csrf_tokens'][$formKey])
    ) {
        $_SESSION['csrf_tokens'][$formKey] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_tokens'][$formKey];
}

/**
 * Validate submitted CSRF token for a named form/action.
 */
function csrf_validate(string $formKey, ?string $submittedToken): bool
{
    $formKey = trim($formKey);
    if ($formKey === '' || $submittedToken === null || $submittedToken === '') {
        return false;
    }

    $sessionToken = $_SESSION['csrf_tokens'][$formKey] ?? null;
    if (!is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $submittedToken);
}

/**
 * Rotate token after successful POST handling.
 * Good for one-time-ish usage without breaking validation rerenders.
 */
function csrf_rotate(string $formKey): void
{
    $formKey = trim($formKey);
    if ($formKey === '') {
        return;
    }

    if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }

    $_SESSION['csrf_tokens'][$formKey] = bin2hex(random_bytes(32));
}
