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

    public function getOne(int $websiteId = 0): array
    {
        if ($websiteId > 0) {
            $sql = "SELECT *
                  FROM quickguide
                 WHERE active = 1
                   AND website = :website
              ORDER BY RAND()
                 LIMIT 1";

            $statement = $this->db->runSql($sql, [
                'website' => $websiteId,
            ]);

            $guide = $statement->fetch();

            if (!empty($guide)) {
                return $guide;
            }
        }

        $sql = "SELECT *
              FROM quickguide
             WHERE active = 1
               AND website = 0
          ORDER BY RAND()
             LIMIT 1";

        $statement = $this->db->runSql($sql);

        return $statement->fetch() ?: [];
    }
}
