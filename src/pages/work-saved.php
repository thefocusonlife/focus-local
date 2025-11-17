<?php
declare(strict_types = 1);                               // Use strict types
use PhpBook\Validate\Validate;                           // Use Validate class



$story = [];
$arguments = [];                                          // Story data
$errors  = [];                                           // Error messages
$temp        = $_FILES['image']['tmp_name'] ?? '';       // Temporary image
$destination = '';                                       // Where to save file
$saved       = null;                                     // Did story save
$photocount = 0;
$order      = 0;

// Initialize variables needed for the HTML page
// Initialize variables needed for the HTML page
$story = [
    'id'          => $id,
    'title'       => '',
    'summary'     => '',
    'content'     => '',
    'member_id'   => 0,
    'family_id'   => 0,
    'menu_id'     => 0,
    'image_id'    => null,
    'imagesize'   => 0,
    'published'   => false,
    'image_file'  => '',
    'image_alt'   => '',
    'storyorder'  => 1,
    'landscape'   => 1,
    'crop'        => 1,
    'block'       => 0
    
];                                                       // Story data

$errors  = [
    'warning'     => '',
    'title'       => '',
    'summary'     => '',
    'content'     => '',
    'author'      => '',
    'menu'        => '',
    'image_file'  => '',
    'image_alt'   => '',
];


if ($id) {                                               // If valid id
    $story = $cms->getStory()->get($id, false);      // Get story data
    if (!$story) {                                     // If story empty
        redirect('admin/stories/', ['failure' => 'Story not found']); // Redirect
    }
}

$id = $cms->getSession()->id;         
//user's id from session
if ($id === 0) {                      
//logged in
    redirect('login/');               
//not found
}
$saved_image = $story['image_file'] ? true : false;    // Has an image been uploaded
$authors     = $cms->getMember()->getAll();              // Get all members
$menus  = $cms->getMenu()->getAll(); 
           // Get menus
$member = $cms->getMember()->get($id);
$families    = $cms->getMember()->getAll();
$photocount  = $cms->getStory()->count();                 // Get number of stories
$used        = $cms->getStory()->used();                  // get photo bytes consumed
$storyorder    = $cms->getStory()->getStoryorder($id);         // get last storyorder
$families    = $cms->getFamily()->getAll();

if ($story['storyorder'] == 0) {
$story['storyorder'] = $storyorder['storyorder'];
}
if ($storyorder == null) {
    $story['storyorder'] = 1; 
  }

$saved_image = $story['image_file'] ?? false;          // Has an image been uploaded


if ($_SERVER['REQUEST_METHOD'] == 'POST') {              // Form submitted
    // If file bigger than limit in php.ini or .htaccess store error message
    $errors['image_file'] = ($_FILES['image']['error'] === 1) ? 'File too big ' : '';
    

    // If image was uploaded, get image data and validate
    if ($temp and $_FILES['image']['error'] == 0) {      // Check file
        $errors['image_file']  = in_array(mime_content_type($temp), MEDIA_TYPES)
            ? '' : 'Wrong file type. ';                                   // File type
        $errors['image_file'] .= ($_FILES['image']['size'] <= MAX_SIZE)
            ? '' : 'File too big. Resize with Paint or other image utility. Max Size = ' . (MAX_SIZE/1000000). 'mb.' ;                                      // File size
       $story['image_alt'] = $_POST['image_alt'];                      // Get alt text
        $errors['image_alt']  = Validate::isText($story['image_alt'], 1, 254)
            ? '' : 'Alt text can be 1-1000 characters.';                  // Alt text
        if ($errors['image_file'] == '' and $errors['image_alt'] == '') { // If valid
            $story['image_file'] = create_filename($_FILES['image']['name'], 'uploads/'); // Filename
            $destination = UPLOADS . $story['image_file'];           
        }
  
    }

    $story['title']       = $_POST['title'];           // Get title
    $story['summary']     = $_POST['summary'];         // Get summary
    $story['content']     = $_POST['content'];         // Get content
    $story['member_id']   = intval($_POST['member_id']);       // Get member_id
    $story['family_id']   = intval($_POST['member_id']);
    $story['menu_id'] = intval($_POST['menu_id']);     // Get menu_id
    $story['published']   = 1;                         // Set published
    $story['seo_title']   = create_seo_name($story['title']); 
    $story['storyorder']    = intval($_POST['storyorder']);
    $story['landscape']   = intval($_POST['landscape']);
    $story['crop']         =intval($_POST['crop']) ;
    $story['block']        =intval($_POST['block']);
    $story['keyword']     = $_POST['keyword'];
    $purifier = new HTMLPurifier();                                                    // Create HTMLPurifier
    $purifier->config->set('HTML.Allowed', 'p,br,strong,em,b,i,a[href],img[src|alt]'); // Permitted tags
    $story['content'] = $purifier->purify($story['content']);                      // Purify content

    $errors['title']    = (Validate::isText($story['title'], 1, 80))
        ? '' : 'Title should be 1 - 80 characters.';                      // Validate title
    $errors['summary']  = (Validate::isText($story['summary'], 0, 254))
        ? ' ' : 'Summary should be 1 - 254 characters.';                   // Validate summary
    $errors['content']  = (Validate::isText($story['content'], 0, 100000))
        ? ' ' : 'Content should be 1 - 100,000 characters.';               // Validate content
    $errors['menu'] = Validate::isMenuId($story['menu_id'], $menus)
        ? '' : 'Not a valid menu';                                    // Validate menu

    $temp = $_FILES['image']['tmp_name'] ?? '';                           // Temporary image

$arguments = $story;   
    if ($id == false and $temp == '') {                                   // If new story with no temp image path
        $errors['image_file'] = 'Please upload an image';                 // Add error message to upload an image
    }

    $invalid = implode($errors);                                          // Join error messages

    if ($invalid) {                                                       // If invalid data
        $errors['message'] = 'Please correct form errors';                // Store message
    } else {                                                              // Otherwise
        if ($story['id']) {   
                                                  // If id exists: update
            $saved = $cms->getStory()->update($arguments, $temp, $destination); // Update story
        } else {  

                    // No id: create
             unset($arguments['id']);
            $saved = $cms->getStory()->create($arguments, $temp, $destination); // Create story
        }
        if ($saved === true) {   
             //  redirect('member/' . $cms->getSession()->id, ['success' => 'Story saved']); // Redirect
           redirect('member/' . $cms->getSession()->id);                 // Send to member page
        } else {
          
            $errors['message'] = 'Story title already in use';          // Store message
        }
    }
  
    $story['image_file'] = $saved_image ? $story['image_file'] : '';  // Remove image if new story
    
}

$data['navigation'] = $menus;                                  // Navigation
$data['menus'] = $menus;
$data['authors']    = $authors;
$data['story']    = $story;
$data['member']     = $member;                               // Story
$data['photocount'] = $photocount;
$data['storyorder']   = $storyorder;
$data['families']   = $families;

echo $twig->render('work.html', $data);                             // Render template