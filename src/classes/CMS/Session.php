<?php
namespace PhpBook\CMS; // Declare namespace

class Session // Define Session class
{
    public $id; // Store member's id
    public $forename; // Store member's forename
    public $role; // Store member's role
    public $account_id; // Store member's account_id
    public $landscape;
    public $follow_id;
    public $pagelimit;
    public $sorttype;
    public $tempfollow;
    public $website;

    public function __construct()
    {
        $path = mb_strtolower($_SERVER['REQUEST_URI']); // Get path in lowercase
        $path = substr($path, strlen(DOC_ROOT)); // Remove up to DOC_ROOT
        $parts = explode('/', $path); // Split into array at /

        if ($parts[0] != 'admin') {
            // If an admin page
            $page = $parts[0] ?: 'index'; // Page name (or use index)
            $id = $parts[1] ?? null; // Get ID (or use null)
        } else {
            // If not an admin page
            $page = 'admin/' . ($parts[1] ?? ''); // Page name
            $id = $parts[2] ?? null; // Get ID
        } // Runs when object created
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->id = $_SESSION['id'] ?? 2; // Set id property of this object
        $this->forename = $_SESSION['forename'] ?? 'Guest'; // Set forename property of this object
        $this->role = $_SESSION['role'] ?? 'guest'; // Set role property of this object
        $this->account_id = $_SESSION['account_id'] ?? 1; // set account_id property of this object
        $this->landscape = $_SESSION['landscape'] ?? true;
        $this->follow_id = $_SESSION['follow_id'] ?? 0; // set follow_id property of this
        $this->pagelimit = $_SESSION['pagelimit'] ?? 200;
        $this->sorttype = $_SESSION['sorttype'] ?? 2;
        $this->website = $_SESSION['website'] ?? $id;
    }

    // Create new session
    public function create($member, $website)
    {
        session_regenerate_id(true);

        // Logged-in session ONLY if member array + valid id + active status
        if (
            is_array($member) &&
            (int) ($member['id'] ?? 0) > 0 &&
            (string) ($member['status'] ?? 'active') === 'active'
        ) {
            $_SESSION['id'] = (int) $member['id'];
            $_SESSION['forename'] = (string) ($member['forename'] ?? '');
            $_SESSION['role'] = (string) ($member['role'] ?? 'guest');
            $_SESSION['account_id'] = (int) ($member['account_id'] ?? 0);
            $_SESSION['landscape'] = true;
            $_SESSION['follow_id'] = (int) ($member['account_id'] ?? 0);
            $_SESSION['pagelimit'] = (int) ($member['pagelimit'] ?? 200);
            $_SESSION['sorttype'] = (int) ($member['sorttype'] ?? TFOL_DEFAULT_SORTTYPE_ID);
            $_SESSION['website'] = (int) ($member['website'] ?? ($website ?? 1));

            return; // ✅ stop here — authenticated session created
        }

        // Guest session (covers: not array, id<=0, pending, suspended)
        $_SESSION['id'] = 2;
        $_SESSION['forename'] = 'Guest';
        $_SESSION['role'] = 'guest';
        $_SESSION['account_id'] = 1;
        $_SESSION['landscape'] = true;
        $_SESSION['follow_id'] = 0;
        $_SESSION['pagelimit'] = 200;
        $_SESSION['sorttype'] = (int) ($_SESSION['sorttype'] ?? TFOL_DEFAULT_SORTTYPE_ID);

        $_SESSION['website'] = (int) ($website ?? 1);
    }

    // Update existing session - alias for create()
    public function update($member, $website = null)
    {
        $this->create($member, $website); // alias for create()
    }

    // Delete existing session
    public function delete(): void
    {
        // Clear session array
        $_SESSION = [];

        // Delete session cookie (if any)
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly'],
            );
        }

        // Destroy the session
        session_destroy();
    }

    /**
     * Force session into a clean Guest context (prevents identity bleed across websites).
     * Keeps website selection (or sets it if provided).
     */
    public function resetToGuest(?int $websiteId = null, bool $regenerateId = true): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // ---- Clear identity that can leak ----
        unset(
            $_SESSION['forename'],
            $_SESSION['role'],
            $_SESSION['account_id'],
            $_SESSION['member'],
            $_SESSION['member_id'],
            $_SESSION['email'],
            $_SESSION['logged_in'],
        );

        // ---- Clear UI/nav/page state that shouldn't carry across sites ----
        unset(
            $_SESSION['menu_owner_id'],
            $_SESSION['section'],
            $_SESSION['menu_id'],
            $_SESSION['active_sorttype_id'],
            // NOTE: decide whether you want to unset sorttype; leaving it is ok too
            // $_SESSION['sorttype'],
        );

        // ---- Set explicit Guest identity (TFOL conventions) ----
        $_SESSION['id'] = 2;
        $_SESSION['forename'] = 'Guest';
        $_SESSION['role'] = 'guest';
        $_SESSION['account_id'] = 1;

        // Other defaults your app expects
        $_SESSION['landscape'] = $_SESSION['landscape'] ?? true;
        $_SESSION['follow_id'] = 0;
        $_SESSION['pagelimit'] = $_SESSION['pagelimit'] ?? 200;
        $_SESSION['sorttype'] = $_SESSION['sorttype'] ?? TFOL_DEFAULT_SORTTYPE_ID;

        // Website context
        if ($websiteId !== null && $websiteId > 0) {
            $_SESSION['website'] = $websiteId;
        } elseif (empty($_SESSION['website'])) {
            $_SESSION['website'] = 1;
        }

        if ($regenerateId) {
            session_regenerate_id(true);
        }

        // Keep object properties in sync (optional but nice)
        $this->id = (int) $_SESSION['id'];
        $this->forename = (string) $_SESSION['forename'];
        $this->role = (string) $_SESSION['role'];
        $this->account_id = (int) $_SESSION['account_id'];
        $this->website = (int) $_SESSION['website'];
    }
}
