<?php
declare(strict_types=1);

namespace PhpBook\CMS;

class Note
{
    protected Database $db;

    private const NOTE_TYPE_FOLLOW = 1;

    private const NOTE_TYPE_MESSAGE = 2;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getInbox(int $memberId): array
    {
        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name,
                   family_id, to_family_id, request, allow, request_date, reply_date
              FROM note
             WHERE to_id = :member_id
               AND note_type = :note_type
          ORDER BY request_date DESC, id DESC";

        return $this->db
            ->runSql($sql, [
                'member_id' => $memberId,
                'note_type' => self::NOTE_TYPE_MESSAGE,
            ])
            ->fetchAll();
    }

    public function getSent(int $memberId): array
    {
        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name,
                   family_id, to_family_id, request, allow, request_date, reply_date
              FROM note
             WHERE from_id = :member_id
               AND note_type = 2
          ORDER BY request_date DESC, id DESC";

        return $this->db
            ->runSql($sql, [
                'member_id' => $memberId,
            ])
            ->fetchAll();
    }

    public function getMessageForMember(int $messageId, int $memberId): array|false
    {
        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name,
                   family_id, to_family_id, request, allow, request_date, reply_date
              FROM note
             WHERE id = :id
               AND note_type = 2
               AND (to_id = :to_id OR from_id = :from_id)";

        return $this->db
            ->runSql($sql, [
                'id' => $messageId,
                'to_id' => $memberId,
                'from_id' => $memberId,
            ])
            ->fetch();
    }

    public function getById(int $id): array|false
    {
        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name,
                       family_id, to_family_id, request, allow, request_date, reply_date
                  FROM note
                 WHERE id = :id";

        return $this->db->runSql($sql, ['id' => $id])->fetch();
    }

    public function getByFromId(int $memberId): array
    {
        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name,
                       family_id, to_family_id, request, allow, request_date, reply_date
                  FROM note
                 WHERE from_id = :member_id
              ORDER BY request_date DESC, id DESC";

        return $this->db->runSql($sql, ['member_id' => $memberId])->fetchAll();
    }

    public function getByToId(int $memberId): array
    {
        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name,
                       family_id, to_family_id, request, allow, request_date, reply_date
                  FROM note
                 WHERE to_id = :member_id
              ORDER BY request_date DESC, id DESC";

        return $this->db->runSql($sql, ['member_id' => $memberId])->fetchAll();
    }

    public function getForMember(int $memberId): array
    {
        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name,
                       family_id, to_family_id, request, allow, request_date, reply_date
                  FROM note
                 WHERE from_id = :from_id
                    OR to_id   = :to_id
              ORDER BY request_date DESC, id DESC";

        return $this->db
            ->runSql($sql, [
                'from_id' => $memberId,
                'to_id' => $memberId,
            ])
            ->fetchAll();
    }

    public function getApprovedFollowsFromMember(int $memberId): array
    {
        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name,
                       family_id, to_family_id, request, allow, request_date, reply_date
                  FROM note
                 WHERE from_id = :member_id
                   AND note_type = :note_type
                   AND allow = 1
              ORDER BY to_name ASC";

        return $this->db
            ->runSql($sql, [
                'member_id' => $memberId,
                'note_type' => self::NOTE_TYPE_FOLLOW,
            ])
            ->fetchAll();
    }

    public function countForMember(int $memberId): int
    {
        $sql = "SELECT COUNT(id)
                  FROM note
                 WHERE to_id = :member_id
                   AND allow = 0";

        return (int) $this->db->runSql($sql, ['member_id' => $memberId])->fetchColumn();
    }

    public function create(array $note): bool
    {
        try {
            $sql = "INSERT INTO note
                    (website, note_type, from_id, from_name, to_id, to_name,
                     family_id, to_family_id, allow, request)
                    VALUES
                    (:website, :note_type, :from_id, :from_name, :to_id, :to_name,
                     :family_id, :to_family_id, :allow, :request)";

            $this->db->runSql($sql, $note);
            return true;
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                return false;
            }
            throw $e;
        }
    }

    public function update(array $note): bool
    {
        try {
            $sql = "UPDATE note
                       SET website      = :website,
                           note_type    = :note_type,
                           from_id      = :from_id,
                           from_name    = :from_name,
                           to_id        = :to_id,
                           to_name      = :to_name,
                           family_id    = :family_id,
                           to_family_id = :to_family_id,
                           request      = :request,
                           allow        = :allow,
                           request_date = :request_date,
                           reply_date   = :reply_date
                     WHERE id = :id";

            $this->db->runSql($sql, $note);
            return true;
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                return false;
            }
            throw $e;
        }
    }

    public function approve(int $id): bool
    {
        $sql = "UPDATE note
                   SET allow = 1,
                       reply_date = CURRENT_DATE
                 WHERE id = :id";

        $this->db->runSql($sql, ['id' => $id]);
        return true;
    }

    public function delete(int $id): bool
    {
        try {
            $sql = 'DELETE FROM note WHERE id = :id';
            $this->db->runSql($sql, ['id' => $id]);
            return true;
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1451) {
                return false;
            }
            throw $e;
        }
    }

    public function getAllowedFollowAccounts(
        int $websiteId,
        int $memberId,
        int $noteTypeFollow = self::NOTE_TYPE_FOLLOW,
    ): array {
        if ($websiteId <= 0 || $memberId <= 0) {
            return [];
        }

        $sql = "SELECT DISTINCT
                       to_family_id AS account_id,
                       to_name      AS account_name
                  FROM note
                 WHERE website = :website
                   AND note_type = :note_type
                   AND from_id = :member_id
                   AND allow = 1
                   AND to_family_id > 0
              ORDER BY to_name";

        $rows = $this->db
            ->runSql($sql, [
                'website' => $websiteId,
                'note_type' => $noteTypeFollow,
                'member_id' => $memberId,
            ])
            ->fetchAll();

        foreach ($rows as &$row) {
            $row['account_id'] = (int) $row['account_id'];
            $row['account_name'] = (string) $row['account_name'];
        }

        return $rows;
    }

    // Legacy method names — keep existing code from breaking

    public function get(int $id): array
    {
        return $this->getByFromId($id);
    }

    public function getAllTo(int $id): array
    {
        return $this->getByToId($id);
    }

    public function getAllFrom(int $id): array
    {
        return $this->getByFromId($id);
    }

    public function getAll(int $id = 0): array
    {
        if ($id > 0) {
            return $this->getForMember($id);
        }

        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name,
                       family_id, to_family_id, request, allow, request_date, reply_date
                  FROM note
              ORDER BY request_date DESC, id DESC";

        return $this->db->runSql($sql)->fetchAll();
    }

    public function getByAllowed(int $id): array
    {
        return $this->getApprovedFollowsFromMember($id);
    }

    public function count(): int
    {
        $memberId = (int) ($_SESSION['id'] ?? 0);
        return $memberId > 0 ? $this->countForMember($memberId) : 0;
    }

    public function createMessage(array $message): bool
    {
        $message['note_type'] = 2;
        $message['allow'] = 0;

        return $this->create($message);
    }

    public function countUnreadMessages(int $memberId): int
    {
        $sql = "SELECT COUNT(id)
              FROM note
             WHERE to_id = :member_id
               AND note_type = 2
               AND allow = 0";

        return (int) $this->db
            ->runSql($sql, [
                'member_id' => $memberId,
            ])
            ->fetchColumn();
    }

    public function getUnreadCount(int $memberId): int
    {
        $sql = "SELECT COUNT(*)
            FROM note
            WHERE to_member_id = :member_id
              AND notetype_id = 2
              AND is_read = 0
              AND deleted_to = 0";

        return (int) $this->db
            ->runSql($sql, [
                'member_id' => $memberId,
            ])
            ->fetchColumn();
    }

    public function markRead(int $id): void
    {
        $sql = "UPDATE note
               SET allow = 1
             WHERE id = :id";

        $this->db->runSql($sql, ['id' => $id]);
    }
}
