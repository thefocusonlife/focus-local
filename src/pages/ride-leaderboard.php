<?php

/**
 * RideLeaderboardController
 *
 * Public/non-admin controller for Website 44 monthly bicycle leaderboard.
 *
 * v1 rules:
 * - Website 44 only by default
 * - Monthly leaderboard, current month by default
 * - Exclude rides under 2 miles
 * - Shared rank ties using RANK()
 * - Member opt-in/opt-out via member.public_ride_leaderboard
 * - Output: rank, member name, total miles, total elevation, ride count
 */
class RideLeaderboardController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Public page action.
     *
     * Example route:
     *   /website/44/ride-leaderboard
     */
    public function index(): void
    {
        $websiteId = 44;
        $month = $_GET['month'] ?? date('Y-m');

        if (!$this->isValidMonth($month)) {
            http_response_code(400);
            echo 'Invalid month format. Use YYYY-MM.';
            return;
        }

        [$startDate, $endDate] = $this->getMonthDateRange($month);

        $leaderboard = $this->getMonthlyLeaderboard($websiteId, $startDate, $endDate);

        // Replace this with your app's normal view/render method if available.
        $this->renderLeaderboard($leaderboard, $month);
    }

    /**
     * JSON endpoint action.
     *
     * Useful if the Website 44 page will load the leaderboard asynchronously.
     *
     * Example route:
     *   /website/44/ride-leaderboard.json
     */
    public function json(): void
    {
        $websiteId = 44;
        $month = $_GET['month'] ?? date('Y-m');

        if (!$this->isValidMonth($month)) {
            $this->jsonResponse(
                [
                    'success' => false,
                    'error' => 'Invalid month format. Use YYYY-MM.',
                ],
                400,
            );
            return;
        }

        [$startDate, $endDate] = $this->getMonthDateRange($month);

        $this->jsonResponse([
            'success' => true,
            'website_id' => $websiteId,
            'month' => $month,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'minimum_distance_miles' => 2,
            'leaderboard' => $this->getMonthlyLeaderboard($websiteId, $startDate, $endDate),
        ]);
    }

    /**
     * Core leaderboard query.
     */
    private function getMonthlyLeaderboard(
        int $websiteId,
        string $startDate,
        string $endDate,
    ): array {
        $sql = "
            SELECT
                ranked.rank_miles,
                ranked.member_id,
                ranked.member_name,
                ranked.total_miles,
                ranked.total_elevation,
                ranked.ride_count
            FROM (
                SELECT
                    r.member_id,
                    m.name AS member_name,
                    ROUND(SUM(r.distance_miles), 1) AS total_miles,
                    ROUND(SUM(r.elevation_gain_ft), 0) AS total_elevation,
                    COUNT(*) AS ride_count,
                    RANK() OVER (ORDER BY SUM(r.distance_miles) DESC) AS rank_miles
                FROM ride r
                INNER JOIN member m
                    ON m.id = r.member_id
                WHERE r.website_id = :website_id
                  AND r.distance_miles >= 2
                  AND COALESCE(m.public_ride_leaderboard, 1) = 1
                  AND r.ride_date >= :start_date
                  AND r.ride_date < :end_date
                GROUP BY r.member_id, m.name
            ) ranked
            ORDER BY ranked.rank_miles ASC, ranked.member_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':website_id' => $websiteId,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function isValidMonth(string $month): bool
    {
        return preg_match('/^\\d{4}-\\d{2}$/', $month) === 1;
    }

    /**
     * Returns [inclusive start date, exclusive end date].
     */
    private function getMonthDateRange(string $month): array
    {
        $start = DateTime::createFromFormat('Y-m-d', $month . '-01');
        $end = clone $start;
        $end->modify('+1 month');

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    private function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Temporary simple render method.
     * Replace with your actual template/view system once wired into the app.
     */
    private function renderLeaderboard(array $leaderboard, string $month): void
    {
        header('Content-Type: text/html; charset=utf-8');

        echo '<section class="ride-leaderboard">';
        echo '<h2>Monthly Bicycle Club Leaderboard</h2>';
        echo '<p>' . htmlspecialchars($month) . ' · rides 2+ miles</p>';

        if (empty($leaderboard)) {
            echo '<p>No qualifying rides found for this month.</p>';
            echo '</section>';
            return;
        }

        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Rank</th>';
        echo '<th>Member</th>';
        echo '<th>Total Miles</th>';
        echo '<th>Total Elevation</th>';
        echo '<th>Ride Count</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($leaderboard as $row) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars((string) $row['rank_miles']) . '</td>';
            echo '<td>' . htmlspecialchars((string) $row['member_name']) . '</td>';
            echo '<td>' . number_format((float) $row['total_miles'], 1) . ' mi</td>';
            echo '<td>' . number_format((float) $row['total_elevation'], 0) . ' ft</td>';
            echo '<td>' . number_format((int) $row['ride_count']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</section>';
    }
}
