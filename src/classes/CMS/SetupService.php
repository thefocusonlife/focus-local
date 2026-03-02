<?php
declare(strict_types=1);

namespace PhpBook\CMS;

use PDO;
use RuntimeException;

final class SetupService
{
    public function __construct(private PDO $pdo) {}

    /**
     * Copy "uber" master menus from website 1 to the target website if missing.
     * Mirrors CopyUberMenusToWebsite stored procedure logic.
     *
     * @return array{target_website:int, master_rows_available:int, rows_inserted:int}
     */
    public function copyUberMenusToWebsite(int $targetWebsiteId): array
    {
        if ($targetWebsiteId <= 0) {
            throw new RuntimeException('Invalid targetWebsiteId');
        }
        if ($targetWebsiteId === 1) {
            return ['target_website' => 1, 'master_rows_available' => 0, 'rows_inserted' => 0];
        }

        $masterIds = [1, 2, 3, 39, 50];
        $in = implode(',', array_fill(0, count($masterIds), '?'));

        $startedTx = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $startedTx = true;
        }

        try {
            // 1) Check target exists
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM website WHERE id = ?');
            $stmt->execute([$targetWebsiteId]);
            $targetExists = (int) $stmt->fetchColumn();

            if ($targetExists <= 0) {
                throw new RuntimeException("Target website not found: {$targetWebsiteId}");
            }

            // 2) Count master menus
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*)
               FROM menu
              WHERE website = 1
                AND account_id = 1
                AND id IN ($in)",
            );
            $stmt->execute($masterIds);
            $masterCount = (int) $stmt->fetchColumn();

            // 3) Insert missing menus
            $sql = "
            INSERT INTO menu
              (website, name, description, navigation, account_id, seo_name, position, default_sorttype_id, master_id)
            SELECT
              ? AS website,
              m.name,
              m.description,
              1 AS navigation,
              1 AS account_id,
              m.seo_name,
              m.position,
              m.default_sorttype_id,
              m.id AS master_id
            FROM menu m
            WHERE m.website = 1
              AND m.account_id = 1
              AND m.id IN ($in)
              AND NOT EXISTS (
                SELECT 1
                FROM menu x
                WHERE x.website = ?
                  AND (
                    x.master_id = m.id
                    OR (x.account_id = 1 AND x.seo_name = m.seo_name)
                  )
              )
        ";

            $params = array_merge([$targetWebsiteId], $masterIds, [$targetWebsiteId]);

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rowsInserted = $stmt->rowCount();

            if ($startedTx) {
                $this->pdo->commit();
            }

            return [
                'target_website' => $targetWebsiteId,
                'master_rows_available' => $masterCount,
                'rows_inserted' => $rowsInserted,
            ];
        } catch (\Throwable $e) {
            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
