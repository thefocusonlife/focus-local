<?php
namespace PhpBook\CMS; // Declare namespace

class Note
{
    public $id; // Store follow id
    public $note_type;
    public $from_id;
    public $from_name;
    public $to_id;
    public $to_name;
    public $family_id;
    public $request;
    public $allow;
    public $request_date;
    public $reply_date;
    public $notetype;

    protected $db; // Holds ref to Database object

    public function __construct(Database $db)
    {
        $this->db = $db; // Add ref to Database object
    }

    // Get individual note by id
    public function get(int $id)
    {
        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name, family_id, to_family_id, request,allow, request_date, reply_date
                  FROM note
                 WHERE from_id = :id;"; // SQL to get follow
        return $this->db->runSql($sql, $id)->fetch(); // Return follow
    }
    public function getById(int $id)
    {
        $arguments = [$id];
        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name, family_id, to_family_id,  request,allow, request_date, reply_date
                FROM note
                WHERE id = :id"; // SQL to get note by primary index
        return $this->db->runSql($sql, $arguments)->fetch(); // Return member
    }
    // Get details of all follows
    public function getAllTo(int $id): array
    {
        $arguments = [$id];
        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name, family_id, to_family_id, request, allow, request_date, reply_date
            FROM note
            WHERE (to_id = :id)
            ORDER BY request_date DESC; "; // SQL to get all notes for a to_id Descinding
        return $this->db->runSql($sql, $arguments)->fetchAll(); // Return all follows
    }
    // Get details of all follows
    public function getAllFrom(int $id): array
    {
        $arguments = [$id];

        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name, family_id, to_family_id, request, allow, request_date, reply_date
        FROM note
        WHERE (from_id = :id)
        ORDER BY request_date DESC; "; // SQL to get all notes for a from_id  Descending
        return $this->db->runSql($sql, $arguments)->fetchAll(); // Return all follows
    }

    // Get number of notifications
    public function count(): int
    {
        $sql = "SELECT COUNT(id) FROM note
                WHERE follow.f_id = $_SESSION[id];"; // SQL to count follows by Session_id
        return $this->db->runSql($sql)->fetchColumn(); // Return menu count
    }
    // Get details of all follows
    public function getAll(int $id): array
    {
        $arguments = [];

        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name, family_id, to_family_id, request, allow, request_date, reply_date
        FROM note
        WHERE 1";
        return $this->db->runSql($sql, $arguments)->fetchAll(); // Return all follows
    }

    public function update(array $note): bool
    {
        try {
            // Try to update data
            $this->db->beginTransaction(); // Start transaction

            $sql = "UPDATE note
                   SET id = :id, website = :website, note_type = :note_type, from_id = :from_id, from_name = :from_name, to_id = :to_id, to_name = :to_name,
                    family_id = :family_id, to_family_id = :to_family_id, request = :request, allow = :allow, request_date = :request_date, reply_date = :reply_date
                       WHERE id = :id;"; // SQL statement

            $arguments = $note;
            $this->db->runSql($sql, $arguments)->rowCount(); // Update Note
            $this->db->commit(); // Commit transaction
            return true; // Update worked
        } catch (\PDOException $e) {
            // If PDOException was raised
            $this->db->rollBack(); // Rollback transaction

            if ($e->errorInfo[1] === 1062) {
                // If an integrity constraint
                return false; // Return false
            } else {
                // For all other reasons

                throw $e;
                // Re-throw exception
            }
        }
    }

    // Create a new note
    public function create(array $note): bool
    {
        try {
            $this->db->beginTransaction(); // Start
            $sql = "INSERT INTO note (website, note_type, from_id, from_name, to_id, to_name, family_id, to_family_id, allow, request)
         VALUES (:website, :note_type, :from_id, :from_name, :to_id, :to_name, :family_id, :to_family_id, :allow, :request);";
            $this->db->runSql($sql, $note); // SQL to add new note
            $this->db->commit(); // Commit ransaction
            return true; // Return true
        } catch (\PDOException $e) {
            $this->db->rollBack(); // If PDOException thrown
            if ($e->errorInfo[1] === 1062) {
                // If error indicates duplicate entry
                return false; // Return false to indicate duplicate name
            }

            throw $e; // Re-throw exception
        }
    }

    // Delete existing note
    public function delete(int $id): bool
    {
        try {
            // Try to delete note
            $sql = "DELETE FROM note
             WHERE id = :id;"; // SQL to delete note
            $this->db->runSql($sql, [$id]); // Delete note
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

    /**
     * Returns the list of allowed "Family/Account" ids (to_family_id) that this member
     * is approved to follow (one-sided outgoing follows).
     *
     * Each row: ['account_id' => int, 'account_name' => string]
     */
    public function getAllowedFollowAccounts(
        int $websiteId,
        int $memberId,
        int $noteTypeFollow = 1,
    ): array {
        if ($websiteId <= 0 || $memberId <= 0) {
            return [];
        }

        $sql = "SELECT DISTINCT
              n.to_family_id AS account_id,
              n.to_name      AS account_name
            FROM note n
            WHERE n.website   = :website
              AND n.note_type = :note_type_follow
              AND n.from_id   = :member_id
              AND n.allow     = 1
              AND n.to_family_id > 0
            ORDER BY n.to_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':website' => $websiteId,
            ':note_type_follow' => $noteTypeFollow,
            ':member_id' => $memberId,
        ]);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        // Normalize types
        foreach ($rows as &$r) {
            $r['account_id'] = (int) ($r['account_id'] ?? 0);
            $r['account_name'] = (string) ($r['account_name'] ?? '');
        }
        unset($r);

        // Drop any junk rows (extra defensive)
        $rows = array_values(
            array_filter($rows, static function ($r) {
                return !empty($r['account_id']) && $r['account_id'] > 0;
            }),
        );

        return $rows;
    }
}
