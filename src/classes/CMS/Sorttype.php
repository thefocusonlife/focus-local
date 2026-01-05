<?php
namespace PhpBook\CMS; // Declare namespace

class Sorttype // Define Session class
{
    public $id; // Store sorttype id
    public $name;
    public $sorttype;
    // Store member's account_id

    protected $db; // Holds ref to Database object

    public function __construct(Database $db)
    {
        $this->db = $db; // Add ref to Database object
    }

    // Get individual sorttype by id
    public function get(int $id)
    {
        $sql = "SELECT id, name, sorttype, filter
                  FROM sorttype
                 WHERE id = :id;"; // SQL to get sorttype
        return $this->db->runSQL($sql, [$id])->fetch(); // Return sorttype
    }

    // Get details of all sorttypes
    public function getAll(): array
    {
        $sql = "SELECT id, name, sorttype, filter
                  FROM sorttype;"; // SQL to get all sorttypes
        return $this->db->runSQL($sql)->fetchAll(); // Return all sorttypes
    }

    // Get allowed sorttypes for a specific menu (menu-scoped)
    public function getByMenu(int $menuId): array
    {
        // menu_sorttype mapping removed; return all sorttypes
        return $this->getAll();
    }

    public function isAllowedForMenu(int $menuId, int $sorttypeId): bool
    {
        // menu_sorttype mapping removed; any existing sorttype is allowed
        $sql = "SELECT 1
              FROM sorttype
             WHERE id = :id
             LIMIT 1;";
        $row = $this->db->runSQL($sql, ['id' => $sorttypeId])->fetch();
        return (bool) $row;
    }

    public function getDefaultIdForMenu(int $menuId): int
    {
        // menu_sorttype mapping removed; pick a safe default

        // Prefer 1 if it exists
        $row = $this->db->runSql('SELECT id FROM sorttype WHERE id = 1 LIMIT 1;')->fetch();
        if ($row && isset($row['id'])) {
            return 1;
        }

        // Otherwise prefer 2 if it exists
        $row = $this->db->runSql('SELECT id FROM sorttype WHERE id = 2 LIMIT 1;')->fetch();
        if ($row && isset($row['id'])) {
            return 2;
        }

        // Otherwise fall back to the lowest id available
        $row = $this->db->runSql('SELECT id FROM sorttype ORDER BY id ASC LIMIT 1;')->fetch();
        return $row && isset($row['id']) ? (int) $row['id'] : 1;
    }

    public function resolveForMenu(int $menuId, int $preferredSorttypeId): int
    {
        if ($preferredSorttypeId > 0 && $this->isAllowedForMenu($menuId, $preferredSorttypeId)) {
            return $preferredSorttypeId;
        }

        return $this->getDefaultIdForMenu($menuId);
    }
}
