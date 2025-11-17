<?php
namespace PhpBook\CMS;                                   // Declare namespace

class Pagelimit
{                                                        // Define Session class
    public $id;                                          // Store pagelimit id
    public $name;
    public $amount; 
                           

    protected $db;                                       // Holds ref to Database object

    public function __construct(Database $db)
    {
        $this->db = $db;                                 // Add ref to Database object
    }

     // Get individual pagelimit by id
    public function get(int $id)
    {
        $sql = "SELECT id, name, amount
                  FROM pagelimit
                 WHERE id = :id;";                       // SQL to get pagelimit
        return $this->db->runSQL($sql, [$id])->fetch();  // Return pagelimit
    }

    // Get details of all sorttypes
    public function getAll(): array
    {

        $sql = "SELECT id, name, amount
                  FROM pagelimit;";                         // SQL to get all pagelimits
      
        return $this->db->runSQL($sql)->fetchAll();      // Return all pagelimits
    }
    
}