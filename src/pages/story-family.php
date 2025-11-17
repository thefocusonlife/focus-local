<?php
declare(strict_types = 1);                               // Use strict types
use PhpBook\Validate\Validate;                           // Import Validate namespace

$families = [];
$story = [];


  // is_admin($session->role); 


/*if (!$id) {                                                        // If no id
    redirect('page-not-found/');                                   // Page not found
}
*/

$story    = $cms->getStory()->get($id,true);  
$families    = $cms->getMember()->getAll();              // Get all members

if ($_SERVER['REQUEST_METHOD'] == 'POST') {                      // If form submitted
   $account_id = intVal($_POST['member_id']) ?? 1;                         // Get new role
   // $id = $_POST['ID'] ?? '6';
                     
        $story['family_id']=$account_id;

  
        $cms->getstory()->update($story,'','');                       // Update family id in database  <<< need to unset joined and member

        redirect('admin/stories/', ['success' => 'Family updated']); // Redirect with message
      
    }


$data['families'] = $families;                          // Author data data for template
$data['story']    = $story;
$data['website']  = $_SESSION['website'];
echo $twig->render('story-family.html', $data);                 // Render Twig template