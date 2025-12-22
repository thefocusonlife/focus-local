<?php
declare(strict_types = 1);                               // Use strict types
use PhpBook\Validate\Validate;                           // Use Validate class


$member=[];
$temp        = $_FILES['image']['tmp_name'] ?? '';       // Temporary image
$destination = '';                                     // Where to save file
$saved       = null;                                     // Did story save
$photocount  = 0;
$used        = 0;
$storyorder  = 0;
$families = null;
$image=[];
$website=[];
$landscape=[];
$allow_comment = true;

// Initialize variables needed for the HTML page

$story = [
    'id'          => $id,
    'website'     => '',
    'title'       => '',
    'summary'     => '.',
    'content'     => '.',
    'member_id'   => 0,
    'family_id'   => 0,
    'menu_id'     => 0,
    'image_id'    => null,
    'published'   => 0,
    'image_file'  => '',
    'image_alt'   => '.',
    'storyorder'  => 0,
    'landscape'   => 1,
    'allow_comment' => true,
    'keyword'     => 'none',
];                                                       // Story data

$errors  = [
    'warning'     => '',
    'website'     => '',
    'title'       => '',
    'summary'     => '',
    'content'     => '',
    'author'      => '',
    'menu'        => '',
    'image_file'  => '',
    'image_alt'   => '',
];
if (!empty($parts[1])) {
    $id = (int) $parts[1];

}
if ($id) {
    $story = $cms->getStory()->get($id,false);          // Get story data
    if (!$story) {                                     // If story is empty
        $cms->getSession()->id;

       include APP_ROOT . '/src/pages/page-not-found.php';  // Page not found
    }

    if ($story['member_id'] !== $cms->getSession()->id)  { // If not author of story
     if($_SESSION['id']>1) {
       include APP_ROOT . '/src/pages/page-not-found.php';  // Page not found
    }
}
}
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

$saved_image = $story['image_file'] ? true : false;          // Has an image been uploaded
if($story['id'] == false) {
    $authors = $cms->getMember()->get($_SESSION['id']);

}else {
    $authors     = $cms->getMember()->get($story['member_id']);
                   // Get all members
}
$menus       = $cms->getMenu()->getAll2($_SESSION['website'],$_SESSION['account_id']);                    // Get menus
$member      = $cms->getMember()->get($id);
if (($story['id'])==false) {

    $families = $cms->getMember()->get($member['account_id']);
    $photocount =0;
} else {

    $families = $cms->getMember()->get($story['family_id']);
    $photocount  = intval($cms->getStory()->used($story['member_id']));
}

$website     = $cms->getwebsite()->getById($_SESSION['website']) ?? 1;

if (empty($_SESSION) or $_SESSION['id']== 0) {
    if ($website['id'] > 1 and $website['non_members'] == 0) {
        redirect('index/99999', ['failure' => 'You must register as a member to access GET FOCUSED websites.
        Click the "Register" link on top of this page to see pricing.']);
        exit;

    }
}

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


if ($_SERVER['REQUEST_METHOD'] == 'POST') {              // Form submitted

    if (!empty($_FILES)) {
    // If file bigger than limit in php.ini or .htaccess store error message
    $errors['image_file'] = ($_FILES['image']['error'] === 1) ? 'File too big-Resize using Paint ' : '';
    }
    // If image was uploaded, get image data and validate
    if ($temp and $_FILES['image']['error'] == 0) {      // Check file
        $errors['image_file']  = in_array(mime_content_type($temp), MEDIA_TYPES)
            ? '' : 'Wrong file type. ';                                   // File type
        $errors['image_file'] .= ($_FILES['image']['size'] <= MAX_SIZE)
            ? '' : 'File too big. Resize with Paint or other image utility. Max Size = ' . (MAX_SIZE/1000000). 'mb.';                                      // File size
                               // Get alt text

        $story['image_alt'] = $_FILES['image']['name'];
        $errors['image_alt']  = Validate::isText($story['image_alt'], 1, 254)
            ? '' : 'Alt text can be 1-1000 characters.';                  // Alt text
        if ($errors['image_file'] == '' && $errors['image_alt'] == '') {
    // Image validated — actual saving handled by ImageService later
}


    }

    $story['website']     = intval($_POST['website']);         // get website id
    $story['title']       = $_POST['title'];           // Get title
    $story['summary']     = $_POST['summary'];         // Get summary
    $story['content']     = $_POST['content'];         // Get content
    $story['member_id']   = intval($_POST['member_id']);       // Get member_id
    $story['family_id']   = intval($_POST['member_id']);
    $story['menu_id']     = intval($_POST['menu_id']);     // Get menu_id
    $story['published']   = !empty($_POST['published']) ? 1 : 0; // Set public
    $story['seo_title']   = create_seo_name($story['title']);
    $story['storyorder']  = intval($_POST['storyorder']);
   $story['allow_comment']       = intval($_POST['allow_comment']);
    $story['keyword']     = $_POST['keyword'];

    $purifier               = new HTMLPurifier();                     // Create Purifier
    $purifier->config->set('HTML.Allowed', 'p,br,strong,em,b,i,a[href],img[src|alt]'); // Permitted tags$purifier->config->set('HTML.Allowed', 'p,br,strong,em,b,i,a[href],img[src|alt]'); // Permitted tags
/*
disabling purify during development only to create guides and documentation
$story['content']     = $purifier->purify($story['content']); // Purify content
*/

    // Check if all data was valid and create error messages if it is invalid
    $errors['title']    = Validate::isText($story['title'], 1, 80)
        ? '' : 'Title should be 1 - 80 characters.';     // Validate title
    $errors['summary']  = Validate::isText($story['summary'], 1, 254)
        ? '' : 'Summary should be 0 - 254 characters.';  // Validate summary
    $errors['content']  = Validate::isText($story['content'], 1, 100000)
        ? '' : 'Content should be 0 - 100,000 characters.'; // Validate content
    //$errors['member']   = Validate::isMemberId($story['member_id'], $authors)
    //    ? '' : 'Not a valid author';                     // Validate author
    $errors['menu'] = Validate::isMenuId($story['menu_id'], $menus)
        ? '' : 'Not a valid menu';                   // Validate menu
    $errors['keyword']    = Validate::isText($story['keyword'], 1, 80)
        ? '' : 'Keyword should be 1 - 80 characters.';     // Validate title
    $invalid = implode($errors);

    // Part C: Check if data is valid, if so update database

    if ($invalid) {

        ($story);                                                  // If invalid data
        $errors['warning'] = 'Please correct form errors';              // Store error
    } else {
                                                        // Otherwise
        $arguments = $story;
                                        // Save data as $arguments
                                        // ---------- Image upload via ImageService (UPDATE only) ----------
if (
    !empty($arguments['id']) &&
    isset($_FILES['image']) &&
    $_FILES['image']['error'] === UPLOAD_ERR_OK &&
    !empty($_FILES['image']['tmp_name'])
) {
    $arguments['image_alt'] = $arguments['image_alt'] ?? $_FILES['image']['name'];

   $result = $cms->getImageService()->saveUploadedStoryImage(
    $_FILES['image'],
    (int)$arguments['id'],
    $arguments['title'] ?? ''
);


    $arguments['image_id'] = (int)$imageId;
}


        if ($arguments['id']) {
            $saved = $cms->getStory()->update($arguments); // Update story
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

$data['story']      = $story;                          // Story data for template
$data['menus']      = $menus;                             // Menu data for template
$data['authors']    = $authors;                      // Author data data for template//
$data['member']     = $member;
$data['errors']     = $errors;                       // Error data for template
$data['storyorder'] = $storyorder;
$data['photocount'] = $photocount;
$data['family']     = $families;
$data['website']     = $cms->getWebsite()->getById(intval($authors['website']));

echo $twig->render('blog.html', $data);                             // Render template
