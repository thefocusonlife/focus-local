<?php
namespace PhpBook\CMS; // Namespace declaration

class Menu
{
    protected $db; // Holds ref to Database object

    public function __construct(Database $db)
    {
        $this->db = $db; // Add ref to Database object
    }

    // Get individual menu
    public function get(int $id)
    {
        $sql = "SELECT id, website, name, description, navigation, account_id, seo_name, position
                  FROM menu
                 WHERE id = :id;"; // SQL to get one menu
        return $this->db->runSQL($sql, [$id])->fetch(); // Return menu data
    }

    public function getBySlug(int $websiteId, int $accountId, string $slug): ?array
    {
        $sql = "SELECT id, master_id, website, name, description, navigation, account_id, seo_name, position
            FROM menu
            WHERE website = :website
              AND account_id = :account_id
              AND seo_name = :slug
            LIMIT 1";
        $row = $this->db
            ->runSQL($sql, [
                'website' => $websiteId,
                'account_id' => $accountId,
                'slug' => $slug,
            ])
            ->fetch();

        return $row ?: null;
    }

    // Get all menus
    public function getAll(): array
    {
        $sql = "SELECT id, website, name, navigation, account_id, seo_name, position
                  FROM menu

                  ORDER BY account_id ASC, position ASC;"; // SQL to get all menus
        return $this->db->runSQL($sql)->fetchAll(); // Return all menus
    }
    public function getAll2(?int $website, ?int $account_id): array
    {
        $website = (int) ($website ?? 1);
        $account_id = (int) ($account_id ?? 0);

        $arguments['website'] = $website;
        $arguments['account_id'] = $account_id;

        $sql = "SELECT id, website, name, navigation, account_id, seo_name, position
              FROM menu
             WHERE website = :website AND account_id = :account_id
             ORDER BY website, account_id ASC, position ASC;";

        return $this->db->runSQL($sql, $arguments)->fetchAll();
    }

    public function getAllByWebsite(int $websiteId): array
    {
        $websiteId = (int) $websiteId;
        if ($websiteId <= 0) {
            $websiteId = 1;
        }

        $sql = "SELECT id, website, name, navigation, account_id, seo_name, position
              FROM menu
             WHERE website = :website
             ORDER BY account_id ASC, position ASC;";

        return $this->db->runSQL($sql, ['website' => $websiteId])->fetchAll();
    }

    public function getFirstForWebsite(int $websiteId): ?int
    {
        $sql = "
        SELECT id
        FROM menu
        WHERE website_id = :website_id
        ORDER BY id ASC
        LIMIT 1
    ";

        $row = $this->db
            ->runSQL($sql, [
                'website_id' => $websiteId,
            ])
            ->fetch();

        return $row ? (int) $row['id'] : null;
    }

    // ADMIN METHODS
    // Get number of menus
    public function count(): int
    {
        $sql = "SELECT COUNT(id) FROM menu
                WHERE menu.account_id = $_SESSION[id];"; // SQL to count menus
        return $this->db->runSQL($sql)->fetchColumn(); // Return menu count
    }

    // Create new menu
    public function create(array $menu): bool
    {
        //$menu['account_id'] = 1;

        try {
            // Try to create menu
            $sql = "INSERT INTO menu (website, name, description, navigation, account_id, seo_name, position)
            VALUES (:website, :name, :description, :navigation, :account_id, :seo_name, :position);";

            $params = [
                'website' => $menu['website'],
                'name' => $menu['name'],
                'description' => $menu['description'],
                'navigation' => $menu['navigation'],
                'account_id' => $menu['account_id'],
                'seo_name' => $menu['seo_name'],
                'position' => $menu['position'],
            ];

            $this->db->runSQL($sql, $params);
            return true;
        } catch (\PDOException $e) {
            // If a exception was thrown
            if (($e->errorInfo[1] ?? null) === 1062) {
                // If error indicates duplicate entry
                return false; // Return false to indicate duplicate name
            } else {
                // Otherwise
                throw $e; // Re-throw exception
            }
        }
    }

    public function updateAccountId(int $menuId, int $accountId): bool
    {
        $menuId = (int) $menuId;
        $accountId = (int) $accountId;

        $sql = "UPDATE menu
               SET account_id = :account_id
             WHERE id = :id
             LIMIT 1;";

        $stmt = $this->db->runSQL($sql, [
            'account_id' => $accountId,
            'id' => $menuId,
        ]);

        return $stmt->rowCount() === 1;
    }

    // Update existing menu
    public function update(array $menu): bool
    {
        try {
            // Try to update menu
            $sql = "UPDATE menu
                    SET  website = :website, name = :name, description = :description, navigation = :navigation, account_id = :account_id, seo_name = :seo_name, position = :position
                    WHERE id = :id;"; // SQL to update menu
            $this->db->runSQL($sql, $menu); // Update menu
            return true; // It worked, return true
        } catch (\PDOException $e) {
            // If exception thrown
            if ($e->errorInfo[1] === 1062) {
                // If duplicate entry
                return false; // Return false to indicate duplicate name
            } else {
                // If any other exception
                throw $e; // Re-throw exception
            }
        }
    }

    // Delete existing menu
    public function delete(int $id): bool
    {
        try {
            // Try to delete menu
            $sql = "DELETE FROM menu
                 WHERE id = :id;"; // SQL to delete menu
            $this->db->runSQL($sql, [$id]); // Delete menu
            return true; // It worked, return true
        } catch (\PDOException $e) {
            // If exception was thrown
            if ($e->errorInfo[1] === 1451) {
                // If error is integrity constraint
                return false; // Return false indicating stories exist in this menu
            } else {
                // If any other exception
                throw $e; // Re-throw exception
            }
        }
    }
}
