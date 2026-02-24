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
        return $this->db->runSql($sql, [$id])->fetch(); // Return sorttype
    }
    public function getAllSorttypes(): array
    {
        $sql = 'SELECT id, name
            FROM sorttype
            ORDER BY id ASC;';
        $rows = $this->db->runSql($sql)->fetchAll();

        // Always return an array
        return is_array($rows) ? $rows : [];
    }
    // Get details of all sorttypes
    public function getAll(): array
    {
        $sql = "SELECT id, name, sorttype, filter
                  FROM sorttype;"; // SQL to get all sorttypes
        return $this->db->runSql($sql)->fetchAll(); // Return all sorttypes
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
        $row = $this->db->runSql($sql, ['id' => $sorttypeId])->fetch();
        return (bool) $row;
    }

    public function exists(int $sorttypeId): bool
    {
        $sql = "
        SELECT 1
        FROM sorttype
        WHERE id = :id
        LIMIT 1
    ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $sorttypeId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function getDefaultIdForMenu(int $menuId): int
    {
        // menu_sorttype mapping removed; pick a safe default

        // Prefer 2 (Newest) if it exists
        $row = $this->db->runSql('SELECT id FROM sorttype WHERE id = 2 LIMIT 1;')->fetch();
        if ($row && isset($row['id'])) {
            return 2;
        }

        // Otherwise prefer 1 (Random) if it exists
        $row = $this->db->runSql('SELECT id FROM sorttype WHERE id = 1 LIMIT 1;')->fetch();
        if ($row && isset($row['id'])) {
            return 1;
        }

        // Otherwise fall back to the lowest id available
        $row = $this->db->runSql('SELECT id FROM sorttype ORDER BY id ASC LIMIT 1;')->fetch();
        return $row && isset($row['id']) ? (int) $row['id'] : 2;
    }

    public function resolveForMenu(int $menuId, int $preferredSorttypeId = 0): int
    {
        // 1) READ session override (authoritative)
        $websiteId = (int) ($_SESSION['website'] ?? 0);

        $override = (int) ($_SESSION['sort_override'][$websiteId][$menuId] ?? 0);

        if ($override > 0) {
            return $override;
        }

        // 2) Incoming / preferred (POST hint)
        if ($preferredSorttypeId > 0 && $this->isAllowedForMenu($menuId, $preferredSorttypeId)) {
            return $preferredSorttypeId;
        }

        // 3) Menu default
        return (int) $this->getDefaultIdForMenu($menuId);
    }
}
