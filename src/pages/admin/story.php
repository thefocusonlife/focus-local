<?php
// Part A: Setup
use PhpBook\Validate\Validate;                           // Import Validate namespace

is_admin($session->role);                                // Check if admin
include APP_ROOT . '/src/pages/menu-path.php';           // get path for website and menus
// Initialize variables that the PHP code needs
$member=[];



// initialize variables
$temp        = $_FILES['image']['tmp_name'] ?? '';       // Temporary image
$destination = '';                                      // Where to save file
$saved       = null;                                     // Did story save
$photocount  = 0;
$storyorder  = 0;
$families = [];
$image=[];
$website=[];
$landscape=[];
$allow_comment = true;
$arguments = [];
$id = false;
// Initialize variables needed for the HTML page

$story = [
    'id'          => 0,         // $id,
    'website'     => 1,
    'title'       => '',
    'summary'     => '.',
    'content'     => '.',
    'member_id'   => 0,
    'family_id'   => 0,
    'menu_id'     => 0,
    'image_id'    => null,
    'imagesize'   => 0,
    'published'   => false,
    'image_file'  => '',
    'image_alt'   => '.',
    'storyorder'  => 0,
    'landscape'   => 1,
    'allow_comment' => 1,
    'keyword'     => 'none',
    
];                                                       // Story data

$errors  = [
    'warning'     => '',
    'title'       => '',
    'summary'     => '',
    'content'     => '',
    'author'      => '',
    'menu'    => '',
    'image_file'  => '',
    'image_alt'   => '',
];

if (!empty($parts[2])) {              
    $id = intval($parts[2]);                                                 // If valid id
    $story = $cms->getStory()->get($id, false);                              // Get story data
    if (!$story) {                                                          // If story empty
        redirect('admin/stories/', ['failure' => 'Story not found']);       // Redirect
    }
}

//user's id from session
if ($id === 0) {                      
//logged in
//exit;
    redirect('login/');               
//not found
}
$saved_image = $story['image_file'] ? true : false;          // Has an image been uploaded
if($story['id']==false) {
    $authors = $cms->getMember()->get($_SESSION['id']);
    $families = $cms->getMember()->get($_SESSION['account_id']);
    $photocount =0;
} else {
    $authors     = $cms->getMember()->get($story['member_id']);                  // Get story member
    $families = $cms->getMember()->get($story['family_id']);
    $photocount  = intval($cms->getStory()->used($story['member_id']));
}
$menus       = $cms->getMenu()->getAll2($_SESSION['website'],$_SESSION['account_id']);                    // Get menus
$member      = $cms->getMember()->get($_SESSION['id']);

/*if (($story['id'])==false) {
    echo "admin/story.php -96";
    $families = $cms->getMember()->get($member['id']);
    $photocount =0;
} else {
    echo "admin/story.php -99";
    $families = $cms->getMember()->get($story['family_id']);
    $photocount  = intval($cms->getStory()->used($story['member_id']));
}
*/
if ($story['storyorder']<1) {
    $storyorder  = $cms->getStory()->getstoryorder(intval($authors['id']));         // get last storyorder
 } else {
    if ($storyorder<1) {
    $storyorder = intval($story['storyorder']);
    }
 }      
$website     = $cms->getwebsite()->getById($_SESSION['website']) ?? 1;


if (empty($storyorder)) {
    $story['storyorder'] = 1;
 } else {
    if ($story['storyorder']<1 ) { 
    $story['storyorder'] = intval($storyorder['storyorder']);  
    }
  }
                 
// Part B: Get and validate form data

if ($_SERVER['REQUEST_METHOD'] == 'POST') {              // If form submitted
    if (!empty($_FILES)) {      
        // If file bigger than limit in php.ini or .htaccess store error message
        $errors['image_file'] = ($_FILES['image']['error'] === 1) ? 'File too big-Resize using Paint ' : '';
        }// If file bigger than limit in php.ini or .htaccess store error message
      
    // If image was uploaded, get image data and validate
    //if ($temp and $_FILES['image']['error'] == 0) {      // Check file  
        if ($temp ) {      // Check file if errors not being generated -- need to determine why TOO BIG images not erroring out
        $story['image_alt']  = $_FILES['image']['name'];                    // Get alt text

        // Validate image data
        $errors['image_file']  = in_array(mime_content_type($temp), MEDIA_TYPES)
            ? '' : 'Wrong file type. ';                                  // Validate file type
           
        $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION)); // File extension in lowercase

        $errors['image_file'] .= in_array($extension, FILE_EXTENSIONS)
            ? '' : 'Wrong file extension. ';                             // Validate file extension
        $errors['image_file'] .= ($_FILES['image']['size'] <= MAX_SIZE)
            ? '' : 'File too big. Resize with Paint or other image utility. Max Size = ' . (MAX_SIZE/1000000). 'mb.';                                     // Validate file size -doesn't appear to be working
        $errors['image_alt']   = Validate::isText($story['image_alt'], 1, 254)
            ? '' : 'Alt text can be 1-1000 characters.';                 // Alt text

        if ($errors['image_file'] === '' and $errors['image_alt'] === '') {                  // If valid
            $story['image_file'] = create_filename($_FILES['image']['name'], UPLOADS);      // Path  S3 changes needed ?
            $destination = UPLOADS . $story['image_file'];                                  // Destination
        }
    }

    $story['title']       = $_POST['title'];                        // Get title
    $story['summary']     = $_POST['summary'];                      // Get summary
    $story['content']     = $_POST['content'];                      // Get content
    $story['member_id']   = intval($_POST['member_id']);            // Get member_id
    $story['family_id']   = intval( $_POST['family_id']);
    $story['menu_id']     = intval($_POST['menu_id']);              // Get menu_id
    $story['published']   = (isset($_POST['published'])) ? 1 : 0;   // Get navigation
    $story['seo_title']   = create_seo_name($story['title']);       // SEO friendly title
    $story['storyorder']  = intval($_POST['storyorder']);           // Get storyorder  
    $story['landscape']   = isset($_POST['landscape']);
    $story['allow_comment']  = isset($_POST['block']);
    $story['keyword']     = $_POST['keyword'];
    $story['website']     = intval($_SESSION['website']);
    $story['blog']        = intval($_POST['blog']);
    $authors              = $cms->getMember()->get(intval($_POST['member_id']));
    
    $purifier               = new HTMLPurifier();                     // Create Purifier
    $purifier->config->set('HTML.Allowed', 'p,br,strong,em,b,i,a[href],img[src|alt]'); // Permitted tags$purifier->config->set('HTML.Allowed', 'p,br,strong,em,b,i,a[href],img[src|alt]'); // Permitted tags
/*
disabling purify during development only to create guides and documentation
$story['content']     = $purifier->purify($story['content']);       // Purify content- validate useable embeded html

*/
    // Check if all data was valid and create error messages if it is invalid
    $errors['title']    = Validate::isText($story['title'], 1, 80)
        ? '' : 'Title should be 1 - 80 characters.';     // Validate title
    $errors['summary']  = Validate::isText($story['summary'], 1, 254)
        ? '' : 'Summary should be 0 - 254 characters.';  // Validate summary
    $errors['content']  = Validate::isText($story['content'], 1, 100000)
        ? '' : 'Content should be 1 - 100,000 characters.'; // Validate content
    //$errors['member']   = Validate::isMemberId($story['member_id'], $authors)
    //    ? '' : 'Not a valid author';                     // Validate author
    $errors['menu'] = Validate::isMenuId($story['menu_id'], $menus)
        ? '' : 'Not a valid menu';                   // Validate menu
    $errors['keyword']    = Validate::isText($story['keyword'], 1, 80)
        ? '' : 'Keyword should be 1 - 80 characters.';     // Validate title
    $invalid = implode($errors);

    // Part C: Check if data is valid, if so update database
    $arguments = $story;
    if ($invalid) { 
        ($story);                                                  // If invalid data
        $errors['warning'] =  $invalid;                            // Store error
    } else {  
        if ($arguments['id']) { 
         // If id exists update   
            $saved = $cms->getStory()->update($arguments, $temp, $destination); // Update story
        } else {  
         // No id create
            unset($arguments['id']);
            $saved = $cms->getStory()->create($arguments, $temp, $destination); // Create story
        }
        if ($saved == true) {   
        // If updated
          redirect('admin/stories/', ['success' => 'Story saved']); // Redirect
        } else { 
            // Otherwise
            $errors['warning'] = 'Story title already in use';         // Store message
        }
    }
    $story['image_file'] = $saved_image ? $story['image_file'] : ''; // Remove image if new story
}
$data['story']      = $story;                           // Story data for template
$data['menus']      = $menus;                           // Menu data for template
$data['authors']    = $authors;                         // Author data data for template
$data['member']     = $member;
$data['errors']     = $errors;                          // Error data for template
$data['storyorder'] = $storyorder;
$data['photocount'] = $photocount;
$data['family']     = $families;
$data['website']    = $cms->getWebsite()->getById(intval($story['website']));
if (!empty($msg)) {
    $data['failure'] = $msg;
}
echo $twig->render('admin/story.html', $data);         // Render Twig template
