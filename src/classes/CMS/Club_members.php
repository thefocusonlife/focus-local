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
     * @param array $data
     */
    public function create($data)
    {
        $sql = "
            INSERT INTO club_members (
                website_id,
                first_name,
                last_name,
                address1,
                address2,
                city,
                state,
                zip,
                email,
                phone,
                emergency_name,
                emergency_phone,
                emergency_relationship,
                primary_ride_type,
                other_ride_type,
                riding_level,
                preferred_distance,
                medical_notes,
                liability_release_accepted,
                liability_release_accepted_at,
                created_at
            ) VALUES (
                :website_id,
                :first_name,
                :last_name,
                :address1,
                :address2,
                :city,
                :state,
                :zip,
                :email,
                :phone,
                :emergency_name,
                :emergency_phone,
                :emergency_relationship,
                :primary_ride_type,
                :other_ride_type,
                :riding_level,
                :preferred_distance,
                :medical_notes,
                :liability_release_accepted,
                :liability_release_accepted_at,
                NOW()
            )
        ";

        return $this->db->runSql($sql, [
            'website_id' => $data['website_id'] ?? 1,
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'address1' => $data['address1'] ?? '',
            'address2' => $data['address2'] ?? '',
            'city' => $data['city'] ?? '',
            'state' => $data['state'] ?? '',
            'zip' => $data['zip'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'emergency_name' => $data['emergency_name'] ?? '',
            'emergency_phone' => $data['emergency_phone'] ?? '',
            'emergency_relationship' => $data['emergency_relationship'] ?? '',
            'primary_ride_type' => $data['primary_ride_type'] ?? '',
            'other_ride_type' => $data['other_ride_type'] ?? '',
            'riding_level' => $data['riding_level'] ?? '',
            'preferred_distance' => $data['preferred_distance'] ?? '',
            'medical_notes' => $data['medical_notes'] ?? '',
            'liability_release_accepted' => !empty($data['liability_release_accepted']) ? 1 : 0,
            'liability_release_accepted_at' => !empty($data['liability_release_accepted'])
                ? date('Y-m-d H:i:s')
                : null,
        ]);
    }

    public function getAll($website_id = 44)
    {
        return $this->db->runSql(
            "
            SELECT *
            FROM club_members
            WHERE website_id = :website_id
            ORDER BY created_at DESC
        ",
            [
                'website_id' => $website_id,
            ],
        );
    }
    /**
     * @param int $id
     */
    public function getById($id)
    {
        $rows = $this->db->runSql(
            "
            SELECT *
            FROM club_members
            WHERE id = :id
            LIMIT 1
        ",
            [
                'id' => $id,
            ],
        );

        return $rows[0] ?? null;
    }
    /**
     * @param int $id
     */
    public function delete($id)
    {
        return $this->db->runSql(
            "
            DELETE FROM club_members
            WHERE id = :id
            LIMIT 1
        ",
            [
                'id' => $id,
            ],
        );
    }
}
