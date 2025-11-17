<?php
namespace PhpBook\CMS;                                   // Namespace declaration

class Menu
{
    protected $db;                                       // Holds ref to Database object

    public function __construct(Database $db)
    {
        $this->db = $db;                                 // Add ref to Database object
    }

    // Get individual menu
    public function get(int $id)
    {
        $sql = "SELECT id, website, name, description, navigation, account_id, seo_name, position 
                  FROM menu 
                 WHERE id = :id;";                       // SQL to get one menu
        return $this->db->runSQL($sql, [$id])->fetch();  // Return menu data
    }

     // Get individual menu
     public function getMenu(int $id)
     {
         $sql = "SELECT id, website, name, description, navigation, account_id, seo_name, position 
                   FROM menu 
                  WHERE account_id = :id;";                       // SQL to get one menu
         return $this->db->runSQL($sql, [$id])->fetch();  // Return menu data
     }
    
    // Get all menus
    public function getAll(): array
    {
        $sql = "SELECT id, website, name, navigation, account_id, seo_name, position 
                  FROM menu
                        
                  ORDER BY account_id ASC, position ASC;";                       // SQL to get all menus
        return $this->db->runSQL($sql)->fetchAll();      // Return all menus
    }
    public function getAll2(int $website, int $account_id): array
    {
        $arguments['website'] = $website;
        $arguments['account_id'] = $account_id;
        $sql = "SELECT id, website, name, navigation, account_id, seo_name, position 
                  FROM menu
                  WHERE website = :website AND account_id = :account_id      
                  ORDER BY website, account_id ASC, position ASC;";                       // SQL to get all menus
        return $this->db->runSQL($sql,$arguments)->fetchAll();      // Return all menus
    }

    // ADMIN METHODS
    // Get number of menus
    public function count(): int
    {
        $sql = "SELECT COUNT(id) FROM menu
                WHERE menu.account_id = $_SESSION[id];";        // SQL to count menus
        return $this->db->runSQL($sql)->fetchColumn();          // Return menu count
    }

    // Create new menu
    public function create(array $menu): bool
    {
       
        //$menu['account_id'] = 1;
       
        try {                                            // Try to create menu
            $sql = "INSERT INTO menu (website, name, description, navigation, account_id, seo_name, position) 
                    VALUES (:website, :name, :description, :navigation, :account_id, :seo_name, :position);"; // SQL to add new menu
            $this->db->runSQL($sql, $menu);              // Add new menu
            return true;                                 // It worked, return true
        } catch (\PDOException $e) {                     // If a exception was thrown
            if ($e->errorInfo[1] === 1062) {             // If error indicates duplicate entry
                return false;                            // Return false to indicate duplicate name
            } else {                                     // Otherwise
                throw $e;                                // Re-throw exception
            }
        }
    }

    // Update existing menu
    public function update(array $menu): bool
    {
        
        try {                                            // Try to update menu
            $sql = "UPDATE menu 
                    SET  website = :website, name = :name, description = :description, navigation = :navigation, account_id = :account_id, seo_name = :seo_name, position = :position 
                    WHERE id = :id;";                   // SQL to update menu
            $this->db->runSQL($sql, $menu);              // Update menu
            return true;                                 // It worked, return true
        } catch (\PDOException $e) {                     // If exception thrown
            if ($e->errorInfo[1] === 1062) {             // If duplicate entry
                return false;                            // Return false to indicate duplicate name
            } else {                                     // If any other exception
                throw $e;                                // Re-throw exception
            }
        }
    }

    // Delete existing menu
    public function delete(int $id): bool
    {
        try {                                            // Try to delete menu
            $sql = "DELETE FROM menu 
                 WHERE id = :id;";                       // SQL to delete menu
            $this->db->runSQL($sql, [$id]);              // Delete menu
            return true;                                 // It worked, return true
        } catch (\PDOException $e) {                     // If exception was thrown
            if ($e->errorInfo[1] === 1451) {             // If error is integrity constraint
                return false;                            // Return false indicating stories exist in this menu
            } else {                                     // If any other exception
                throw $e;                                // Re-throw exception
            }
        }
    }

}