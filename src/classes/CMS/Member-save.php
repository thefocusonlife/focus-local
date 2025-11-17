<?php
namespace PhpBook\CMS;                                   // Namespace declaration

class Member
{
    protected $db;                                       // Holds ref to Database object

    public function __construct(Database $db)
    {
        $this->db = $db;                                 // Add ref to Database object
    }

     // Get individual member by id
    public function get(int $id)
    {
        $sql = "SELECT id, website, forename, surname, email, joined, picture, role, account_id, photo_limit, agegroup, plan, pagelimit,sorttype, publik, termsok
                  FROM member
                 WHERE id = :id;";                       // SQL to get member
        return $this->db->runSQL($sql, [$id])->fetch();  // Return member
    }

    // Get details of all members
    public function getAll(): array
    {
        $sql = "SELECT id, website, forename, surname, email, joined, picture, role, account_id, photo_limit, agegroup, plan, pagelimit,sorttype,  publik, termsok
                  FROM member;";                         // SQL to get all members
        return $this->db->runSQL($sql)->fetchAll();      // Return all members
    }
    // Get details of all members by website
    public function getAll2(int $id): array
    {
        $arguments = array($id);
        $sql = "SELECT id, website, forename, surname, email, joined, picture, role, account_id, photo_limit, agegroup, plan, pagelimit,sorttype,  publik, termsok
                  FROM member
                  WHERE website = :id;";                         // SQL to get all members
        return $this->db->runSQL($sql,$arguments)->fetchAll();      // Return all members
    }
    public function getAll3(int $id): array
    {
        $arguments = array($id);
        $sql = "SELECT id, website, forename, surname, email, joined, picture, role, account_id, photo_limit, agegroup, plan, pagelimit,sorttype,  publik, termsok
                  FROM member
                  WHERE account_id = :id;";                         // SQL to get all members
        return $this->db->runSQL($sql,$arguments)->fetchAll();      // Return all members
    }

    // Get individual member data using their email
    public function getIdByEmail(string $email)
    {
        $sql = "SELECT id
                  FROM member
                 WHERE email = :email;";                         // SQL query to get member id
        return $this->db->runSQL($sql, [$email])->fetchColumn(); // Run SQL and return member id
    }

    //get last id
    public function getLastId() :array {
    $sql = "SELECT id
    FROM member
    WHERE 1                     
    ORDER BY id DESC
    LIMIT 1;";                          // Add GROUP BY clause
    return $this->db->runSQL($sql)->fetchAll();  // Return last member id
    }

    // Login: returns member data if authenticated, false if not
    public function login(string $email, int $website, string $password) 
    {
        $sql = "SELECT id, website, forename, surname, joined, email, password, picture, role, account_id, photo_limit, agegroup, plan, pagelimit,sorttype,  publik, termsok
                  FROM member 
                 WHERE email = :email
                 AND website =:website;";                            // SQL to collect member data
        $member = $this->db->runSQL($sql, [$arguments])->fetch();       // Run SQL
        if (!$member) {                                             // If no member found
            return false;                                           // Return false
        }                                                           // Otherwise
        $authenticated = password_verify($password, $member['password']); // Did password match
        return ($authenticated ? $member : false);                  // Return whether password matched
    }

    // Get total number of members
    public function count(): int
    {
        $sql = "SELECT COUNT(id) FROM member
                WHERE member.account_id = $_SESSION[id];";            // SQL to count number of members
        return $this->db->runSQL($sql)->fetchColumn();     // Run SQL and return count
    }

    // Create a new member
    public function create(array $member): bool
    {
        $member['password'] = password_hash($member['password'], PASSWORD_DEFAULT);  // Hash password
      
        try {                                                          // Try to add member
            $sql = "INSERT INTO member (website, forename, surname, email, password, account_id, photo_limit, agegroup, plan, pagelimit,sorttype,  publik, termsok) 
                    VALUES (:website, :forename, :surname, :email, :password, :account_id, :photo_limit, :agegroup, :plan, :pagelimit, :sorttype, :publik, :termsok;)"; // SQL to add member  
            
            $this->db->runSQL($sql, $member);                          // Run SQL
            return true;                                               // Return true
        } catch (\PDOException $e) {                                   // If PDOException thrown
            if ($e->errorInfo[1] === 1062) {                           // If error indicates duplicate entry
                return false; 
            } else {                                        // Return false to indicate duplicate name
             throw $e; 
            }                                                 // Re-throw exception
        }
    }

    // Update an existing member
    public function update(array $member): bool
    {

        unset($member['joined'],  $member['picture']);               // Remove joined and member from array
        try { 
            $this->db->beginTransaction();               // Start                                                         // Try to update member
            $sql = "UPDATE member 
                       SET website = :website, forename = :forename, surname = :surname, email = :email, role = :role, 
                       account_id =:account_id, photo_limit = :photo_limit, agegroup = :agegroup, plan = :plan,
                       pagelimit = :pagelimit,sorttype,  publik, termsok = :;"; 
                       WHERE id = :id;"; 
                       // SQL to update member
            $this->db->runSQL($sql, $member); 
            $this->db->commit();                         // Commit transaction  
         
            return true;                                             // Return true
        } catch (\PDOException $e) {                                 // If PDOException thrown
            if ($e->errorInfo[1] == 1062) {                          // If a duplicate (email in use)
                return false;                                        // Return false
            } else {  
                throw $e;                                                  // Any other error
            }
        }                                                   // Re-throw exception
    }

   
    // Upload member profile image
    public function pictureCreate(int $id, string $filename, string $temporary, string $destination): bool
    {
        if ($temporary) {                                    // If an image was uploaded
            $temporary = realpath($temporary);               // Needed for use on Windows
            $image = new \Imagick($temporary);               // Object to represent image
            $image->cropThumbnailImage(350, 350);            // Create cropped image
            $saved = $image->writeImage($destination);       // Save file
            if ($saved == false) {                           // If save failed
                throw new \Exception('Unable to save image'); // Throw an exception
            }
        }

        $sql = "UPDATE member 
                   SET picture = :picture
                 WHERE id = :id;";                                  // SQL to create picture
        $this->db->runSQL($sql, ['id'=>$id, 'picture'=>$filename]); // Run SQL pass in user id and filename
        return true;                                                // Done return true
    }

    // Delete member profile image
    public function pictureDelete(array $member, string $uploads): bool
    {
        $path = $uploads . $member['picture'];           // Create path for image
        $unlink = unlink($path);                         // Delete image file
        if ($unlink === false) {                         // If failed throw exception
            throw new \Exception('Unable to delete image or image is missing');
        }
        $sql = "UPDATE member 
                   SET picture = null
                 WHERE id = :id;";                       // SQL to set picture to null
        $this->db->runSQL($sql, ['id'=>$member['id']]);  // Run SQL
        return true;                                     // Return true
    }

    // Update member password
    public function passwordUpdate(int $id, string $password): bool
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);          // Hash the password
        $sql = 'UPDATE member 
                   SET password = :password 
                 WHERE id = :id';                                    // SQL to update password
        $this->db->runSQL($sql, ['id' => $id, 'password' => $hash]); // Run SQL
        return true;                                                 // Return true
    }

    // Get age groups
    public function getAgegroups(): array
    {
        $sql = "SELECT age, agegroup
                  FROM agegroup;";                         // SQL to get all agegroup records
        return $this->db->runSQL($sql)->fetchAll();        // Return all agegroup records
    }

    // Get Plans
    public function getPlans(): array
    {
        $sql = "SELECT id, name, description, photolimit, monthly, annual
                  FROM plan;";                           // SQL to get all plan records
        return $this->db->runSQL($sql)->fetchAll();      // Return all plan records
    }

    // Get photolimit
    public function getphotolimit(int $id)
    {
        $sql = "SELECT photolimit
                  FROM plan
                  WHERE id = :id;";                           // SQL to get photolimit
        return $this->db->runSQL($sql, [$id])->fetch();       // plan photolimit   /
        
    }

    // Get Follow
    public function getFollows(): array
    {
        $sql = "SELECT to_id, to_account_id, from_id, from_account_id, status, request_date, response_date
                  FROM follow;";                         // SQL to get all plan records
        return $this->db->runSQL($sql)->fetchAll();      // Return all plan records
    }
   
    
}