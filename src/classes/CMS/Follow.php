<?php
namespace PhpBook\CMS; // Declare namespace

class Follow
{
    public $f_id; // Store follow id
    public $from_id;
    public $to_id;
    public $from_account_id; // Store follow id
    public $to_account_id;
    public $status;
    public $request_date;

    protected $db; // Holds ref to Database object

    public function __construct(Database $db)
    {
        $this->db = $db; // Add ref to Database object
    }

    // Get individual follow by id
    public function get(int $id)
    {
        $sql = "SELECT f_id, from_id, to_id, from_account_id, to_account_id, status, request_date
                  FROM follow
                 WHERE f_id = :id;"; // SQL to get follow
        return $this->db->runSQL($sql, [$id])->fetch(); // Return follow
    }

    // Get details of all follows
    public function getAll(): array
    {
        $sql = "SELECT f_id, from_id, to_id, from_account_id, to_account_id, status, request_date
                  FROM follow
                  WHERE 1;"; // SQL to get all follows
        return $this->db->runSQL($sql)->fetchAll(); // Return all follows
    }
    // Get number of follows
    public function count(): int
    {
        $sql = "SELECT COUNT(f_id) FROM follow
                WHERE follow.f_id = $_SESSION[id];"; // SQL to count follows
        return $this->db->runSQL($sql)->fetchColumn(); // Return menu count
    }
}
