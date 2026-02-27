<?php
declare(strict_types=1);

namespace PhpBook\CMS;

class Website
{
    protected Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function get(int $id): array
    {
        $sql = 'SELECT id, uber_id, sorttype, name, image_file, alt, non_members, blog, is_active
            FROM website
            WHERE id = :id
            LIMIT 1';
        $row = $this->db->runSql($sql, ['id' => $id])->fetch();
        return is_array($row) ? $row : [];
    }

    public function getById(int $id): array
    {
        return $this->get($id);
    }

    public function getAllActive(): array
    {
        $sql = "SELECT id, uber_id, sorttype, name, image_file, alt, non_members, blog, is_active
            FROM website
            WHERE is_active = 1
            ORDER BY id ASC";
        return $this->db->runSql($sql)->fetchAll() ?: [];
    }

    public function getAll(): array
    {
        $sql = 'SELECT id, uber_id, sorttype, name, image_file, alt, non_members, blog, is_active
            FROM website
            ORDER BY id ASC';
        return $this->db->runSql($sql)->fetchAll() ?: [];
    }

    public function count(): int
    {
        $sql = 'SELECT COUNT(id) FROM website';
        return (int) $this->db->runSql($sql)->fetchColumn();
    }

    // ✅ return rowcount int (0 is valid)
    public function update(array $website): int
    {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE website
                    SET uber_id = :uber_id,
                        sorttype = :sorttype,
                        name = :name,
                        image_file = :image_file,
                        alt = :alt,
                        non_members = :non_members,
                        blog = :blog
                    WHERE id = :id";

            $stmt = $this->db->runSql($sql, [
                'id' => (int) ($website['id'] ?? 0),
                'uber_id' => $website['uber_id'] ?? null,
                'sorttype' => (int) ($website['sorttype'] ?? 2),
                'name' => (string) ($website['name'] ?? ''),
                'image_file' => (string) ($website['image_file'] ?? ''),
                'alt' => (string) ($website['alt'] ?? ''),
                'non_members' => (int) ($website['non_members'] ?? 0),
                'blog' => (int) ($website['blog'] ?? 0),
            ]);

            $affected = (int) $stmt->rowCount();
            $this->db->commit();
            return $affected;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            if (($e->errorInfo[1] ?? null) === 1062) {
                return -1; // duplicate indicator (optional)
            }
            throw $e;
        }
    }

    // ✅ create returns inserted id (int). -1 optional for duplicate
    public function create(array $website): int
    {
        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO website (uber_id, sorttype, name, image_file, alt, non_members, blog)
                VALUES (:uber_id, :sorttype, :name, :image_file, :alt, :non_members, :blog)";

            $this->db->runSql($sql, [
                'uber_id' => $website['uber_id'] ?? null,
                'sorttype' => (int) ($website['sorttype'] ?? 2),
                'name' => (string) ($website['name'] ?? ''),
                'image_file' => (string) ($website['image_file'] ?? ''),
                'alt' => (string) ($website['alt'] ?? ''),
                'non_members' => (int) ($website['non_members'] ?? 0),
                'blog' => (int) ($website['blog'] ?? 0),
            ]);

            $newId = (int) $this->db->lastInsertId();
            $this->db->commit();
            return $newId;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            if (($e->errorInfo[1] ?? null) === 1062) {
                return -1; // duplicate indicator
            }
            throw $e;
        }
    }
    private int $lastCreatedId = 0;

    public function getLastCreatedId(): int
    {
        return $this->lastCreatedId;
    }

    public function setActive(int $id, int $active): int
    {
        $sql = 'UPDATE website SET is_active = :active WHERE id = :id';
        $stmt = $this->db->runSql($sql, ['active' => $active, 'id' => $id]);
        return (int) $stmt->rowCount();
    }

    public function delete(int $id): bool
    {
        try {
            $sql = 'DELETE FROM website WHERE id = :id';
            $this->db->runSql($sql, ['id' => $id]);
            return true;
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1451) {
                return false;
            }
            throw $e;
        }
    }
}
