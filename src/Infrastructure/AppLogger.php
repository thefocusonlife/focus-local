<?php

namespace App\Infrastructure;

class AppLogger
{
    private static ?string $logFile = null;

    private static function logFilePath(): string
    {
        if (self::$logFile !== null) {
            return self::$logFile;
        }

        // Project root: /path/to/project (e.g., /opt/lampp/htdocs/focus-local)
        $projectRoot = dirname(__DIR__, 2);

        // Default log file: /path/to/project/logs/app.log
        $path = $projectRoot . '/logs/app.log';

        self::$logFile = $path;
        return $path;
    }

    public static function log(string $level, string $event, array $context = []): void
    {
        try {
            $timestamp = date('Y-m-d\TH:i:sP');

            // keep logs one-line and safe
            $contextString = self::formatContext($context);

            $line = sprintf(
                "[%s] %s %s %s\n",
                $timestamp,
                strtoupper($level),
                $event,
                $contextString,
            );

            $file = self::logFilePath();

            // Ensure directory exists (won't throw if it fails; we swallow below)
            $dir = dirname($file);
            if (!is_dir($dir)) {
                @mkdir($dir, 2775, true);
            }

            @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Never allow logging to break application flow
        }
    }

    private static function formatContext(array $context): string
    {
        if (empty($context)) {
            return '';
        }

        $pairs = [];

        foreach ($context as $key => $value) {
            if ($value === null) {
                $value = '';
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                $value = (string) $value;
            }

            // prevent newline injection in logs
            $value = str_replace(["\r", "\n"], ['\\r', '\\n'], $value);

            $pairs[] = $key . '=' . $value;
        }

        return implode(' ', $pairs);
    }
}
