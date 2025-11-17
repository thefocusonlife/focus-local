<?php
namespace PhpBook\CMS;                                   // Namespace declaration

class Story
{
    public $id;                                          // Store story id
    public $website;                                     // Store website id
    public $title;                                       // Store story title
    public $summary;                                     // Store story summary
    public $content;                                     // Store story content
    public $created;                                     // Store story creation date
    public $menu_id;                                     // Story story menu_id
    public $member_id;                                   // Store story member_id         
    public $family_id;                                   // Store story family id
    public $published;                                   // Store story published flag
    public $seo_title;                                   // Store story seo_title
    public $storyorder;                                    // Store story storyorder
    public $landscape;                                   // Store story landscape flag
    public $crop;                                        // Store story crop flag
    public $allow_comment;
    public $keyword;                                     // Store story keyword  
    public $geomitry_info; 
    public $imagesize;
    
    protected $db;                                       // Holds ref to Database object

    public function __construct(Database $db)
    {
     
        $this->db = $db;                                   // Add ref to Database object
    }
   
    // Get individual story
    public function get(int $id, bool $published) {

        $sql = "SELECT a.id, a.website, a.title, a.summary, a.content, a.created, a.menu_id, a.member_id, a.family_id, a.imagesize, a.published, a.seo_title,
                     a.storyorder, a.landscape, a.crop, a.allow_comment, a.keyword,
                       c.name AS menu,
                       c.seo_name AS seo_menu,
                       m.forename, m.surname,
                       CONCAT(m.forename, ' ', m.surname) AS author,
                       i.id       AS image_id, 
                       i.file     AS image_file, 
                       i.alt      AS image_alt,
                      
                       (SELECT COUNT(story_id) 
                          FROM likes 
                         WHERE likes.story_id = a.id) AS likes,
                       (SELECT COUNT(story_id) 
                          FROM comment 
                         WHERE comment.story_id = a.id) AS comments
                  FROM story    AS a
                  JOIN menu   AS c ON a.menu_id = c.id
                  JOIN member     AS m ON a.member_id   = m.id
                  LEFT JOIN image AS i ON a.image_id    = i.id
                 WHERE a.id = :id ";                     // SQL statement
        if ($published) {                                // If must be published
            $sql .= "AND a.published = 1 ";              // Add clause to SQL
        }
        
     
        $sql .= "GROUP BY 1;";                          // Add GROUP BY clause
        return $this->db->runSQL($sql, [$id])->fetch();  // Return story
    }


    // Get summaries of stories - published only
            public function getAll( $published=null ,$menu = null, $member=null,  $limit = 300): array {
    
            // Setup file
$path  = mb_strtolower($_SERVER['REQUEST_URI']);             // Get path in lowercase
$path  = substr($path, strlen(DOC_ROOT));                    // Remove up to DOC_ROOT
$parts = explode('/', $path);                                // Split into array at /

if ($parts[0] != 'admin') {                                  // If an admin page
    $page = $parts[0] ?: 'index';                            // Page name (or use index)
    $id   = $parts[1] ?? null;                               // Get ID (or use null)
} else {                                                     // If not an admin page
    $page = 'admin/' . ($parts[1] ?? '');                    // Page name
    $id   = $parts[2] ?? null;                               // Get ID
}
                 // Validate ID
srand(time());
//$php_page = APP_ROOT . '/src/pages/' . $page . '.php';       // Path to PHP page

        $arguments['menu'] = $arguments['menu1'] = $menu;  // Menu id
        $arguments['member']   = $arguments['member1']   = $member;   // Author id
         if(empty($_SESSION['pagelimit'])) {
            $arguments['limit'] = $limit;
         } else {   
            $arguments['limit']    = $_SESSION['pagelimit']; 
         }
         $sql = "SELECT a.id, a.website, a.title, a.summary, a.created, a.family_id, a.menu_id, a.member_id, a.family_id, a.published,
         a.seo_title, a.storyorder,a.landscape, a.crop, a.allow_comment, keyword,
         c.name AS menu,
         c.seo_name AS seo_menu,
         m.forename, m.surname,
         CONCAT(m.forename, ' ', m.surname) AS author,
         m.account_id,
         i.file     AS image_file, 
         i.alt      AS image_alt,
         (SELECT COUNT(story_id) 
            FROM likes 
           WHERE likes.story_id = a.id) AS likes,
         (SELECT COUNT(story_id) 
            FROM comment 
            WHERE comment.story_id = a.id) AS comments
            FROM story      AS a
            JOIN menu       AS c ON a.menu_id = c.id
            JOIN member     AS m ON a.member_id   = m.id
            LEFT JOIN image AS i ON a.image_id    = i.id 
            WHERE (a.menu_id = :menu OR :menu1 is null)
            AND (a.member_id   = :member   OR :member1   is null)
            AND (a.published = 0 or a.published =1)";    
            // JUST FOR TESTING
             
        if (empty($_SESSION['sorttype'])) {
           if(empty($parts[2])) {
              // $sql .= " ORDER BY landscape, 8
                //LIMIT :limit;";
                $sql .= " ORDER BY a.menu_id ASC, a.storyorder,a.landscape DESC 
                LIMIT :limit;";   
           }   
           } elseif ($_SESSION['sorttype'] >= 1  AND $_SESSION['sorttype'] <= 3) {                
                 $sql .= " ORDER BY  a.menu_id ASC, c.position, a.storyorder
               LIMIT :limit;";
            } elseif ($_SESSION['sorttype'] >= 4  AND $_SESSION['sorttype'] <= 6) {
                $sql .= " ORDER BY  a.menu_id DESC, c.position DESC, a.storyorder DESC
               LIMIT :limit;";   
           } elseif ($_SESSION['sorttype'] >= 7  AND $_SESSION['sorttype'] <= 9){
            if ($arguments['menu'] <= 4) {
                $sql .= " ORDER BY  a.menu_id ASC, c.position, a.storyorder
                LIMIT :limit;";
            } else {     
                $sql .= " ORDER BY  a.landscape DESC, RAND() 
                LIMIT :limit;";
            }
                                // Add: order and max number of stories to return 
            
             } else {       
                $sql .= " ORDER BY a.menu_id ASC, a.storyorder,a.landscape DESC 
                LIMIT :limit;";
           }
         return $this->db->runSQL($sql, $arguments)->fetchAll(); // Return data
        // SQL for story summary
       
    }

    // Get summaries of stories - Published and not-published
            public function getAll2($website, $published, $menu=null , $member = null, $limit = 150): array {
    
         
                // Setup file
                 // Validate ID

//$php_page = APP_ROOT . '/src/pages/' . $page . '.php';       // Path to PHP page

        $arguments['menu']      = $arguments['menu1']     = $menu;  // Menu id
        $arguments['member']    = $arguments['member1']   = $member;   // Author id
        $arguments['website']   = $website;
         if(empty($_SESSION['pagelimit'])) {
            $arguments['limit'] = $limit;
         } else {
            if($_SESSION['id'] == 1) {
                $arguments['limit'] = 150;                      // manually toggle here for managing UberAdmin stories 
                //$arguments['limit'] = $_SESSION['pagelimit'];
            } else {  
            $arguments['limit']    = $_SESSION['pagelimit']; 
            }
         }
         
   
        $sql = "SELECT a.id, a.website, a.title, a.summary, a.created, a.family_id, a.menu_id, a.member_id, a.family_id, a.published,
                       a.seo_title, a.storyorder,a.landscape, a.crop, a.allow_comment, keyword,
                       c.name AS menu,
                       c.seo_name AS seo_menu,
                       m.forename, m.surname,
                       CONCAT(m.forename, ' ', m.surname) AS author,
                       m.account_id,
                       i.file     AS image_file, 
                       i.alt      AS image_alt,
                       (SELECT COUNT(story_id) 
                          FROM likes 
                         WHERE likes.story_id = a.id) AS likes,
                       (SELECT COUNT(story_id) 
                          FROM comment 
                         WHERE comment.story_id = a.id) AS comments

                  FROM story    AS a
                  JOIN menu   AS c ON a.menu_id = c.id
                  JOIN member     AS m ON a.member_id   = m.id
                  LEFT JOIN image AS i ON a.image_id    = i.id 
                  WHERE (a.menu_id = :menu OR :menu1 is null)
                  AND (a.member_id   = :member   OR :member1   is null)
                  AND (a.website = :website)
                  AND (a.published = 1)";

if (empty($_SESSION['sorttype'])) {
    if(empty($parts[2])) {
       // $sql .= " ORDER BY landscape, 8
         //LIMIT :limit;";
         $sql .= " ORDER BY  a.storyorder,a.landscape DESC 
                LIMIT :limit;";//
         }   
    } elseif ($_SESSION['sorttype'] >= 1  AND $_SESSION['sorttype'] <= 3) {                
          $sql .= " ORDER BY  a.menu_id ASC, c.position, a.storyorder
        LIMIT :limit;";
    } elseif ($_SESSION['sorttype'] >= 4  AND $_SESSION['sorttype'] <= 6) {
        
        $sql .= " ORDER BY  a.menu_id DESC, c.position DESC, a.storyorder DESC
        LIMIT :limit;";   
    } elseif ($_SESSION['sorttype'] >= 7  AND $_SESSION['sorttype'] <= 9) {
        ($arguments['menu']);
        
       if ( $arguments['menu'] >= 0 and $arguments['menu']<= 4) {
            $sql .= " ORDER BY  a.menu_id ASC, c.position, a.storyorder
            LIMIT :limit;";
        } else {     
            $sql .= " ORDER BY  a.landscape DESC, RAND() 
            LIMIT :limit;";
        }
                         // Add: order and max number of stories to return 
     } else { 

        $sql .= " ORDER BY a.menu_id ASC, a.storyorder,a.landscape DESC 
        LIMIT :limit;";
    }
        return $this->db->runSQL($sql, $arguments)->fetchAll(); // Return data
        // SQL for story summary
    }
// Get summaries of stories - Published by website
public function getAll3($website, $published = null ,$menu = null, $member=null,  $limit = 300): array {

srand(time());     
$arguments = array($website);  
    // Setup file
$path  = mb_strtolower($_SERVER['REQUEST_URI']);             // Get path in lowercase
$path  = substr($path, strlen(DOC_ROOT));                    // Remove up to DOC_ROOT
$parts = explode('/', $path);                                // Split into array at /

if ($parts[0] != 'admin') {                                  // If an admin page
$page = $parts[0] ?: 'index';                            // Page name (or use index)
$id   = $parts[1] ?? null;                               // Get ID (or use null)
} else {                                                     // If not an admin page
$page = 'admin/' . ($parts[1] ?? '');                    // Page name
$id   = $parts[2] ?? null;                               // Get ID
}

// Validate ID

//$php_page = APP_ROOT . '/src/pages/' . $page . '.php';       // Path to PHP page


if(empty($_SESSION['pagelimit'])) {
$arguments['limit'] = $limit;
} else {   
$arguments['limit']    = $_SESSION['pagelimit']; 
}

$sql = "SELECT a.id, a.website, a.title, a.summary, a.created, a.family_id, a.menu_id, a.member_id, a.family_id, a.published,
a.seo_title, a.storyorder,a.landscape, a.crop, a.allow_comment, keyword,
c.name AS menu,
c.seo_name AS seo_menu,
m.forename, m.surname,
CONCAT(m.forename, ' ', m.surname) AS author,
m.account_id,
i.file     AS image_file, 
i.alt      AS image_alt,
(SELECT COUNT(story_id) 
   FROM likes 
  WHERE likes.story_id = a.id) AS likes,
(SELECT COUNT(story_id) 
   FROM comment 
  WHERE comment.story_id = a.id) AS comments

FROM story    AS a
JOIN menu   AS c ON a.menu_id = c.id
JOIN member     AS m ON a.member_id   = m.id
LEFT JOIN image AS i ON a.image_id    = i.id 
WHERE (a.website = :website)";

if (empty($_SESSION['sorttype'])) {
if(empty($parts[2])) {
//$sql .= " ORDER BY landscape, 8
//LIMIT :limit;";
$sql .= " ORDER BY a.menu_id ASC, a.storyorder,a.landscape DESC 
                LIMIT :limit;";
}   
} elseif ($_SESSION['sorttype'] >= 1  AND $_SESSION['sorttype'] <= 3) {                
$sql .= " ORDER BY  a.menu_id ASC, c.position, a.storyorder
LIMIT :limit;";
} elseif ($_SESSION['sorttype'] >= 4  AND $_SESSION['sorttype'] <= 6) {
$sql .= " ORDER BY  a.menu_id DESC, c.position DESC, a.storyorder DESC
LIMIT :limit;";   
} elseif ($_SESSION['sorttype'] >= 7  AND $_SESSION['sorttype'] <= 9){
      
        $sql .= " ORDER BY  a.landscape DESC, RAND() 
        LIMIT :limit;";
    
  // Add: order and max number of stories to return 

} else {       
$sql .= " ORDER BY a.menu_id ASC, a.storyorder,a.landscape DESC 
LIMIT :limit;";
}
return $this->db->runSQL($sql, $arguments)->fetchAll(); // Return data
// SQL for story summary
}

    // Get number of search matches
    public function searchCount(string $term): int
    {
        $arguments['term1'] = $arguments['term2'] = $arguments['term3'] = $arguments['term4'] = '%' . $term . '%'; // Add wildcards to search term
        $sql   = "SELECT COUNT(title)
                FROM story
                WHERE story.published = 1 AND  story.title   LIKE :term1 
                  OR story.published = 1 AND  story.summary  LIKE :term2 
                  OR story.published = 1 AND  story.content LIKE :term3
                  OR story.published = 1 AND story.keyword LIKE :term4;";
                                       // SQL to count matches
        return $this->db->runSQL($sql, $arguments)->fetchColumn(); // Return number of matches
    }

    // Get number of search matches
    public function searchCount1(string $term1, $term2): int
    {
        $arguments['term1'] = $arguments['term2'] = $arguments['term3'] = $arguments['term4'] = '%' . $term1 . '%'; // Add wildcards to search term
        $arguments['term5'] = $arguments['term6'] = $arguments['term7'] = $arguments['term8'] = '%' . $term2 . '%'; // Add wildcards to search term
        $sql   = "SELECT COUNT(title)
                FROM story
                WHERE
                 (story.published = 1 AND  story.title   LIKE :term1 
                  OR story.published = 1 AND  story.summary  LIKE :term2 
                  OR story.published = 1 AND  story.content LIKE :term3
                  OR story.published = 1 AND story.keyword LIKE :term4)
                  AND
                  (story.published = 1 AND  story.title   LIKE :term5 
                  OR story.published = 1 AND  story.summary  LIKE :term6 
                  OR story.published = 1 AND  story.content LIKE :term7
                  OR story.published = 1 AND story.keyword LIKE :term8);";
                                       // SQL to count matches
        return $this->db->runSQL($sql, $arguments)->fetchColumn(); // Return number of matches
    }

    // Get story summaries of search matches
    public function search(string $term, int $show = 30, int $from = 0): array
    {
        $arguments['term1'] = $arguments['term2'] = $arguments['term3'] = $arguments['term4'] = '%' . $term . '%'; // Add wildcards to search term
        $arguments['show']  = $show;                          // Number of results to show
        $arguments['from']  = $from;                          // Number of results to skip
        $sql  = "SELECT a.id,a.website, a.title, a.summary, a.created, a.menu_id, a.member_id, a.family_id, a.published,
                        a.seo_title, a.storyorder, a.landscape, a.crop, a.allow_comment, a.keyword,
                        c.name     AS menu,
                        c.seo_name AS seo_menu,
                        m.forename, m.surname,
                        CONCAT(m.forename, ' ', m.surname) AS author,
                        i.file      AS image_file, 
                        i.alt       AS image_alt,
                        (SELECT COUNT(story_id) 
                           FROM likes 
                          WHERE likes.story_id = a.id) AS likes,
                        (SELECT COUNT(story_id) 
                           FROM comment 
                          WHERE comment.story_id = a.id) AS comments

                   FROM story     AS a
                   JOIN menu    AS c    ON a.menu_id = c.id
                   JOIN member      AS m    ON a.member_id   = m.id
                   LEFT JOIN image  AS i    ON a.image_id    = i.id

                  WHERE 
                     (a.published = 1 AND a.title   LIKE :term1) 
                     OR (a.published = 1 AND a.summary   LIKE :term2)
                     OR (a.published = 1 AND a.content   LIKE :term3)
                     OR (a.published = 1 AND a.keyword   LIKE :term4)
                    
                  ORDER BY a.id DESC
                  LIMIT :show 
                  OFFSET :from;";                                 // SQL to get story summaries
        return $this->db->runSQL($sql, $arguments)->fetchAll();  // Return story summaries
    }

       // Get story summaries of search matches with + connector
       public function search1(string $term1, string $term2, int $show = 30, int $from = 0): array
       {
           $arguments['term1'] = $arguments['term2'] = $arguments['term3'] = $arguments['term4'] = '%' . $term1 . '%'; // Add wildcards to search term
           $arguments['term5'] = $arguments['term6'] = $arguments['term7'] = $arguments['term8']  = '%' . $term2 . '%'; 
           $arguments['show']  = $show;                          // Number of results to show
           $arguments['from']  = $from;                          // Number of results to skip
           $sql  = "SELECT a.id,a.website, a.title, a.summary, a.created, a.menu_id, a.member_id, a.family_id, a.published,
                           a.seo_title, a.storyorder, a.landscape, a.crop, a.allow_comment, a.keyword,
                           c.name     AS menu,
                           c.seo_name AS seo_menu,
                           m.forename, m.surname,
                           CONCAT(m.forename, ' ', m.surname) AS author,
                           i.file      AS image_file, 
                           i.alt       AS image_alt,
                           (SELECT COUNT(story_id) 
                              FROM likes 
                             WHERE likes.story_id = a.id) AS likes,
                           (SELECT COUNT(story_id) 
                              FROM comment 
                             WHERE comment.story_id = a.id) AS comments
   
                      FROM story     AS a
                      JOIN menu    AS c    ON a.menu_id = c.id
                      JOIN member      AS m    ON a.member_id   = m.id
                      LEFT JOIN image  AS i    ON a.image_id    = i.id
   
                     WHERE 
                        ((a.published = 1 AND a.title   LIKE :term1) 
                        OR (a.published = 1 AND a.summary   LIKE :term2)
                        OR (a.published = 1 AND a.content   LIKE :term3)
                        OR (a.published = 1 AND a.keyword   LIKE :term4))
                       AND 
                       ((a.published = 1 AND a.title   LIKE :term5) 
                        OR (a.published = 1 AND a.summary   LIKE :term6)
                        OR (a.published = 1 AND a.content   LIKE :term7)
                        OR (a.published = 1 AND a.keyword   LIKE :term8))
                     ORDER BY a.id DESC
                     LIMIT :show 
                     OFFSET :from;";
                                                                     // SQL to get story summaries
           return $this->db->runSQL($sql, $arguments)->fetchAll();  // Return story summaries
       }
    /*
    //Get Story Order
       public function getStoryorder(int $id) {

 $sql = "SELECT (a.storyorder + 1) as 'storyorder'
                 FROM story    AS a
                 WHERE a.member_id = :id                    
                 ORDER BY a.storyorder DESC, a.member_id
                 LIMIT 1;";                          // Add GROUP BY clause
                 return $this->db->runSQL($sql, [$id])->fetch();  // Return story
         }
*/
    // get families
        public function getFamily(int $id) {
        $sql = "SELECT DISTINCT m2.id, CONCAT (m2.forename,' ',m2.surname) AS family
                FROM member as m
                JOIN member as m2 ON m2.id = m.account_id;";
                return $this->db->runSQL($sql, [$id])->fetch();  // Return story
         }

    // ADMIN METHODS
    // Get number of stories
    public function count(): int
    {
        $sql = "SELECT COUNT(id) FROM story                     
                WHERE story.member_id = $_SESSION[id];";
        return $this->db->runSQL($sql)->fetchColumn();   // Return count from result set
    }
 
    // Get total storage used by member
 
    public function used(int $id): int
 {
    $sql = "SELECT SUM(imagesize) from story 
            WHERE member_id = $id;";
    return $this->db->runSQL($sql)->fetchColumn();   // Return count from result set
 }
 // Save new story
    public function create(array $story, string $temporary, string $destination): bool
    
    {
    
        try {                                            // Try to insert data
            $this->db->beginTransaction();               // Start 
            if ($destination) {                          // If image uploaded
                // Crop and save file
                $image_data  = getimagesize($temporary);              // Get image data
                $orig_width  = $image_data[0];                        // Image width
                $orig_height = $image_data[1];
                $imagick = new \Imagick($temporary);       
               
                $imagick = new \Imagick($temporary);   
                $imagesize = $imagick->getImageLength();
                
                if ($orig_width>$orig_height) {
                    $imagick = new \Imagick($temporary);     // Object to represent image
                    if ($story['crop'] == 1) {
                    $imagick->cropThumbnailImage(1200, 700); // Create cropped image
                    }
                    $imagick->writeImage($destination);
                    $imagesize = $imagick->getImageLength();
                    $story['landscape'] = 1;
                    
                } else {
                    $imagick = new \Imagick($temporary);     // Object to represent image
                    if ($story['crop'] == 1) {
                    $imagick->cropThumbnailImage(420, 560); // Create cropped image
                    }
                    $imagick->writeImage($destination);
                    $story['landscape']=0;
                }      // Save file
                $imagick = new \Imagick($destination); 
                $imagesize = $imagick->getImageLength();             
            
                $sql = "INSERT INTO image (file, alt)
                        VALUES (:file, :alt);";          // SQL to add image
                $this->db->runSQL($sql, [$story['image_file'], $story['image_alt']]); // Add image to table

                $story['image_id'] = $this->db->lastInsertId();  // Return image id
            }

            $story['imagesize'] = $imagesize;
    
            unset ($story['id'], $story['image_file'], $story['image_alt']);
            $sql = "INSERT INTO story (website, title, summary, content, menu_id, member_id, family_id,
                       image_id, imagesize, published, seo_title, storyorder, landscape, crop, allow_comment, keyword)
                    VALUES (:website, :title, :summary, :content, :menu_id, :member_id, :family_id, :image_id,
                     :imagesize, :published, :seo_title, :storyorder, :landscape, :crop, :allow_comment, :keyword);"; // SQL to add story      

            $this->db->runSQL($sql, $story);           // Add story
            $this->db->commit();                         // Commit transaction
            return true;                                 // Return true
        } catch (\PDOException $e) {                     // If PDOException was raised 
            $this->db->rollBack();                       // Rollback transaction
            if (file_exists($destination)) {             // If image file exists
                unlink($destination);                    // Delete image file
            }
            if ($e->errorInfo[1] === 1062) {             // If an integrity constraint
                return false;                            // Return false
            } else {                                     // For all other reasons
                throw $e;                                // Re-throw exception
            }
        }
    }
    // Update existing story
    public function update(array $story, string $temporary, string $destination): bool
    { 
       
    
        try {  
                                                      // Try to update data
            $this->db->beginTransaction();               // Start transaction
            if ($destination) {                          // If image uploaded
               
                // Crop and save file
                $image_data  = getimagesize($temporary);              // Get image data
                $orig_width  = $image_data[0];                        // Image width
                $orig_height = $image_data[1];
                $imagick = new \Imagick($temporary);       
               
                $imagick = new \Imagick($temporary);   
                $imagesize = $imagick->getImageLength();
                
                if ($orig_width>$orig_height) {
                    $imagick = new \Imagick($temporary);     // Object to represent image
                    if ($story['crop'] == 1) {
                    $imagick->cropThumbnailImage(1200, 700); // Create cropped image
                    }
                    $imagick->writeImage($destination);
                    $imagesize = $imagick->getImageLength();
                    $story['landscape'] = 1;
                    
                } else {
                    $imagick = new \Imagick($temporary);     // Object to represent image
                    if ($story['crop'] == 1) {
                    $imagick->cropThumbnailImage(420, 560); // Create cropped image
                    }
                    $imagick->writeImage($destination);
                    $story['landscape']=0;
                }      // Save file
                $imagick = new \Imagick($destination); 
                $imagesize = $imagick->getImageLength();             
                $sql = "INSERT INTO image (file, alt)
                        VALUES (:file, :alt);";          // SQL to add image
                $this->db->runSQL($sql, [$story['image_file'], $story['image_alt']]); // Add image to image table
                $story['image_id'] = $this->db->lastInsertId(); // Add image id to $story
            }
            // Remove unwanted elements from $story
            unset($story['menu'], $story['seo_menu'], $story['created'], $story['forename'], $story['surname'], $story['author'], $story['image_file'], $story['image_alt'], $story['likes'], $story['comments']);
            $sql = "UPDATE story 
                       SET website = :website, title = :title, summary = :summary, content = :content, menu_id = :menu_id, member_id = :member_id, family_id = :family_id,
                           image_id = :image_id, imagesize = :imagesize, published = :published, seo_title = :seo_title, storyorder = :storyorder, landscape = :landscape,
                           crop = :crop, allow_comment = :allow_comment, keyword = :keyword 
                     WHERE id = :id;";                   // SQL statement
                
            $this->db->runSQL($sql, $story)->rowCount(); // Update story
            $this->db->commit();                         // Commit transaction
            return true;                                 // Update worked
        } catch (\PDOException $e) {                     // If PDOException was raised
            $this->db->rollBack();                       // Rollback transaction
            if (file_exists($destination)) {             // If image file exists
                unlink($destination);                    // Delete image file
            }
            if ($e->errorInfo[1] === 1062) {             // If an integrity constraint
                return false;                            // Return false
            } else {                                     // For all other reasons
              
                throw $e;                                // Re-throw exception
            }
        }
    }

    // Delete story
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM story WHERE id = :id;";    // SQL statement
        $this->db->runSQL($sql, [$id]);                  // Delete story
        return true;                                     // Return true
    }

    // Delete image from story
    public function imageDelete(int $image_id, string $path, int $story_id): bool
    {
        $sql = "UPDATE story SET image_id = null 
                 WHERE id = :story_id;";               // SQL statement
        $this->db->runSQL($sql, [$story_id]);             // Delete image from story
        $sql = "DELETE FROM image 
                 WHERE id = :id;";                       // SQL statement
        $this->db->runSQL($sql, [$image_id]);            // Delete image from image
        if (file_exists($path)) {                        // If image file exists
            unlink($path);                               // Delete image file
        }
        return true;                                     // Return true
    }

    // Update alt text for story image
    public function altUpdate(int $image_id, string $alt): bool
    {
        $sql = "UPDATE image SET alt = :alt) 
                 WHERE id = :story_id;";               // SQL statement
        $this->db->runSQL($sql, [$alt, $image_id]);      // Delete image from story
        return true;                                     // Return true
    }
}