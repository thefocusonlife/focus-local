<?php
namespace PhpBook\CMS;

require_once __DIR__ . '/BicycleCommunity.php';
require_once __DIR__ . '/Club_members.php';

class CMS
{
    /**
     * =========================================
     * CORE SYSTEM OBJECTS
     * =========================================
     */

    /** @var mixed Database object (provides runSql()) */
    protected $db = null;

    /** @var ImageService|null */
    protected $imageService = null;

    /** @var Session|null */
    protected $session = null;

    /** @var Token|null */
    protected $token = null;

    /**
     * =========================================
     * CONTENT MODELS
     * =========================================
     */

    /** @var Story|null */
    protected $story = null;

    /** @var Menu|null */
    protected $menu = null;

    /** @var Comment|null */
    protected $comment = null;

    /** @var Like|null */
    protected $like = null;

    /** @var Family|null */
    protected $family = null;

    /** @var Member|null */
    protected $member = null;

    /** @var Follow|null */
    protected $follow = null;

    /**
     * =========================================
     * COMMUNITY / FEATURE MODULES
     * =========================================
     */

    /** @var \PhpBook\CMS\BicycleCommunity|null */
    protected $bicycleCommunity = null;
    /** @var \PhpBook\CMS\Club_members|null */
    protected $club_members = null;

    /**
     * =========================================
     * OPTIONAL / FUTURE
     * =========================================
     */

    /** @var mixed|null */
    protected $storyorder = null;

    /** @var pagelimit|null */
    protected $pagelimit = null; // Stores reference to Pagelimit objec

    /** @var sorttype|null */
    protected $sorttype = null; // Stores reference to Sorttype objec

    /** @var note|null */
    protected $note = null; // Stores reference to Note object

    /** @var notes|null */
    protected $notes = null;

    /** @var newnote|null */
    protected $newnote = null; // Stores reference to Newnote object
    /** @var notetype|null */
    protected $notetype = null;

    /** @var website|null */
    protected $website = null;

    /** @var quickguide|null */
    protected $quickguide = null;

    /** @var ride|null */
    protected $ride = null; //Stores reference to Ride object

    /**
     * @param mixed $dsn
     * @param mixed $username
     * @param mixed $password
     */
    public function __construct($dsn, $username, $password)
    {
        $this->db = new Database($dsn, $username, $password); // Create Database object
        $this->imageService = new ImageService(UPLOADS);
    }

    public function getDb(): Database
    {
        return $this->db;
    }

    public function getStory()
    {
        if ($this->story === null) {
            // If $story property null
            $this->story = new Story($this->db); // Create Story object
        }

        return $this->story; // Return Story object
    }

    public function getImageService(): ImageService
    {
        return $this->imageService;
    }

    public function getMenu()
    {
        if ($this->menu === null) {
            // If $menu property null
            $this->menu = new Menu($this->db); // Create Menu object
        }
        return $this->menu; // Return Menu object
    }

    public function getMember()
    {
        if ($this->member === null) {
            // If $member property null
            $this->member = new Member($this->db); // Create Member object
        }
        return $this->member; // Return Member object
    }

    public function getFamily()
    {
        if ($this->family === null) {
            // If $family property null
            $this->family = new family($this->db); // Create Family object
        }
        return $this->family; // Return Member object
    }

    public function getSession()
    {
        if ($this->session === null) {
            // If $session property null
            $this->session = new Session(); // Create Session object
        }
        return $this->session; // Return Session object
    }

    public function getToken()
    {
        if ($this->token === null) {
            // If $token property null
            $this->token = new Token($this->db); // Create Token object
        }
        return $this->token; // Return Token object
    }

    public function getLike()
    {
        if ($this->like === null) {
            // If $like property null
            $this->like = new Like($this->db); // Create Like object
        }
        return $this->like; // Return Like object
    }

    public function getComment()
    {
        if ($this->comment === null) {
            // If $comment property null
            $this->comment = new Comment($this->db); // Create Comment object
        }
        return $this->comment; // Return Comment object
    }
    /*     public function getStoryorder()
    {
        if ($this->storyorder === null) {                      // If $position property null
            $this->storyorder = new Storyorder($this->db);     // Create Position object
        }
        return $this->storyorder;                              // Return Position object
    }
  */
    public function getQuickguide()
    {
        if ($this->quickguide === null) {
            // If $quickguide property null
            $this->quickguide = new Quickguide($this->db); // Create Quickguide object
        }
        return $this->quickguide; // Return Quickguide object
    }

    public function getPagelimit()
    {
        if ($this->pagelimit === null) {
            // If $pagelimit property null
            $this->pagelimit = new Pagelimit($this->db); // Create Pagelimit object
        }
        return $this->pagelimit; // Return Pagelimit object
    }

    public function getSorttype()
    {
        if ($this->sorttype === null) {
            // If $sorttype property null
            $this->sorttype = new Sorttype($this->db); // Create Sorttype object
        }
        return $this->sorttype; // Return Sorttype object
    }

    public function getByMenu(int $menuId): array
    {
        // menu_sorttype mapping removed; return all sorttypes
        return $this->getSorttype()->getAll();
    }

    public function getDefaultIdForMenu(int $menuId): int
    {
        // menu_sorttype mapping removed; pick a safe default
        // Prefer 1 if present
        $row = $this->db->runSql('SELECT 1 FROM sorttype WHERE id = 1 LIMIT 1;')->fetch();
        if ($row) {
            return 1;
        }

        // Next, prefer 9 if present (your old fallback)
        $row = $this->db->runSql('SELECT 1 FROM sorttype WHERE id = 9 LIMIT 1;')->fetch();
        if ($row) {
            return 9;
        }

        // Ultimate fallback: smallest id available
        $row = $this->db->runSql('SELECT id FROM sorttype ORDER BY id ASC LIMIT 1;')->fetch();
        return $row && isset($row['id']) ? (int) $row['id'] : 1;
    }

    public function isAllowedForMenu(int $menuId, int $sorttypeId): bool
    {
        // menu_sorttype mapping removed; any existing sorttype is allowed
        return $this->getSorttype()->isAllowedForMenu($menuId, $sorttypeId);
    }

    public function getFollow()
    {
        if ($this->follow === null) {
            // If $follow property null
            $this->follow = new follow($this->db); // Create follow object
        }
        return $this->follow; // Return follow object
    }
    public function getNote()
    {
        if ($this->note === null) {
            // If $notification property null
            $this->note = new Note($this->db); // Create notification object
        }
        return $this->note; // Return notification object
    }
    public function getNotes()
    {
        if ($this->notes === null) {
            // If $notification property null
            $this->notes = new Notes($this->db); // Create notification object
        }
        return $this->notes; // Return notification object
    }

    public function getNewNote()
    {
        if ($this->newnote === null) {
            // If $notification property null
            $this->newnote = new Newnote($this->db); // Create notification object
        }
        return $this->newnote; // Return notification object
    }
    public function getNotetype()
    {
        if ($this->notetype === null) {
            // If $notetype property null
            $this->notetype = new notetype($this->db); // Create notetype object
        }
        return $this->notetype; // Return notetype object
    }
    public function getWebsite()
    {
        if ($this->website === null) {
            // If $website property null
            $this->website = new Website($this->db); // Create website object
        }
        return $this->website; // Return website object
    } // Return website object

    public function getRide(): Ride
    {
        // if (!$this->ride === null) {
        $this->ride = new Ride($this->db);
        // }
        return $this->ride;
    }

    public function getClubMembers()
    {
        if ($this->club_members === null) {
            $this->club_members = new \PhpBook\CMS\Club_members($this->db);
        }

        return $this->club_members;
    }

    public function getBicycleCommunity()
    {
        return new BicycleCommunity($this->db);
    }

    public function redirect(string $path, array $params = []): void
    {
        // Ensure path is relative (no leading slash required)
        $url = DOC_ROOT . ltrim($path, '/');

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        header('Location: ' . $url);
        exit();
    }
}
