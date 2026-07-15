<?php
namespace PhpBook\CMS;

class Ride
{
    protected $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function countByWebsiteId(int $websiteId): int
    {
        $sql = 'SELECT COUNT(*) FROM ride WHERE website_id = :website_id';
        return (int) $this->db->runSql($sql, [':website_id' => $websiteId])->fetchColumn();
    }

    public function getMembersByWebsiteId(int $websiteId): array
    {
        $sql = "
            SELECT DISTINCT
                m.id,
                CONCAT(COALESCE(m.forename, ''), ' ', COALESCE(m.surname, '')) AS member_name
            FROM member m
            INNER JOIN ride r ON r.member_id = m.id
            WHERE r.website_id = :website_id
            ORDER BY member_name
        ";

        return $this->db->runSql($sql, [':website_id' => $websiteId])->fetchAll();
    }

    public function getSummaryByWebsiteId(int $websiteId, array $filters = []): array
    {
        [$whereSql, $params] = $this->buildFilters($websiteId, $filters, 'r');

        $sql = "
            SELECT
                COUNT(*) AS total_rides,
                COALESCE(SUM(r.distance_miles), 0) AS total_miles,
                COALESCE(SUM(r.elevation_gain_ft), 0) AS total_elevation,
                COALESCE(AVG(r.distance_miles), 0) AS avg_distance,
                COALESCE(AVG(r.avg_speed_mph), 0) AS avg_speed,
                COUNT(DISTINCT r.member_id) AS active_riders
            FROM ride r
            WHERE {$whereSql}
        ";

        $summary = $this->db->runSql($sql, $params)->fetch();

        return $summary ?: [
                'total_rides' => 0,
                'total_miles' => 0,
                'total_elevation' => 0,
                'avg_distance' => 0,
                'avg_speed' => 0,
                'active_riders' => 0,
            ];
    }

    public function getReportRowsByWebsiteId(int $websiteId, array $filters = []): array
    {
        [$whereSql, $params] = $this->buildFilters($websiteId, $filters, 'r');

        $sql = "
            SELECT
                r.id,
                r.website_id,
                r.member_id,
                r.ride_date,
                r.start_time,
                r.title,
                r.ride_type,
                r.start_location,
                r.distance_miles,
                r.elapsed_minutes,
                r.elevation_gain_ft,
                r.avg_speed_mph,
                r.max_speed_mph,
                r.avg_power_watts,
                r.max_power_watts,
                r.avg_heart_rate,
                r.max_heart_rate,
                r.notes,
                r.gpx_file,
                r.gpx_uploaded,
                r.start_lat,
                r.start_lng,
                r.end_lat,
                r.end_lng,
                r.status,
                r.created,
                CONCAT(COALESCE(m.forename, ''), ' ', COALESCE(m.surname, '')) AS member_name
            FROM ride r
            LEFT JOIN member m ON r.member_id = m.id
            WHERE {$whereSql}
            ORDER BY r.ride_date DESC, r.created DESC
        ";

        return $this->db->runSql($sql, $params)->fetchAll();
    }

    protected function buildFilters(int $websiteId, array $filters, string $alias = 'r'): array
    {
        $where = ["{$alias}.website_id = :website_id"];
        $params = [':website_id' => $websiteId];

        $fromDate = trim((string) ($filters['from_date'] ?? ''));
        $toDate = trim((string) ($filters['to_date'] ?? ''));
        $memberId = trim((string) ($filters['member_id'] ?? ''));
        $rideType = trim((string) ($filters['ride_type'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $gpxUploaded = trim((string) ($filters['gpx_uploaded'] ?? ''));

        if ($fromDate !== '') {
            $where[] = "{$alias}.ride_date >= :from_date";
            $params[':from_date'] = $fromDate;
        }

        if ($toDate !== '') {
            $where[] = "{$alias}.ride_date <= :to_date";
            $params[':to_date'] = $toDate;
        }

        if ($memberId !== '') {
            $where[] = "{$alias}.member_id = :member_id";
            $params[':member_id'] = (int) $memberId;
        }

        if ($rideType !== '') {
            $where[] = "{$alias}.ride_type = :ride_type";
            $params[':ride_type'] = $rideType;
        }

        if ($status !== '') {
            $where[] = "{$alias}.status = :status";
            $params[':status'] = $status;
        }

        if ($gpxUploaded === '0' || $gpxUploaded === '1') {
            $where[] = "{$alias}.gpx_uploaded = :gpx_uploaded";
            $params[':gpx_uploaded'] = (int) $gpxUploaded;
        }

        return [implode(' AND ', $where), $params];
    }
    /**
     * Get one ride belonging to Website 44.
     */
    public function getOne(int $rideId, int $websiteId = 44): array
    {
        $sql = "
        SELECT *
        FROM ride
        WHERE id = :id
          AND website_id = :website_id
        LIMIT 1
    ";

        $statement = $this->db->runSql($sql, [
            'id' => $rideId,
            'website_id' => $websiteId,
        ]);

        return $statement->fetch() ?: [];
    }

    /**
     * Update the editable fields for a ride.
     */
    public function update(int $rideId, int $websiteId, array $ride): bool
    {
        $sql = "
        UPDATE ride
        SET ride_date = :ride_date,
            start_time = :start_time,
            title = :title,
            ride_type = :ride_type,
            start_location = :start_location,
            distance_miles = :distance_miles,
            elapsed_minutes = :elapsed_minutes,
            elevation_gain_ft = :elevation_gain_ft,
            avg_speed_mph = :avg_speed_mph,
            avg_power_watts = :avg_power_watts,
            avg_heart_rate = :avg_heart_rate,
            notes = :notes,
            location = :location
        WHERE id = :id
          AND website_id = :website_id
    ";

        $statement = $this->db->runSql($sql, [
            'ride_date' => $ride['ride_date'],
            'start_time' => $ride['start_time'],
            'title' => $ride['title'],
            'ride_type' => $ride['ride_type'],
            'start_location' => $ride['start_location'],
            'distance_miles' => $ride['distance_miles'],
            'elapsed_minutes' => $ride['elapsed_minutes'],
            'elevation_gain_ft' => $ride['elevation_gain_ft'],
            'avg_speed_mph' => $ride['avg_speed_mph'],
            'avg_power_watts' => $ride['avg_power_watts'],
            'avg_heart_rate' => $ride['avg_heart_rate'],
            'notes' => $ride['notes'],
            'location' => $ride['location'],
            'id' => $rideId,
            'website_id' => $websiteId,
        ]);

        return $statement->rowCount() >= 0;
    }
}
