<?php
final class RequestContext
{
    private static ?string $traceId = null;

    public static function traceId(): string
    {
        if (self::$traceId !== null) {
            return self::$traceId;
        }

        // 1) Try header from client
        $hdr = $_SERVER['HTTP_X_TRACE_ID'] ?? null;

        // 2) Try form/query param (for non-JS posts)
        $param = $_REQUEST['trace_id'] ?? null;

        $candidate = $hdr ?: $param;

        if (is_string($candidate) && preg_match('/^[A-Za-z0-9_\-\.]{8,80}$/', $candidate)) {
            self::$traceId = $candidate;
        } else {
            // Generate new trace id
            self::$traceId = 'trc_' . bin2hex(random_bytes(16));
        }

        // Return to client so JS can reuse it
        header('X-Trace-Id: ' . self::$traceId);

        return self::$traceId;
    }
}
