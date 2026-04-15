<?php
namespace PhpBook\CMS; // Declare namespace

class Quickguide // Define Session class
{
    public $guidetext;

    protected $db; // Holds ref to Database object

    public function __construct(Database $db)
    {
        $this->db = $db; // Add ref to Database object
    }

    /*Get individual guidetext by id
    public function get(int $id)
    {
        $sql = "SELECT guidetext
                  FROM quickguide
                                        // SQL to get pagelimit
        return $this->db->runSql($sql, [$id])->fetch();  // Return pagelimit
    }
*/
    // Get quickguide te
    public function getOne(): array|false
    {
        $sql = "SELECT guidetext
            FROM quickguide
            WHERE active = 1
            ORDER BY RAND()
            LIMIT 1";
        return $this->db->runSql($sql)->fetch();
    }
}
