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
        $sql = "
        SELECT st.id, st.name, st.sorttype, st.filter
        FROM menu_sorttype mst
        JOIN sorttype st ON st.id = mst.sorttype_id
        WHERE mst.menu_id = :menu_id
        ORDER BY mst.display_order, st.id
    ";

        return $this->db
            ->runSQL($sql, [
                'menu_id' => $menuId,
            ])
            ->fetchAll();
    }
    public function isAllowedForMenu(int $menuId, int $sorttypeId): bool
    {
        $sql = "
        SELECT 1
        FROM menu_sorttype
        WHERE menu_id = :menu_id
          AND sorttype_id = :sorttype_id
        LIMIT 1
    ";

        $row = $this->db
            ->runSQL($sql, [
                'menu_id' => $menuId,
                'sorttype_id' => $sorttypeId,
            ])
            ->fetch();

        return (bool) $row;
    }

    public function getDefaultIdForMenu(int $menuId): int
    {
        $sql = "
        SELECT sorttype_id
        FROM menu_sorttype
        WHERE menu_id = :menu_id
          AND is_default = 1
        LIMIT 1
    ";

        $row = $this->db
            ->runSQL($sql, [
                'menu_id' => $menuId,
            ])
            ->fetch();

        if ($row && isset($row['sorttype_id'])) {
            return (int) $row['sorttype_id'];
        }

        // Ultimate safety fallback (Mixed / Newest)
        return 9;
    }

    public function resolveForMenu(int $menuId, int $preferredSorttypeId): int
    {
        if ($preferredSorttypeId > 0 && $this->isAllowedForMenu($menuId, $preferredSorttypeId)) {
            return $preferredSorttypeId;
        }

        return $this->getDefaultIdForMenu($menuId);
    }
}
