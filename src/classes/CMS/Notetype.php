<?php
namespace PhpBook\CMS;                                   // Declare namespace

class Notetype
{                                                        // Define Notetype class
    public $id;                                          // id
    public $description;                                 // description
     
    
    protected $db;                                       // Holds ref to Database object

    public function __construct(Database $db)
    {
        $this->db = $db;                                 // Add ref to Database object
    }

     // Get individual notetype by id
    public function get(int $id)
    {
        $sql = "SELECT id, description
                  FROM notetype
                 WHERE id = :id;";                       // SQL to get notetype
        return $this->db->runSQL($sql, [$id])->fetch();  // Return notetype
    }

    // Get details of all notetypes
    public function getAll(): array
    {
        $sql = "SELECT id, description
                  FROM notetype;";                         // SQL to get all notetypes
        return $this->db->runSQL($sql)->fetchAll();      // Return all notetypes
    }
    
}