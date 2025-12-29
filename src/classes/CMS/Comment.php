<?php
namespace PhpBook\CMS; // Declare namespace

class Comment // Define Comment class
{
    protected $db; // Holds reference to Database object

    public function __construct(Database $db)
    {
        // Runs when object created using class
        $this->db = $db; // Store Database object in $db property
    }

    // Get all comments for story
    public function getAll(int $id): array
    {
        $sql = "SELECT c.id, c.website, c.comment, c.posted, c.story_id,
               CONCAT(m.forename, ' ', m.surname) AS author, m.picture
                 FROM comment AS c
                 JOIN member  AS m ON c.member_id = m.id 
                WHERE c.story_id = :id;"; // SQL statement
        return $this->db->runSQL($sql, ['id' => $id])->fetchAll(); // Execute query
    }

    // Create story comment
    public function create(array $comment): bool
    {
        $sql = "INSERT INTO comment (website,comment, story_id, member_id) 
                VALUES (:website, :comment, :story_id, :member_id);"; // SQL statement
        $this->db->runSQL($sql, $comment); // Execute query
        return true;
    }
    // Delete comment
    public function delete(int $id)
    {
        //: bool
        $sql = 'DELETE FROM comment WHERE story_id = :id;'; // SQL statement
        $this->db->runSQL($sql, [$id]); // Delete comment
    }
}
