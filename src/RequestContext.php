<?php
final class RequestContext
{
    private static ?string $traceId = null;

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
}
