<?php
namespace PhpBook\CMS; // Namespace declaration
use PhpBook\CMS\Exceptions\ForeignKeyConstraintException;
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
        $sql = "SELECT id, default_sorttype_id, master_id, website, name, description, navigation, account_id, seo_name, position
                  FROM menu
                 WHERE id = :id;"; // SQL to get one menu
        return $this->db->runSql($sql, [$id])->fetch(); // Return menu data
    }

    public function getBySlug(int $websiteId, int $accountId, string $slug): ?array
    {
        $sql = "SELECT id, default_sorttype_id, master_id, website, name, description, navigation, account_id, seo_name, position
            FROM menu
            WHERE website = :website
              AND account_id = :account_id
              AND seo_name = :slug
            LIMIT 1";
        $row = $this->db
            ->runSql($sql, [
                'website' => $websiteId,
                'account_id' => $accountId,
                'slug' => $slug,
            ])
            ->fetch();

        return $row ?: null;
    }

    public function getBySlugAnyAccount(int $websiteId, string $slug): ?array
    {
        $sql = "SELECT id, default_sorttype_id, master_id, website, name, description, navigation,
                   account_id, seo_name, position
            FROM menu
            WHERE website = :website
              AND seo_name = :slug
            ORDER BY
              (account_id = 1) DESC,   -- prefer UberAdmin/shared menu if duplicates exist
              navigation DESC,
              position ASC,
              id ASC
            LIMIT 1";
        $row = $this->db
            ->runSql($sql, [
                'website' => $websiteId,
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
        return $this->db->runSql($sql)->fetchAll(); // Return all menus
    }
    public function getAll2(?int $website, ?int $account_id): array
    {
        $website = (int) ($website ?? 0);
        $account_id = (int) ($account_id ?? 0);

        if ($website <= 0 || $account_id <= 0) {
            return [];
        }

        $sql = "SELECT id, website, name, navigation, account_id, seo_name, position
              FROM menu
             WHERE website = :website
               AND account_id = :account_id
             ORDER BY website, account_id ASC, position ASC;";

        return $this->db
            ->runSql($sql, [
                'website' => $website,
                'account_id' => $account_id,
            ])
            ->fetchAll();
    }

    public function getForWebsite(int $menuId, int $websiteId): ?array
    {
        if ($menuId <= 0 || $websiteId <= 0) {
            return null;
        }

        $sql = "SELECT id, default_sorttype_id, master_id, website, name, description, navigation,
                   account_id, seo_name, position
              FROM menu
             WHERE id = :id
               AND website = :website
             LIMIT 1";

        $row = $this->db
            ->runSql($sql, [
                'id' => $menuId,
                'website' => $websiteId,
            ])
            ->fetch();

        return $row ?: null;
    }

    public function getAllByWebsite(int $websiteId): array
    {
        $websiteId = (int) $websiteId;
        if ($websiteId <= 0) {
            return [];
        }

        $sql = "SELECT id, website, name, navigation, account_id, seo_name, position
              FROM menu
             WHERE website = :website
             ORDER BY account_id ASC, position ASC;";

        return $this->db->runSql($sql, ['website' => $websiteId])->fetchAll();
    }

    public function getFirstForWebsite(int $websiteId): ?int
    {
        $websiteId = (int) $websiteId;
        if ($websiteId <= 0) {
            return null;
        }

        $sql = "
        SELECT id
        FROM menu
        WHERE website = :website
        ORDER BY id ASC
        LIMIT 1
    ";

        $row = $this->db->runSql($sql, ['website' => $websiteId])->fetch();

        return $row ? (int) $row['id'] : null;
    }

    // ADMIN METHODS
    // Get number of menus
    public function count(): int
    {
        $sql = "SELECT COUNT(id) FROM menu
                WHERE menu.account_id = $_SESSION[id];"; // SQL to count menus
        return $this->db->runSql($sql)->fetchColumn(); // Return menu count
    }

    // Create new menu
    public function create(array $menu): int
    {
        try {
            $sql = "INSERT INTO menu
            (website, name, description, navigation, account_id, seo_name, position, default_sorttype_id)
            VALUES
            (:website, :name, :description, :navigation, :account_id, :seo_name, :position, :default_sorttype_id)";

            $params = [
                'website' => $menu['website'],
                'name' => $menu['name'],
                'description' => $menu['description'],
                'navigation' => $menu['navigation'],
                'account_id' => $menu['account_id'],
                'seo_name' => $menu['seo_name'],
                'position' => $menu['position'],
                'default_sorttype_id' => $menu['default_sorttype_id'],
            ];

            $this->db->runSql($sql, $params);

            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                return 0;
            }
            throw $e;
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

        $stmt = $this->db->runSql($sql, [
            'account_id' => $accountId,
            'id' => $menuId,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function update(array $menu): int
    {
        foreach (['id', 'website', 'name'] as $k) {
            if (!array_key_exists($k, $menu)) {
                throw new \InvalidArgumentException("Missing menu field: {$k}");
            }
        }

        return $this->menu_update(
            $menu['id'],
            $menu['default_sorttype_id'] ?? null,
            $menu['master_id'] ?? null,
            $menu['website'] ?? null,
            $menu['name'] ?? null,
            $menu['description'] ?? null,
            $menu['navigation'] ?? null,
            $menu['account_id'] ?? null,
            $menu['seo_name'] ?? null,
            $menu['position'] ?? null,
        );
    }

    public function menu_update(
        $id,
        $default_sorttype_id = null,
        $master_id = null,
        $website = null,
        $name = null,
        $description = null,
        $navigation = null,
        $account_id = null,
        $seo_name = null,
        $position = null,
    ): int {
        try {
            $sql = "UPDATE menu
            SET master_id = :master_id,
                name = :name,
                description = :description,
                navigation = :navigation,
                account_id = :account_id,
                seo_name = :seo_name,
                position = :position,
                default_sorttype_id = :default_sorttype_id
            WHERE id = :id AND website = :website";

            $params = [
                'id' => $id,
                'website' => $website,
                'master_id' => $master_id,
                'name' => $name,
                'description' => $description,
                'navigation' => $navigation,
                'account_id' => $account_id,
                'seo_name' => $seo_name,
                'position' => $position,
                'default_sorttype_id' => $default_sorttype_id,
            ];

            $stmt = $this->db->runSql($sql, $params);
            return (int) $stmt->rowCount();
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return 0;
            }
            throw $e;
        }
    }

    // Delete existing menu
    /**
     * @throws ForeignKeyConstraintException when menu is referenced (e.g., stories exist)
     */
    public function deleteForWebsite(int $menuId, int $websiteId): int
    {
        $sql = 'DELETE FROM menu
            WHERE id = :id AND website = :website';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id' => $menuId,
                ':website' => $websiteId,
            ]);

            return (int) $stmt->rowCount(); // 0 or 1
        } catch (\PDOException $e) {
            $sqlState = (string) ($e->getCode() ?? '');
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            $errMsg = (string) ($e->getMessage() ?? '');

            // Detect MySQL/MariaDB FK constraint failure in multiple ways
            $isFkError =
                ($sqlState === '23000' && $driverCode === 1451) || // classic
                ($sqlState === '23000' && stripos($errMsg, 'foreign key') !== false) ||
                stripos($errMsg, 'constraint fails') !== false;

            if ($isFkError) {
                throw new ForeignKeyConstraintException(
                    'Menu has dependent rows and cannot be deleted',
                    0,
                    $e,
                );
            }

            // If not FK, rethrow so higher logic can handle or surface an error
            throw $e;
        }
    }

    public function getNextPositionForAccount(int $websiteId, int $accountId, int $step = 5): int
    {
        if ($websiteId <= 0 || $accountId <= 0) {
            return 0;
        }

        $sql = "SELECT COALESCE(MAX(position), 0) AS max_pos
            FROM menu
            WHERE website = :website
              AND account_id = :account_id";

        $row = $this->db
            ->runSql($sql, [
                'website' => $websiteId,
                'account_id' => $accountId,
            ])
            ->fetch();

        $max = (int) ($row['max_pos'] ?? 0);
        return $max + $step;
    }
}
