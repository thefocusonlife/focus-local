<?php
final class AnonIdService
{
    public const COOKIE_NAME = 'anon_id';

    public function getFromCookie(array $cookies): ?string
    {
        $raw = $cookies[self::COOKIE_NAME] ?? null;
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        // Allow only a safe format you control (example: "a2_" + hex)
        if (!preg_match('/^a2_[a-f0-9]{32}$/', $raw)) {
            return null;
        }

        return $raw;
    }

    public function shouldMint(?int $userId, ?string $existingAnonId): bool
    {
        // Never mint for logged-in users; never mint if already present
        return $userId === null && $existingAnonId === null;
    }

    public function mint(): string
    {
        return 'a2_' . bin2hex(random_bytes(16)); // 32 hex chars
    }

    public function buildSetCookieHeader(string $anonId, bool $isHttps): string
    {
        // Keep this conservative and predictable.
        // SameSite=Lax avoids deep-link/login flows breaking in most cases.
        // Secure only when HTTPS.
        $parts = [];
        $parts[] = self::COOKIE_NAME . '=' . rawurlencode($anonId);
        $parts[] = 'Path=/';
        $parts[] = 'Max-Age=' . 60 * 60 * 24 * 365; // 1 year
        $parts[] = 'HttpOnly';
        $parts[] = 'SameSite=Lax';
        if ($isHttps) {
            $parts[] = 'Secure';
        }

        return 'Set-Cookie: ' . implode('; ', $parts);
    }
}
