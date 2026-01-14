<?php
declare(strict_types=1);

require_once __DIR__ . '/guard.php';

/**
 * Returns the author/owner member_id for a story.
 * Adjust the lookup to your data model if needed.
 */
function getStoryOwnerId($cms, int $storyId): int
{
    $story = $cms->getStory()->getById($storyId);
    if (!$story || !isset($story['member_id'])) {
        http_response_code(404);
        exit('Story not found');
    }
    return (int) $story['member_id'];
}

/**
 * Ownership guard:
 * - Uber can bypass
 * - Otherwise story.member_id must match current member id
 */
function assertStoryOwnership($cms, int $storyId, int $memberId, bool $isUber = false): void
{
    if ($isUber) {
        return;
    }

    $storyOwnerId = getStoryOwnerId($cms, $storyId);

    if ($storyOwnerId !== $memberId) {
        // Redirect deny (consistent with other guard behavior)
        denyAccess(null, 403);
    }
}
