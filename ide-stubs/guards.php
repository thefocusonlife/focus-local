<?php
declare(strict_types=1);

/**
 * IDE-only stubs for guard helpers.
 * Real implementations live in /src/security/*.php
 */

function guardRequireLogin($cms): void {}
function guardRequireRole($cms, array $roles): void {}
function guardDenyRole($cms, array $roles): void {}
function guardCsrfOrDie(): void {}
function redirectWithFlash(string $path, string $message): void {}
