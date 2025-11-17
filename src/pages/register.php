<?php
declare(strict_types = 1);                               // Use strict types
use PhpBook\Validate\Validate;                           // Import Validate class
$member = [];                                            // Initialize member array
$errors = [];
$agegroups = [];
$plans     = [];                                            // Initialize errors array
$abc       =[];
$data      =[];
$last_id   = 0;
$lastid    = 0;

//echo $twig->render('plans.html');
//exit;



if ($_SERVER['REQUEST_METHOD'] != 'POST') {              // If form doesn't exist
    $agegroups = $cms->getMember()->getAgegroups();
    $plans     = $cms->getMember()->getPlans();             
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {              // If form was posted
    // Get form data
    $member1 = $cms->getMember()->get($_SESSION['id']);
    $member['website']  = intval($_POST['website']);             // Get current website
    $member['forename'] = $_POST['forename'];            // Get forename
    $member['surname']  = $_POST['surname'];             // Get surname
    $member['password'] = $_POST['password'];            // Get password
    $confirm            = $_POST['confirm'];             // Get password confirmation
    $member['email_master'] =$_POST['email'];
    if ($_POST['website'] != 1) {
        $member['email'] =$_POST['email'] . $_POST['website'];
    } else {
        $member['email'] =$_POST['email'];    
    }
    $member['account_id']   = intval($_POST['lastid']);
    $abc = $cms->getMember()->getPhotolimit( intval($_POST['plan'])) ;
    $member['photo_limit']  =(($abc['photolimit'])) ;
    $member['agegroup']     = intval($_POST['agegroup']);
    $member['plan']         = intval($_POST['plan']);
    $member['pagelimit']    = intval(50);
    $member['sorttype']     = intval(4);
    $member['publik']       = 1;
    $member['termsok']      = 0;
    if ($member['plan']     != 3) {
        $member['role']    = "pending";
    } else {
        $member['role']     = "family";
    }
    if($_POST['website'] != 1) {
    $valid = $cms->getMember()->getIdByEmail($_POST['email']);
    if ($valid == 0){
        $errors['master'] = 'You must register with main theFocusOnLife website in order to register with this website.';
    } else {
        $errors['master'] = '';
    }
    }
    // Validate form data
    $errors['forename'] = Validate::isText($member['forename'], 1, 254)
        ? '' : 'Forename must be 1-254 characters';
    $errors['surname']  = Validate::isText($member['surname'], 1, 254)
        ? '' : 'Surname must be 1-254 characters';
    $errors['email']    = Validate::isEmail($member['email'])
        ? '' : 'Please enter a valid email';
    $errors['password'] = Validate::isPassword($member['password'])
        ? '' : 'Passwords must be at least 8 characters and have:<br> 
                A lowercase letter<br>An uppercase letter<br>A number 
                <br>And a special character';
    $errors['confirm']  = ($member['password'] = $confirm)
        ? '' : 'Passwords do not match';
    $invalid            = implode($errors);                  // Join error messages
    
    
    if (!$invalid) {                                         // If no errors
        $result = $cms->getMember()->create($member);        // Create member + store result
        if ($result === false) {                             // If result is false
            //$errors['email'] = 'Email address already used click Back refresh page re-enter appending Website ID shown below'; // Store a warning
           if($member['website'] >1 ) {
            redirect('register/3', ['failure' => 
            'Email address already used.  Append Website ID to email address.']); // Redirect with error
           } else {
            redirect('register/3', ['failure' => 
            'Email address already used.  Only one email address allowed on the Focus On Life website.']); // Redirect with error      
           }
        } else {    
        // Otherwise send to login
           redirect('index/' . $member['website']);
        }
        
    }
}
$path  = mb_strtolower($_SERVER['REQUEST_URI']);             // Get path in lowercase
$path  = substr($path, strlen(DOC_ROOT));                    // Remove up to DOC_ROOT
$parts = explode('/', $path);                                // Split into array at /

if ($parts[0] != 'admin') {                                  // If an admin page
    $page = $parts[0] ?: 'index';                            // Page name (or use index)
    $id   = ( $parts[1]) ?? null;                               // Get ID (or use null)
} else {                                                     // If not an admin page
    $page = 'admin/' . ($parts[1] ?? '');                    // Page name
    $id   = $parts[2] ?? null;                               // Get ID
}
if (! $id) {
    $id = 1;
}
$website = $cms->getWebsite()->getById(intval($id));
/*
if (! $_SESSION['id']) {
    $mem =1;
} else { 
    $member = $cms->getMember()->get(intval($_SESSION['id']));
    $men = $member['account_id'];
}
*/
//get last id and increment by 1 for account_ID
$lastid = ($cms->getMember()->getLastId());
$last_id = intval($lastid['id']);
$last_id = $last_id+1;
$member = [];
$data['success']  = $_GET['success'] ?? null;            // Check for success message
$data['failure']  = $_GET['failure'] ?? null;            // Check for failure message
//$data['navigation'] = $cms->getMenu()->getAll2($website['id'],$men);         // All menus for nav
//$data['member']     = $member;                               // Member data
$data['agegroups']  = $agegroups;
$data['plans']      = $plans;
$data['errors']     = $errors;                               // Error messages
$data['website']    = $website; // $cms->getWebsite()->getById(intval($id));
$data['lastid']     = $last_id;

echo $twig->render('register.html', $data);                  // Render Twig template
//echo $twig->render('plans.html', $data);