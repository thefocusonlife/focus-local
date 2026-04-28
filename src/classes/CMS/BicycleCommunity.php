<?php
declare(strict_types=1);

namespace CMS;

class BicycleCommunity
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getByWebsiteId(int $websiteId = 44, int $limit = 8): array
    {
        $limit = max(1, (int) $limit);

        $sql = "
            SELECT
                bc.id,
                bc.website_id,
                bc.member_id,
                bc.title,
                bc.content_type,
                bc.url,
                bc.summary,
                bc.content,
                bc.status,
                bc.created,
                bc.updated,
                CONCAT(m.forename, ' ', m.surname) AS member_name
            FROM bicycle_community bc
            LEFT JOIN member m
                ON m.id = bc.member_id
            WHERE bc.website_id = :website_id
              AND bc.status = 'published'
            ORDER BY bc.created DESC, bc.id DESC
            LIMIT {$limit}
        ";

        return $this->db
            ->runSql($sql, [
                'website_id' => $websiteId,
            ])
            ->fetchAll();
    }
}
