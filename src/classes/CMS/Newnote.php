<?php
namespace PhpBook\CMS; // Declare namespace

class newnote
{
    // Store follow id
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

    protected $db; // Holds ref to Database object

    public function __construct(Database $db)
    {
        $this->db = $db; // Add ref to Database object
    }

    // Get individual note by id
    public function get(int $id)
    {
        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name, family_id, to_family_id, request, request_date, reply_date
                  FROM note
                 WHERE from_id = :id;"; // SQL to get follow
        return $this->db->runSQL($sql, [$id])->fetch(); // Return follow
    }

    // Get details of all notes
    public function getAll(): array
    {
        $sql = "SELECT id, website, note_type, from_id, from_name, to_id, to_name, family_id, to_family_id, request, request_date, reply_date
            FROM note
            WHERE 1";
        return $this->db->runSQL($sql)->fetchAll(); // Return all follows
    }
    // Get number of notifications
    public function count(): int
    {
        $sql = "SELECT COUNT(id) FROM note
                WHERE follow.f_id = $_SESSION[id];"; // SQL to count follows
        return $this->db->runSQL($sql)->fetchColumn(); // Return menu count
    }
    // Create a new note
    public function create(array $note): bool
    {
        // $note['note_type] = 1;

        try {
            $this->db->beginTransaction(); // Start transaction

            unset($note['id'], $note['request_date'], $note['reply_date']); // Try to add new noter
            $sql = "INSERT INTO note (website, note_type, from_id, from_name, to_id, to_name, family_id, to_family_id, allow, request)
         VALUES (:website, :note_type, :from_id, :from_name, :to_id, :to_name, :family_id, :to_family_id, :allow, :request);";

            $this->db->runSQL($sql, $note); // Run SQL

            $this->db->commit(); // Commit transaction
            return true; // Return true
        } catch (\PDOException $e) {
            // If PDOException thrown
            if ($e->errorInfo[1] === 1062) {
                // If error indicates duplicate entry
                return false; // Return false to indicate duplicate name
            }
            throw $e; // Re-throw exception
        }
    }
}
