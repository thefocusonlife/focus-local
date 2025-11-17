<?php
namespace PhpBook\CMS;                                   // Declare namespace

class Session
{                                                        // Define Session class
    public $id;                                          // Store member's id
    public $forename;                                    // Store member's forename
    public $role;                                        // Store member's role
    public $account_id;                                  // Store member's account_id
    public $landscape;
    public $follow_id;
    public $pagelimit;
    public $sorttype;
    PUBLIC $tempfollow;
    public $website;

    
    public function __construct()
    {   
        $path  = mb_strtolower($_SERVER['REQUEST_URI']);             // Get path in lowercase
        $path  = substr($path, strlen(DOC_ROOT));                    // Remove up to DOC_ROOT
        $parts = explode('/', $path);                                // Split into array at /

        if ($parts[0] != 'admin') {                                  // If an admin page
            $page = $parts[0] ?: 'index';                                      // Page name (or use index)
            $id   = $parts[1] ?? null;                               // Get ID (or use null)
        } else {                                                     // If not an admin page
            $page = 'admin/' . ($parts[1] ?? '');                    // Page name
            $id   = $parts[2] ?? null;                               // Get ID
        }                                                 // Runs when object created
        session_start();                                 // Start, or restart, session
        $this->id         = $_SESSION['id'] ?? 2;          // Set id property of this object
        $this->forename   = $_SESSION['forename'] ?? 'Guest';   // Set forename property of this object
        $this->role       = $_SESSION['role'] ?? 'guest'; // Set role property of this object
        $this->account_id = $_SESSION['account_id'] ?? 1;   // set account_id property of this object
        $this->landscape  = $_SESSION['landscape'] ?? true;
        $this->follow_id  = $_SESSION['follow_id'] ?? 0;   // set follow_id property of this
        $this->pagelimit  = $_SESSION['pagelimit'] ?? 200;
        $this->sorttype   = $_SESSION['sorttype']  ?? 1;
        $this->website    = $_SESSION['website']   ?? $id;
       
    }

    // Create new session
    public function create($member,$website)
    {
       
        session_regenerate_id(true);                       // Update session id
        if (( $member >0)) {
       // var_dump_pre($member);
       // var_dump_pre($_SESSION);
       // echo "Session -52";
       // exit;
        $_SESSION['id']         = $member['id'];           // Add member id to session
        $_SESSION['forename']   = $member['forename'];     // Add forename to session
        $_SESSION['role']       = $member['role'];         // Add role to session
        $_SESSION['account_id'] = $member['account_id'];  // Add account_id to session
        $_SESSION['landscape']  = true;
        $_SESSION['follow_id']  = $member['account_id'];  // Add account_id to session
        $_SESSION['pagelimit']  = $member['pagelimit'];   // Add pagelimit to session
        $_SESSION['sorttype']   = $member['sorttype'];    // Add sottype to session
        $_SESSION['website']    = $member['website'];     // Add member's website o session
        } else {
            $_SESSION['id']         = 2;                  // Add member id to session
            $_SESSION['forename']   = 'Guest';                 // Add forename to session
            $_SESSION['role']       = 'guest';                 // Add role to session
            $_SESSION['account_id'] = 1;                  // Add account_id to session
            $_SESSION['landscape']  = true;
            $_SESSION['follow_id']  = 0;                  // Add account_id to session
            $_SESSION['pagelimit']  = 200;                // Add pagelimit to session
            $_SESSION['sorttype']   = 1;             // Add sottype to session
            $_SESSION['website']    = $website ?? 1;                  // Add member's website o sess   
      
        }
       
    }

    // Update existing session - alias for create()
    public function update($member)
    {
        $this->create($member);                          // Update data in session
       
    }

    // Delete existing session
    public function delete()
    {
        $_SESSION = [];                                  // Empty $_SESSION superglobal
        $param    = session_get_cookie_params();         // Get session cookie parameters
        setcookie(session_name(), '', time() - 2400, $param['path'], $param['domain'],
            $param['secure'], $param['httponly']);       // Clear session cookie
        session_destroy();                               // Destroy the session
    }
}