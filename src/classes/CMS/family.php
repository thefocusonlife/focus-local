<?php
namespace PhpBook\CMS; // Declare namespace

class Family // Define Session class
{
    public $member_id; // Store member's id
    public $forename; // Store member's forename
    public $surname;
    public $role;
    public $account_id; // Store member's account_id
    public $website;

    protected $db; // Holds ref to Database object

    public function __construct(Database $db)
    {
        $this->db = $db; // Add ref to Database object
    }

    // Get individual member by id
    public function get(int $id)
    {
        $sql = "SELECT id,website, forename, surname, role, account_id
                  FROM member
                 WHERE id = :id;"; // SQL to get member
        return $this->db->runSQL($sql, [$id])->fetch(); // Return member
    }

    // Get details of all members
    public function getAll(): array
    {
        $sql = "SELECT id, website, forename, surname, role, account_id
                  FROM member;"; // SQL to get all members
        return $this->db->runSQL($sql)->fetchAll(); // Return all members
    }
    public function getByAllowed(array $id): array
    {
        $sql = "SELECT m.id, m.website, m.account_id, n.from_id, n.to_name,n.family_id, n.to_family_id, n.allow
                FROM member    AS m
                JOIN note as n ON m.id = n.family_id
                WHERE m.id = :id
                AND n.allow = 1"; // SQL to get all members

        return $this->db->runSQL($sql, $id)->fetchAll(); // Return all members
    }
}
