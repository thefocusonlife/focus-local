<?php
namespace PhpBook\CMS;                                   // Declare namespace

class Sorttype
{                                                        // Define Session class
    public $id;                                          // Store sorttype id
    public $name;
    public $sorttype; 
                            // Store member's account_id

    protected $db;                                       // Holds ref to Database object

    public function __construct(Database $db)
    {
        $this->db = $db;                                 // Add ref to Database object
    }

     // Get individual sorttype by id
    public function get(int $id)
    {
        $sql = "SELECT id, name, sorttype, filter
                  FROM sorttype
                 WHERE id = :id;";                       // SQL to get sorttype
        return $this->db->runSQL($sql, [$id])->fetch();  // Return sorttype
    }

    // Get details of all sorttypes
    public function getAll(): array
    {
        $sql = "SELECT id, name, sorttype, filter
                  FROM sorttype;";                         // SQL to get all sorttypes
        return $this->db->runSQL($sql)->fetchAll();        // Return all sorttypes
    }
    
}