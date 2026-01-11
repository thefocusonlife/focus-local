<?php
declare(strict_types=1);

function assertStoryOwnership(int $storyId, int $memberId): void
{
    if (isUberAdmin()) {
        return;
    }

    $storyOwnerId = getStoryOwnerId($storyId);

    if ($storyOwnerId !== $memberId) {
        denyAccess();
    }
}
