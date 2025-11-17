<?php
declare(strict_types = 1); 
                                          // Use strict types
//include APP_ROOT . '/src/pages/menu-path.php';           // get path for website and menus
$guidetext = "";

if (! $id) {
   
        $website = $cms->getWebsite()->getById(intval($_SESSION['website']));
        $cms->getSession()->create(2,1); 
        
} else {  
    if($id != 99999) {
        if (!isset($_SESSION)) {
        $website = $cms->getWebsite()->getById(intval(1));
        $cms->getSession()->create(0,1);
        } else {
          
        $website = $cms->getWebsite()->getById(intval($id));
        $cms->getSession()->create(0,1);
        }
    } else {
        $website = $cms->getWebsite()->getById(intval($_SESSION['website']));
    }    
   }

if ($id > 1 and !isset($_SESSION['id']) and !isset($website['non_members']))  {
    $msg = "WARNING: You must be a registered member in order to access a GET FOCUSED website.  Click the Register link above to view subscription plans OR click the Refresh link for more photos on this page. ** Note: You may access websites marked as FREE-Access without a membership.";
    $data['failure'] = $msg;
    $website = $cms->getWebsite()->getById(1);
} else if ( $_SESSION['id']==2 and $website['non_members'] == 0) {
    $msg = "WARNING: You must be a registered member in order to access a GET FOCUSED website.  Click the Register link above to view subscription plans OR click the Refresh link for more photos on this page. ** Note: You may access websites marked as FREE-Access without a membership.";
    $data['failure'] = $msg;
    $website = $cms->getWebsite()->getById(1);
} else {
    
        $guidetext = $cms->getQuickguide()->getAll();
        $msg = implode("",$guidetext[0]);
        $data['success'] = $msg;
    
}


if(!isset($_SESSION['id'])) {
  $member = $cms->getMember()->get(2);
  $x =$cms->getWebsite()->getById(1);
  $mem = intval($x);
  $cms->getSession()->create($member,$mem); 
} else { 
    $member = $cms->getMember()->get(intval($_SESSION['id']));
    $mem = intval($member['account_id']);
    $cms->getSession()->create($member,$mem);
}

//$cms->getSession()->create(0,$website['id']);
// $data['failure']  = $_GET['failure'] ?? null;            // Check for failure message
if (!isset($_SESSION['id'])) {
    $cms->getSession()->create($member,$id); 
}


$data['stories']     = $cms->getStory()->getAll3(intval($website['id']), true, null, null,100); // Get latest story summaries

if (($member['id']) <= 1) {
    $data['navigation']  = $cms->getMenu()->getAll2(1,1);
    $cms->getSession()->create(0,$website['id']); 
} else {
   $data['navigation']  = $cms->getMenu()->getAll2($website['id'],$mem);  
}
$data['website']     = $website;             // Get menus
echo $twig->render('index.html', $data);                     // Render Twig template