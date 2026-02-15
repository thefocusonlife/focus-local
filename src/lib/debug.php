<?php
declare(strict_types=1);

function debug_log(string $message): void
{
    if (defined('DEV') && DEV) {
        error_log($message);
    }
}
