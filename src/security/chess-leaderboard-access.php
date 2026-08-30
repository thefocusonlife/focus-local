<?php
declare(strict_types=1);

/**
 * Member IDs authorized to maintain Chess tournaments and leaderboard results.
 *
 * Add future designated Chess administrators here.
 *
 * @return int[]
 */
function chessLeaderboardAdminIds(): array
{
    return [1];
}

/**
 * Determine whether a member may maintain Chess tournament results.
 */
function isChessLeaderboardAdmin(int $viewerId): bool
{
    if ($viewerId < 1 || $viewerId === 2) {
        return false;
    }

    return in_array($viewerId, chessLeaderboardAdminIds(), true);
}
