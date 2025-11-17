<?php
namespace PhpBook\CMS;                                   // Declare namespace

class Like
{                                                        // Define Member class
    protected $db;                                       // Holds reference to Database object

    public function __construct(Database $db)            // Runs when object created using class
    {
        $this->db = $db;                                 // Store Database object in $db property
    }

    public function get(array $like): bool
    {
        $sql = "SELECT COUNT(*)
                  FROM likes
                 WHERE story_id = :id 
                   AND member_id = :member_id";               // SQL
        
        return $this->db->runSQL($sql, $like)->fetchColumn();  // Run and return 1 or 0
    }

    public function create(array $like): bool
    {
        
        $sql = "INSERT INTO likes (story_id, member_id) 
                VALUES (:story_id, :member_id);"; 
        
        $this->db->runSQL($sql, $like);    
        
        return true;                                           // Return true
    }

    public function delete(array $like): bool
    {
        echo "Like -42";
      
        $sql = "DELETE FROM likes
                 WHERE story_id = :story_id 
                   AND member_id = :member_id;";               // SQL
        $this->db->runSQL($sql, $like);                        // Run SQL
        return true;                                           // Return true
    }

}