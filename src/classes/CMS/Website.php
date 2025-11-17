<?php
namespace PhpBook\CMS;                                   // Declare namespace

class Website
{           
    public $id;
    public $sorttype;                                          // Store follow id
    public $name;
    public $image_file;
    public $alt;
    public $non_members;
    
                

    protected $db;                                       // Holds ref to Database object

    public function __construct(Database $db)
    {
        
        $this->db = $db;                                 // Add ref to Database object
    }

     // Get individual website by id
    public function get(int $id): array
    { 
        $arguments = array($id);
        $sql = "SELECT id, uber_id, sorttype, name, image_file, alt, non_members
                FROM website
                WHERE id = :id;";                       // SQL to get follow
       return $this->db->runSQL($sql, $arguments)->fetch();       // Return follow
    }
    public function getById(int $id)
    {
        $arguments = array($id);
        $sql = "SELECT id, uber_id, sorttype, name, image_file, alt, non_members
                FROM website
                WHERE id = :id";                              // SQL to get note by primary index
        return $this->db->runSQL($sql, $arguments)->fetch();  // Return member
    }
    // Get details of all website records
    public function getAll(): array
    {
        $sql = "SELECT id, uber_id, sorttype, name, image_file, alt, non_members
            FROM website
            ORDER BY id ASC;";
        return $this->db->runSQL($sql)->fetchAll();         // Return all follows
    }
 // Get total number of members
 public function count(): int
 {
     $sql = "SELECT COUNT(id) FROM website
             WHERE 1;";            // SQL to count number of members
     return $this->db->runSQL($sql)->fetchColumn();     // Run SQL and return count
 }    
public function update(array $website): bool
{
   
    try {                                            // Try to update data
        $this->db->beginTransaction();               // Start transaction
    
        $sql = "UPDATE website
                   SET id,sorttype, name, image_file, alt
                       WHERE id = :id;";                   // SQL statement
        $arguments = $website;     
        $this->db->runSQL($sql)->rowCount(); //          // run sql
        $this->db->commit();                             // Commit transaction
        return true;                                     // Update worked
    } catch (\PDOException $e) {                         // If PDOException was raised
        $this->db->rollBack();                           // Rollback transaction
        
        if ($e->errorInfo[1] === 1062) {             // If an integrity constraint
            return false;                            // Return false
        } else {                                     // For all other reasons
          
            throw $e;                                // Re-throw exception
        }
    }
}

 // Create a new note
 public function create(array $website): bool
 {
    try {  
        $this->db->beginTransaction();               // Start                                                        
         $sql = "INSERT INTO website (sorttype, name, image_file, alt)
         VALUES (:sorttype, :name, :image_file, :alt);";
         $this->db->runSQL($sql, $website);              // SQL to add new website record    
         $this->db->commit();                         // Commit ransaction
         return true;                                 // Return true
     } catch (\PDOException $e) { 
       
        $this->db->rollBack();                        // If PDOException thrown

         if ($e->errorInfo[1] === 1062) {             // If error indicates duplicate entry
             return false;                            // Return false to indicate duplicate name
         }
        
         throw $e;                                                  // Re-throw exception
     }
 }
 
// Delete existing note
public function delete(int $id): bool
{
    try {                                            // Try to delete website
        $sql = "DELETE FROM website
             WHERE id = :id;";                       // SQL to delete website
        $this->db->runSQL($sql, [$id]);              // Delete website
        return true;                                 // It worked, return true
    } catch (\PDOException $e) {                     // If exception was thrown   
        if ($e->errorInfo[1] === 1451) {             // If error is integrity constraint
            return false;                            // Return false indicating parent record
        } else {                                     // If any other exception
            throw $e;                                // Re-throw exception
        }
    }
}

}