<?php
namespace PhpBook\CMS; // Namespace declaration
use PDOStatement;
class Story
{
    public $id; // Store story id
    public $website; // Store website id
    public $title; // Store story title
    public $summary; // Store story summary
    public $content; // Store story content
    public $created; // Store story creation date
    public $menu_id; // Story story menu_id
    public $member_id; // Store story member_id
    public $family_id; // Store story family id
    public $published; // Store story published flag
    public $seo_title; // Store story seo_title
    public $storyorder; // Store story storyorder
    public $landscape; // Store story landscape flag
    public $allow_comment;
    public $keyword; // Store story keyword
    public $geomitry_info;

    protected $db; // Holds ref to Database object

    public function __construct(Database $db)
    {
        $this->db = $db; // Add ref to Database object
    }

    private function orderByForSorttype(?int $sorttypeId): string
    {
        $id = (int) ($sorttypeId ?? 0);

        switch ($id) {
            case 1:
                return 'ORDER BY RAND()';
            case 2:
                return 'ORDER BY a.created DESC, a.id DESC';
            case 3:
                return 'ORDER BY a.created ASC, a.id ASC';
            case 4:
                return 'ORDER BY a.title ASC, a.id ASC';
            case 5:
                return 'ORDER BY a.title DESC, a.id DESC';
            default:
                return 'ORDER BY a.created DESC, a.id DESC';
        }
    }

    // Get individual story
    public function get(int $id, bool $published)
    {
        $sql = "SELECT a.id, a.website, a.title, a.summary, a.content, a.created, a.menu_id, a.member_id, a.family_id, a.published, a.seo_title,
                     a.storyorder, a.landscape, a.allow_comment, a.keyword, a.blog,
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
                  LEFT JOIN menu AS c ON a.menu_id = c.id and c.website = a.website

                  JOIN member     AS m ON a.member_id   = m.id
                  LEFT JOIN image AS i ON a.image_id    = i.id
                 WHERE a.id = :id "; // SQL statement
        if ($published) {
            // If must be published
            $sql .= 'AND a.published = 1 '; // Add clause to SQL
        }

        $sql .= 'GROUP BY 1;'; // Add GROUP BY clause
        return $this->db->runSql($sql, [$id])->fetch(); // Return story
    }

    public function getForWebsite(int $id, int $websiteId, bool $published): ?array
    {
        if ($id <= 0 || $websiteId <= 0) {
            return null;
        }

        $sql = "SELECT a.id, a.website, a.title, a.summary, a.content, a.created, a.menu_id, a.member_id,
                   a.family_id, a.published, a.seo_title, a.storyorder, a.landscape, a.allow_comment,
                   a.keyword, a.blog,
                   c.name AS menu,
                   c.seo_name AS seo_menu,
                   m.forename, m.surname,
                   CONCAT(m.forename, ' ', m.surname) AS author,
                   i.id   AS image_id,
                   i.file AS image_file,
                   i.alt  AS image_alt,
                   (SELECT COUNT(story_id) FROM likes   WHERE likes.story_id   = a.id) AS likes,
                   (SELECT COUNT(story_id) FROM comment WHERE comment.story_id = a.id) AS comments
              FROM story AS a
              LEFT JOIN menu AS c ON a.menu_id = c.id and c.website = a.website

              JOIN member AS m ON a.member_id = m.id
              LEFT JOIN image AS i ON a.image_id = i.id
             WHERE a.id = :id
               AND a.website = :website ";

        if ($published) {
            $sql .= 'AND a.published = 1 ';
        }

        $sql .= "GROUP BY a.id
             LIMIT 1;";

        $row = $this->db
            ->runSql($sql, [
                'id' => $id,
                'website' => $websiteId,
            ])
            ->fetch();

        return $row ?: null;
    }

    /**
     * Fetch a story by id without applying published/draft filters.
     * Intended for ownership checks and admin tooling.
     */
    public function getByIdAnyStatus(int $id): ?array
    {
        $sql = 'SELECT * FROM story WHERE id = :id LIMIT 1;';
        $row = $this->db->runSql($sql, ['id' => $id])->fetch();
        return $row ?: null;
    }

    public function getAll(
        int $websiteId,
        ?int $menu = null,
        ?int $member = null,
        int $limit = 300,
        ?int $sorttypeId = null,
    ): array {
        $websiteId = (int) $websiteId;
        if ($websiteId <= 0) {
            return [];
        }

        $menu = $menu !== null ? (int) $menu : null;
        $member = $member !== null ? (int) $member : null;

        // Enforce a sane limit (and allow session override if you still want it)
        $limitIn = (int) ($_SESSION['pagelimit'] ?? 0);
        if ($limitIn > 0) {
            $limit = $limitIn;
        }
        $limit = max(1, min($limit, 500)); // hard cap

        $args = [
            'website' => $websiteId,
            'menu' => $menu,
            'menu1' => $menu,
            'member' => $member,
            'member1' => $member,
            'limit' => $limit,
        ];

        $sql = "SELECT a.id, a.website, a.title, a.summary, a.created, a.family_id, a.menu_id, a.member_id,
                   a.published, a.seo_title, a.storyorder, a.landscape, a.allow_comment, a.keyword, a.blog,
                   c.name AS menu,
                   c.seo_name AS seo_menu,
                   m.forename, m.surname,
                   CONCAT(m.forename, ' ', m.surname) AS author,
                   m.account_id,
                   i.file AS image_file,
                   i.alt  AS image_alt,
                   (SELECT COUNT(story_id) FROM likes   WHERE likes.story_id   = a.id) AS likes,
                   (SELECT COUNT(story_id) FROM comment WHERE comment.story_id = a.id) AS comments
              FROM story AS a
              LEFT JOIN menu   AS c ON a.menu_id = c.id AND c.website = a.website
              JOIN member AS m ON a.member_id = m.id
              LEFT JOIN image  AS i ON a.image_id = i.id
             WHERE a.website = :website
               AND (:menu1   IS NULL OR a.menu_id   = :menu)
               AND (:member1 IS NULL OR a.member_id = :member)";

        // Visibility rules: admin can see all; author can see own drafts; others published only
        $role = (string) ($_SESSION['role'] ?? 'guest');
        $viewerId = (int) ($_SESSION['id'] ?? 0);

        if ($role === 'admin') {
            $sql .= ' AND (a.published IN (0,1))';
        } elseif ($member !== null && $viewerId > 0 && $viewerId === $member) {
            // Viewer is the author being filtered; allow drafts
            $sql .= ' AND (a.published IN (0,1))';
        } else {
            $sql .= ' AND a.published = 1';
        }

        $orderBy = $this->orderByForSorttype($sorttypeId);
        $sql .= " {$orderBy} LIMIT :limit";

        // Drop unused args to avoid driver complaints (optional but nice)
        foreach (array_keys($args) as $k) {
            if (!str_contains($sql, ':' . $k)) {
                unset($args[$k]);
            }
        }

        return $this->db->runSql($sql, $args)->fetchAll();
    }

    // Get summaries of stories - Published and not-published
    public function getAll2(
        $website,
        $published,
        $menu = null,
        $member = null,
        $limit = 150,
        ?int $sorttypeId = null,
    ): array {
        $arguments = [
            'menu' => $menu,
            'menu1' => $menu,
            'member' => $member,
            'member1' => $member,
            'website' => $website,
        ];

        if (empty($_SESSION['pagelimit'])) {
            $arguments['limit'] = (int) $limit;
        } else {
            if ((int) ($_SESSION['id'] ?? 0) === 1) {
                $arguments['limit'] = 150; // UberAdmin management view
            } else {
                $arguments['limit'] = (int) $_SESSION['pagelimit'];
            }
        }

        $sql = "SELECT a.id, a.website, a.title, a.summary, a.created, a.family_id, a.menu_id, a.member_id,
                   a.published, a.seo_title, a.storyorder, a.landscape, a.allow_comment, a.keyword, a.blog,
                   c.name AS menu,
                   c.seo_name AS seo_menu,
                   m.forename, m.surname,
                   CONCAT(m.forename, ' ', m.surname) AS author,
                   m.account_id,
                   i.file AS image_file,
                   i.alt  AS image_alt,
                   (SELECT COUNT(story_id) FROM likes   WHERE likes.story_id   = a.id) AS likes,
                   (SELECT COUNT(story_id) FROM comment WHERE comment.story_id = a.id) AS comments
              FROM story AS a
              LEFT JOIN menu   AS c ON a.menu_id = c.id AND c.website = a.website
              JOIN member AS m ON a.member_id = m.id
              LEFT JOIN image  AS i ON a.image_id = i.id
             WHERE (:menu1 IS NULL OR a.menu_id = :menu)
               AND (:member1 IS NULL OR a.member_id = :member)
               AND a.website = :website";

        $role = (string) ($_SESSION['role'] ?? 'guest');
        $viewerId = (int) ($_SESSION['id'] ?? 0);

        if ($role === 'admin') {
            $sql .= ' AND (a.published IN (0,1))';
        } elseif ($viewerId > 0 && $member !== null && $viewerId === (int) $member) {
            $sql .= ' AND (a.published IN (0,1))';
        } else {
            $sql .= ' AND a.published = 1';
        }

        $orderBy = $this->orderByForSorttype($sorttypeId);
        $sql .= " {$orderBy} LIMIT :limit";

        foreach (array_keys($arguments) as $k) {
            if (!str_contains($sql, ':' . $k)) {
                unset($arguments[$k]);
            }
        }

        return $this->db->runSql($sql, $arguments)->fetchAll();
    }

    // Get summaries of stories - Published by website
    public function getAll3(
        $website,
        $published = null,
        $menu = null,
        $member = null,
        $limit = 300,
        $sorttypeId = null,
        $crossWebsite = false,
    ): array {
        $arguments['menu'] = $arguments['menu1'] = $menu; // Menu id
        $arguments['member'] = $arguments['member1'] = $member; // Author id
        $arguments['website'] = $website;
        $arguments['crossWebsite'] = $crossWebsite ? 1 : 0; // (optional; see below)

        //$arguments = array($website);
        // Setup file
        $path = mb_strtolower(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
        $path = substr($path, strlen(DOC_ROOT)); // Remove up to DOC_ROOT
        $path = trim($path, '/');
        $parts = explode('/', $path); // Split into array at /

        if ($parts[0] != 'admin') {
            // If an admin page
            $page = $parts[0] ?: 'index'; // Page name (or use index)
            $id = $parts[1] ?? null; // Get ID (or use null)
        } else {
            // If not an admin page
            $page = 'admin/' . ($parts[1] ?? ''); // Page name
            $id = $parts[2] ?? null; // Get ID
        }

        // Validate ID

        //$php_page = APP_ROOT . '/src/pages/' . $page . '.php';       // Path to PHP page

        if (empty($_SESSION['pagelimit'])) {
            $arguments['limit'] = $limit;
        } else {
            $arguments['limit'] = $_SESSION['pagelimit'];
        }

        $sql = "SELECT a.id, a.website, a.title, a.summary, a.created, a.family_id, a.menu_id, a.member_id, a.family_id, a.published,
a.seo_title, a.storyorder,a.landscape, a.allow_comment, keyword, a.blog,
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
JOIN website  AS w ON w.id = a.website
LEFT JOIN menu AS c ON a.menu_id = c.id and c.website = a.website

JOIN member     AS m ON a.member_id   = m.id
LEFT JOIN image AS i ON a.image_id    = i.id

WHERE w.is_active = 1
AND (a.menu_id = :menu OR :menu1 is null)
AND (a.member_id   = :member   OR :member1   is null)
AND (:crossWebsite = 1 OR a.website = :website)
AND (m.publik = 1)";
        $sessionRole = (string) ($_SESSION['role'] ?? 'guest');
        $sessionMemberId = (int) ($_SESSION['id'] ?? 0);
        $sessionSorttype = (int) ($_SESSION['sorttype'] ?? \TFOL_DEFAULT_SORTTYPE_ID);
        $effectiveSorttype = (int) ($sorttypeId ?? $sessionSorttype);
        $sessionSorttype = $effectiveSorttype; // <-- one-liner to honor menu-scoped sort

        if ($sessionRole === 'admin') {
            $sql .= ' AND (a.published = 0 or a.published = 1)';
        } elseif ($sessionMemberId === (int) $member) {
            $sql .= ' AND (a.published = 0 or a.published = 1)';
        } else {
            $sql .= ' AND (a.published = 1)';
        }
        $effectiveSorttype =
            (int) ($sorttypeId ?? ($_SESSION['sorttype'] ?? TFOL_DEFAULT_SORTTYPE_ID));

        $sorttypeId = (int) ($sorttypeId ?? TFOL_DEFAULT_SORTTYPE_ID);

        $orderBy = $this->orderByForSorttype($sorttypeId);
        $sql .= " $orderBy LIMIT :limit";

        foreach (array_keys($arguments) as $k) {
            if (!str_contains($sql, ':' . $k)) {
                unset($arguments[$k]);
            }
        }

        return $this->db->runSql($sql, $arguments)->fetchAll(); // Return data
        // SQL for story summary
    }

    // Get number of search matches
    public function searchCount(string $term): int
    {
        $arguments['term1'] = $arguments['term2'] = $arguments['term3'] = $arguments['term4'] =
            '%' . $term . '%'; // Add wildcards to search term
        $sql = "SELECT COUNT(title)
                FROM story
                WHERE story.published = 1 AND  story.title   LIKE :term1
                  OR story.published = 1 AND  story.summary  LIKE :term2
                  OR story.published = 1 AND  story.content LIKE :term3
                  OR story.published = 1 AND story.keyword LIKE :term4;";
        // SQL to count matches
        return $this->db->runSql($sql, $arguments)->fetchColumn(); // Return number of matches
    }

    // Get number of search matches
    public function searchCount1(string $term1, $term2): int
    {
        //$arguments['term1'] = $arguments['term2'] = $arguments['term3'] = $arguments['term4'] = '%' . $term1 . '%'; // Add wildcards to search term
        //$arguments['term5'] = $arguments['term6'] = $arguments['term7'] = $arguments['term8'] = '%' . $term2 . '%'; // Add wildcards to search term
        $arguments['term4'] = '%' . $term1 . '%'; // Add wildcards to search term
        $arguments['term8'] = '%' . $term2 . '%'; // Add wildcards to search term

        $sql = "SELECT COUNT(title)
                FROM story
                WHERE
                /* (story.published = 1 AND  story.title   LIKE :term1
                  OR story.published = 1 AND  story.summary  LIKE :term2
                  OR story.published = 1 AND  story.content LIKE :term3
                  OR story.published = 1 AND story.keyword LIKE :term4)
                  */
                  (story.published = 1 AND story.keyword LIKE :term4
                  AND
                  story.published = 1 AND story.keyword LIKE :term8);";
        /*(story.published = 1 AND  story.title   LIKE :term5
                  OR story.published = 1 AND  story.summary  LIKE :term6
                  OR story.published = 1 AND  story.content LIKE :term7
                  OR story.published = 1 AND story.keyword LIKE :term8);";
                */

        // SQL to count matches
        return $this->db->runSql($sql, $arguments)->fetchColumn(); // Return number of matches
    }

    // Get story summaries of search matches
    public function search(string $term, int $show = 30, int $from = 0): array
    {
        $arguments['term1'] = $arguments['term2'] = $arguments['term3'] = $arguments['term4'] =
            '%' . $term . '%'; // Add wildcards to search term
        $arguments['show'] = $show; // Number of results to show
        $arguments['from'] = $from; // Number of results to skip
        $sql = "SELECT a.id,a.website, a.title, a.summary, a.created, a.menu_id, a.member_id, a.family_id, a.published,
                        a.seo_title, a.storyorder, a.landscape, a.allow_comment, a.keyword, a.blog,
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
                  OFFSET :from;"; // SQL to get story summaries
        return $this->db->runSql($sql, $arguments)->fetchAll(); // Return story summaries
    }

    // Get story summaries of search matches with + connector
    public function search1(string $term1, string $term2, int $show = 30, int $from = 0): array
    {
        //$arguments['term1'] = $arguments['term2'] = $arguments['term3'] = $arguments['term4'] = '%' . $term1 . '%'; // Add wildcards to search term
        //$arguments['term5'] = $arguments['term6'] = $arguments['term7'] = $arguments['term8']  = '%' . $term2 . '%';
        $arguments['term4'] = '%' . $term1 . '%'; // Add wildcards to search term
        $arguments['term8'] = '%' . $term2 . '%';

        $arguments['show'] = $show; // Number of results to show
        $arguments['from'] = $from; // Number of results to skip
        $sql = "SELECT a.id,a.website, a.title, a.summary, a.created, a.menu_id, a.member_id, a.family_id, a.published,
                           a.seo_title, a.storyorder, a.landscape, a.allow_comment, a.keyword, blog,
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
                     (a.published = 1 AND a.keyword   LIKE :term4
                     AND
                     a.published = 1 AND a.keyword   LIKE :term8)
           /*             ((a.published = 1 AND a.title   LIKE :term1)
                        OR (a.published = 1 AND a.summary   LIKE :term2)
                        OR (a.published = 1 AND a.content   LIKE :term3)
                        OR (a.published = 1 AND a.keyword   LIKE :term4))
                       AND
                       ((a.published = 1 AND a.title   LIKE :term5)
                        OR (a.published = 1 AND a.summary   LIKE :term6)
                        OR (a.published = 1 AND a.content   LIKE :term7)
                        OR (a.published = 1 AND a.keyword   LIKE :term8))
            */
                     ORDER BY a.id DESC
                     LIMIT :show
                     OFFSET :from;";
        // SQL to get story summaries
        return $this->db->runSql($sql, $arguments)->fetchAll(); // Return story summaries
    }

    //Get Story Order
    public function getStoryorder(int $id)
    {
        $sql = "SELECT (a.storyorder+1) as 'storyorder'
                 FROM story    AS a
                 WHERE a.member_id = :id
                 ORDER BY a.storyorder DESC, a.member_id
                 LIMIT 1;"; // Add GROUP BY clause
        return $this->db->runSql($sql, [$id])->fetch(); // Return story
    }

    // get families
    public function getFamily(int $id)
    {
        $sql = "SELECT DISTINCT m2.id, CONCAT (m2.forename,' ',m2.surname) AS family
                FROM member as m
                JOIN member as m2 ON m2.id = m.account_id;";
        return $this->db->runSql($sql, [$id])->fetch(); // Return story
    }

    // ADMIN METHODS
    // Get number of stories
    public function count(): int
    {
        $sql = "SELECT COUNT(id) FROM story
                WHERE story.member_id = $_SESSION[id];";
        return $this->db->runSql($sql)->fetchColumn(); // Return count from result set
    }

    // Get total story count used by member

    public function used(int $id): int
    {
        // NOTE: Previously this calculated total disk usage by summing `imagesize`.
        // That field has been removed, and the quota system now uses story count instead.
        $sql = "SELECT COUNT(image_id)
            FROM story
            WHERE member_id = $id;";

        return $this->db->runSql($sql)->fetchColumn();
    }

    public function titleExists(string $title, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM story WHERE title = :title';
        $params = ['title' => $title];

        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }

        return (int) $this->db->runSql($sql, $params)->fetchColumn() > 0;
    }

    // Save new story (DB only)
    public function create(array $story): bool
    {
        unset($story['id'], $story['image_file'], $story['image_alt']);

        $sql = "INSERT INTO story (website, title, summary, content, menu_id, member_id, family_id,
               image_id, published, seo_title, storyorder, landscape, allow_comment, keyword, blog)
            VALUES (:website, :title, :summary, :content, :menu_id, :member_id, :family_id, :image_id,
             :published, :seo_title, :storyorder, :landscape, :allow_comment, :keyword, :blog);";

        $this->db->runSql($sql, $story);
        return true;
    }

    // Update story (DB only)
    public function update(array $story): bool
    {
        unset(
            $story['menu'],
            $story['seo_menu'],
            $story['created'],
            $story['forename'],
            $story['surname'],
            $story['author'],
            $story['image_file'],
            $story['image_alt'],
            $story['likes'],
            $story['comments'],
        );

        $sql = "UPDATE story
               SET website = :website,
                   title = :title,
                   summary = :summary,
                   content = :content,
                   menu_id = :menu_id,
                   member_id = :member_id,
                   family_id = :family_id,
                   image_id = :image_id,
                   published = :published,
                   seo_title = :seo_title,
                   storyorder = :storyorder,
                   landscape = :landscape,
                   allow_comment = :allow_comment,
                   keyword = :keyword,
                   blog = :blog
             WHERE id = :id;";

        $stmt = $this->db->runSql($sql, $story);

        return $stmt instanceof PDOStatement;
    }

    public function deleteForWebsite(int $id, int $websiteId): bool
    {
        if ($id <= 0 || $websiteId <= 0) {
            return false;
        }

        $sql = "DELETE FROM story
            WHERE id = :id
              AND website = :website
            LIMIT 1;";

        $stmt = $this->db->runSql($sql, [
            'id' => $id,
            'website' => $websiteId,
        ]);

        return $stmt->rowCount() === 1;
    }

    // Delete story
    public function delete(int $id): bool
    {
        $sql = 'DELETE FROM story WHERE id = :id;'; // SQL statement
        $this->db->runSql($sql, [$id]); // Delete story
        return true; // Return true
    }

    // Delete image from story
    public function imageDelete(int $image_id, string $path, int $story_id): bool
    {
        // 1. Clear the image_id on the story record
        $sql = "UPDATE story
               SET image_id = NULL
             WHERE id = :story_id";
        $this->db->runSql($sql, ['story_id' => $story_id]);

        // 2. Delete the image row itself

        $sql = "DELETE FROM image
             WHERE id = :id";
        $this->db->runSql($sql, ['id' => $image_id]);

        // 3. Delete the physical file
        if ($path && file_exists($path)) {
            unlink($path);
        }

        return true;
    }

    // Update alt text for story image
    public function altUpdate(int $image_id, string $alt): bool
    {
        $sql = "UPDATE image
               SET alt = :alt
             WHERE id = :image_id";
        $this->db->runSql($sql, [
            'alt' => $alt,
            'image_id' => $image_id,
        ]);

        return true;
    }
}
