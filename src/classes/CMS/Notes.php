<?php
namespace PhpBook\CMS;                                   // Declare namespace

class Notes
{           
    public $id;                                          // Store follow id
    public $note_type;
    public $from_id;
    public $from_name;
    public $to_id;
    public $to_name; 
    public $family_id;
    public $request;
    public $allow;                                          
    public $request_date;
    public $reply_date;
    public $notetype;
   
                

    protected $db;                                       // Holds ref to Database object

    public function __construct(Database $db)
    {
       
           $this->db = $db;                                 // Add ref to Database object
    }

    // Get note by member id
    public function get(int $id)
    {
        $arguments = array($id);
        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name, family_id, to_family_id, request,allow, request_date, reply_date
        FROM note
        WHERE from_id = :id";                  // SQL to get member
     return $this->db->runSQL($sql, $arguments)->fetch();  // Return member
    }
    
    // Get note by note_id (priary key)
    public function getById(int $id)
    {
        $arguments = array($id);
        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name, family_id, to_family_id, request,allow, request_date, reply_date
        FROM note
        WHERE id = :id";                  // SQL to get member
     return $this->db->runSQL($sql,$arguments)->fetch();  // Return member
    }

// Get details of all follows
public function getAllTo(int $id): array
{
    
    $arguments = array($id);
   

    $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name, family_id, to_family_id, request, allow, request_date, reply_date
    FROM note
    WHERE (to_id = :id)
    ORDER BY request_date DESC; ";                              // SQL to all notes by to_id
          
    return $this->db->runSQL($sql,$arguments)->fetchAll();      // Return all follows
}


// Get details of all follows
public function getAllFrom(int $id): array
{
$arguments = array($id);


$sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name, family_id, to_family_id, request, allow, request_date, reply_date
FROM note
WHERE (from_id = :id)
ORDER BY request_date DESC; ";                              // SQL to all notes by from_id
      
return $this->db->runSQL($sql,$arguments)->fetchAll();      // Return all follows
}

// Get details of all follows
public function getByAllowed(int $id): array
{
$arguments = array($id);


$sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name, family_id, to_family_id, request, allow, request_date, reply_date
FROM note
WHERE (from_id = :id AND allow == 1)
ORDER BY to_family_id DESC; ";                                // SQL to get all accepted follow requests by follow_id DESC (note: may want ASC)
      
return $this->db->runSQL($sql,$arguments)->fetchAll();      // Return all follows
}
// Get number of notifications
public function count(): int
{
    $sql = "SELECT COUNT(id) FROM note                  
            WHERE follow.f_id = $_SESSION[id];";        // SQL to count follows by Session_id
    return $this->db->runSQL($sql)->fetchColumn();      // Return menu count
}

    // Get details of all follows
    public function getAll(int $id): array
    {
     
        $arguments['id2'] = $arguments['id1'] = $id;
        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name, family_id, to_family_id, request, allow, request_date, reply_date
        FROM note
        WHERE (from_id = :id1
        or to_id = :id2)
        ORDER BY request_date DESC;";     
                                                            // since id is unique - maybe this is a duplicate;
        return $this->db->runSQL($sql,$arguments)->fetchAll();      // Return all follows
    }
    public function update(array $note): bool
    {
       
    
        try {                                            // Try to update data
            $this->db->beginTransaction();               // Start transaction
        
            $sql = "UPDATE note
                       SET website = :website, note_type = :note_type, from_id = :from_id, from_name = :from_name, to_id = :to_id, to_name = :to_name,
                        family_id = :family_id, to_family_id = :to_family_id, request = :request, allow = :allow, request_date = :request_date, reply_date = :reply_date
                           WHERE id = :id;";                   // SQL statement
            
              
            $this->db->runSQL($sql, $note); // Update story
            $this->db->commit();                         // Commit transaction
            return true;                                 // Update worked
        } catch (\PDOException $e) { 
            // If PDOException was raised
            $this->db->rollBack();                       // Rollback transaction
            
            if ($e->errorInfo[1] === 1062) {             // If an integrity constraint
                return false;                            // Return false
            } else {                                     // For all other reasons
              
                throw $e;
                                       // Re-throw exception
            }
        }
    }
                  
    
 // Create a new note
 public function create(array $note): bool
 {
     // $note['note_type] = 1;    removed to allow all note types, not just follow requests
    try {  
                                                               // Try to add new noter
         $sql = "INSERT INTO note (website, note_type, from_id, from_name, to_id, to_name, family_id, to_family_id, allow, request)
         VALUES (:website, :note_type, :from_id, :from_name, :to_id, :to_name, :family_id, :to_family_id, :allow, :request);";
                        
         $this->db->runSQL($sql, $note);  
         $this->db->commit();                         // Commit transaction                        // Run SQL
         return true;                                               // Return true
    } catch (\PDOException $e) {                                   // If PDOException thrown
         if ($e->errorInfo[1] === 1062) {                           // If error indicates duplicate entry
             return false; 
                                                 // Return false to indicate duplicate name
         }
        
         throw $e;                                                  // Re-throw exception
    }
    }
     // get last note by userid -- not using this function anywhere ?
     public function getLastNote(int $id) {

        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name, Family_id, to_family request,allow, request_date, reply_date
        FROM note
        WHERE to_id = :id                     
        ORDER BY from_id DESC
        LIMIT 1;";                          // Add GROUP BY clause
        return $this->db->runSQL($sql, [$id])->fetch();  // Return story
         }
}