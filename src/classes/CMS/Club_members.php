<?php

namespace PhpBook\CMS;

class Club_members
{
    /** @var mixed Database object with runSql() */
    protected $db;

    /**
     * @param mixed $db Database object with runSql()
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create a general club membership record.
     *
     * @param array $data
     */
    public function create(array $data)
    {
        $websiteId = (int) ($data['website_id'] ?? 0);

        $memberId = (int) ($data['member_id'] ?? 0);

        if ($memberId < 1 || $memberId === 2) {
            throw new \InvalidArgumentException('A registered member account is required.');
        }
        if ($websiteId < 1) {
            throw new \InvalidArgumentException(
                'A valid website ID is required to create a club member.',
            );
        }

        $membershipStatus = $data['membership_status'] ?? 'pending';
        $allowedStatuses = ['pending', 'active', 'inactive', 'declined'];

        if (!in_array($membershipStatus, $allowedStatuses, true)) {
            $membershipStatus = 'pending';
        }

        $parentalAuthorizationAccepted = !empty($data['parental_authorization_accepted']);

        $sql = "
            INSERT INTO club_members (
                website_id,
                member_id,
                first_name,
                last_name,
                email,
                phone,
                date_of_birth,
                guardian_name,
                guardian_email,
                guardian_phone,
                emergency_name,
                emergency_phone,
                emergency_relationship,
                parental_authorization_accepted,
                parental_authorization_accepted_at,
                membership_status,
                created_at,
                updated_at
            ) VALUES (
                :website_id,
                :member_id,
                :first_name,
                :last_name,
                :email,
                :phone,
                :date_of_birth,
                :guardian_name,
                :guardian_email,
                :guardian_phone,
                :emergency_name,
                :emergency_phone,
                :emergency_relationship,
                :parental_authorization_accepted,
                :parental_authorization_accepted_at,
                :membership_status,
                NOW(),
                NOW()
            )
        ";

        return $this->db->runSql($sql, [
            'website_id' => $websiteId,
            'member_id' => $memberId,
            'first_name' => trim($data['first_name'] ?? ''),
            'last_name' => trim($data['last_name'] ?? ''),
            'email' => trim($data['email'] ?? ''),
            'phone' => $this->nullableString($data['phone'] ?? null),
            'date_of_birth' => $this->nullableString($data['date_of_birth'] ?? null),
            'guardian_name' => $this->nullableString($data['guardian_name'] ?? null),
            'guardian_email' => $this->nullableString($data['guardian_email'] ?? null),
            'guardian_phone' => $this->nullableString($data['guardian_phone'] ?? null),
            'emergency_name' => $this->nullableString($data['emergency_name'] ?? null),
            'emergency_phone' => $this->nullableString($data['emergency_phone'] ?? null),
            'emergency_relationship' => $this->nullableString(
                $data['emergency_relationship'] ?? null,
            ),
            'parental_authorization_accepted' => $parentalAuthorizationAccepted ? 1 : 0,
            'parental_authorization_accepted_at' => $parentalAuthorizationAccepted
                ? date('Y-m-d H:i:s')
                : null,
            'membership_status' => $membershipStatus,
        ]);
    }

    /**
     * Get all club members for one website.
     *
     * @param int $websiteId
     */
    public function getAll(int $websiteId)
    {
        if ($websiteId < 1) {
            throw new \InvalidArgumentException(
                'A valid website ID is required to retrieve club members.',
            );
        }

        return $this->db->runSql(
            "
            SELECT *
            FROM club_members
            WHERE website_id = :website_id
            ORDER BY created_at DESC
            ",
            [
                'website_id' => $websiteId,
            ],
        );
    }

    /**
     * Get one club member belonging to a specific website.
     *
     * @param int $id
     * @param int $websiteId
     */
    public function getById(int $id, int $websiteId)
    {
        if ($id < 1 || $websiteId < 1) {
            return null;
        }

        $stmt = $this->db->runSql(
            "
        SELECT *
        FROM club_members
        WHERE id = :id
          AND website_id = :website_id
        LIMIT 1
        ",
            [
                'id' => $id,
                'website_id' => $websiteId,
            ],
        );

        if (!$stmt instanceof \PDOStatement) {
            return null;
        }

        $membership = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $membership !== false ? $membership : null;
    }

    /**
     * Get a club membership by TFOL member and website.
     */
    public function getByMemberId(int $memberId, int $websiteId)
    {
        if ($memberId < 1 || $memberId === 2 || $websiteId < 1) {
            return null;
        }

        $stmt = $this->db->runSql(
            "
        SELECT *
        FROM club_members
        WHERE member_id = :member_id
          AND website_id = :website_id
        LIMIT 1
        ",
            [
                'member_id' => $memberId,
                'website_id' => $websiteId,
            ],
        );

        if (!$stmt instanceof \PDOStatement) {
            return null;
        }

        $membership = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $membership !== false ? $membership : null;
    }
    /**
     * Update a member's own club membership application.
     *
     * Membership status is deliberately not changed here.
     *
     * @param array $data
     */
    public function updateByMemberId(int $memberId, int $websiteId, array $data)
    {
        if ($memberId < 1 || $memberId === 2 || $websiteId < 1) {
            throw new \InvalidArgumentException(
                'A registered member and valid website are required.',
            );
        }

        $parentalAuthorizationAccepted = !empty($data['parental_authorization_accepted']);

        $sql = "
        UPDATE club_members
        SET first_name = :first_name,
            last_name = :last_name,
            email = :email,
            phone = :phone,
            date_of_birth = :date_of_birth,
            guardian_name = :guardian_name,
            guardian_email = :guardian_email,
            guardian_phone = :guardian_phone,
            emergency_name = :emergency_name,
            emergency_phone = :emergency_phone,
            emergency_relationship = :emergency_relationship,
            parental_authorization_accepted =
                :parental_authorization_accepted,
            parental_authorization_accepted_at =
                :parental_authorization_accepted_at,
            updated_at = NOW()
        WHERE member_id = :member_id
          AND website_id = :website_id
        LIMIT 1
    ";

        return $this->db->runSql($sql, [
            'first_name' => trim($data['first_name'] ?? ''),
            'last_name' => trim($data['last_name'] ?? ''),
            'email' => trim($data['email'] ?? ''),
            'phone' => $this->nullableString($data['phone'] ?? null),
            'date_of_birth' => $this->nullableString($data['date_of_birth'] ?? null),
            'guardian_name' => $this->nullableString($data['guardian_name'] ?? null),
            'guardian_email' => $this->nullableString($data['guardian_email'] ?? null),
            'guardian_phone' => $this->nullableString($data['guardian_phone'] ?? null),
            'emergency_name' => $this->nullableString($data['emergency_name'] ?? null),
            'emergency_phone' => $this->nullableString($data['emergency_phone'] ?? null),
            'emergency_relationship' => $this->nullableString(
                $data['emergency_relationship'] ?? null,
            ),
            'parental_authorization_accepted' => $parentalAuthorizationAccepted ? 1 : 0,
            'parental_authorization_accepted_at' => $parentalAuthorizationAccepted
                ? date('Y-m-d H:i:s')
                : null,
            'member_id' => $memberId,
            'website_id' => $websiteId,
        ]);
    }

    /**
     * Change a club member's membership status.
     *
     * @param int $id
     * @param int $websiteId
     * @param string $membershipStatus
     */
    public function updateStatus(int $id, int $websiteId, string $membershipStatus)
    {
        $allowedStatuses = ['pending', 'active', 'inactive', 'declined'];

        if ($id < 1 || $websiteId < 1 || !in_array($membershipStatus, $allowedStatuses, true)) {
            throw new \InvalidArgumentException(
                'A valid member, website, and membership status are required.',
            );
        }

        return $this->db->runSql(
            "
            UPDATE club_members
            SET membership_status = :membership_status,
                updated_at = NOW()
            WHERE id = :id
              AND website_id = :website_id
            LIMIT 1
            ",
            [
                'membership_status' => $membershipStatus,
                'id' => $id,
                'website_id' => $websiteId,
            ],
        );
    }

    /**
     * Delete one club member belonging to a specific website.
     *
     * @param int $id
     * @param int $websiteId
     */
    public function delete(int $id, int $websiteId)
    {
        if ($id < 1 || $websiteId < 1) {
            throw new \InvalidArgumentException('Valid member and website IDs are required.');
        }

        return $this->db->runSql(
            "
            DELETE FROM club_members
            WHERE id = :id
              AND website_id = :website_id
            LIMIT 1
            ",
            [
                'id' => $id,
                'website_id' => $websiteId,
            ],
        );
    }

    /**
     * Convert an empty optional form value to NULL.
     *
     * @param mixed $value
     */
    private function nullableString($value)
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
