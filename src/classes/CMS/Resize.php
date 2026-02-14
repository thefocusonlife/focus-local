<?php
// Don't believe this is being used --making it fail on purpose

namespace PhpBook\CMS; // Declare namespace
declare(strict_types=1); // Use strict types
exit();
if (!$id) {
    // If no valid id
    //  include APP_ROOT . '/src/pages/page-not-found.php';     // Page not found
}
class Resize // Define Session class
{
    public $member_id; // Store member's id
    public $forename;
    public $surname;
    public $role; // Store member's forename
    public $account_id; // Store member's account_id

    protected $db; // Holds ref to Database object

    public function __construct(Database $db)
    {
        $this->db = $db; // Add ref to Database object
    }

    // Get individual member by id
    public function get(int $id)
    {
        $sql = "SELECT id, forename, surname, role, account_id
                  FROM member
                 WHERE id = :id;"; // SQL to get member
        return $this->db->runSql($sql, [$id])->fetch(); // Return member
    }

    // Get details of all members
    public function getAll(): array
    {
        $sql = "SELECT id, forename, surname, role, account_id
                  FROM member;"; // SQL to get all members
        return $this->db->runSql($sql)->fetchAll(); // Return all members
    }
}
