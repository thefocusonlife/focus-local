<?php
/** @noinspection PhpUndefinedFunctionInspection */
/**
 * Intelephense note:
 * getAnonId() is defined in src/identity.php and loaded via bootstrap.
 * We guard with function_exists() at runtime.
 */
final class RequestContext
{
    private static ?string $traceId = null;
    private static ?string $anonId = null;

    public static function traceId(): string
    {
        if (self::$traceId !== null) {
            return self::$traceId;
        }

        $hdr = $_SERVER['HTTP_X_TRACE_ID'] ?? null;
        $param = $_REQUEST['trace_id'] ?? null;
        $candidate = $hdr ?: $param;

        if (is_string($candidate) && preg_match('/^[A-Za-z0-9_\-\.]{8,80}$/', $candidate)) {
            self::$traceId = $candidate;
        } else {
            self::$traceId = 'trc_' . bin2hex(random_bytes(16));
        }

        if (!headers_sent()) {
            header('X-Trace-Id: ' . self::$traceId);
        }

        return self::$traceId;
    }

    public static function anonId(): string
    {
        if (self::$anonId !== null) {
            return self::$anonId;
        }

        // Prefer your canonical helper if available.
        if (function_exists('getAnonId')) {
            self::$anonId = getAnonId();
            return self::$anonId;
        }

        // Fallback (should rarely happen if bootstrap loads identity.php)
        $candidate = $_COOKIE['anon_id'] ?? null;
        if (is_string($candidate) && preg_match('/^[A-Za-z0-9_\-]{8,80}$/', $candidate)) {
            self::$anonId = $candidate;
            return self::$anonId;
        }

        self::$anonId = 'anon_' . bin2hex(random_bytes(16));
        return self::$anonId;
    }
}
