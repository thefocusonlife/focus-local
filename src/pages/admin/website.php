<?php
// Part A: Setup
use PhpBook\Validate\Validate;                           // Import Validate namespace
is_admin($session->role);                                // Check if admin

// Initialize variables that the PHP code needs
$member=[];
$temp        = $_FILES['image_file']['tmp_name'] ?? '';       // Temporary image
$destination = '';
$image       ='';                                     // Where to save file
$saved       = null;                                     // Did story save

// Initialize variables needed for the HTML page
$website = [
    'id'            => $_SESSION['id'],  //$id,
    'uber_id'        =>$_SESSION['id'],
    'name'          => '',
    'image_file'    => '',
    'alt'           => '',
    'non_members'   => 0   
];                                                       // Story data

$errors  = [
    'warning'        => '',
    'name'           => '',
    'image_file'     => '',
    'image_alt'      => '',
    'non_members'    => 0
];

if ($id) {                                               // If valid id
    $image = $cms->getWebsite()->get($id, false);      // Get website data
    
    if (!$image) {                                     // If website empty
        redirect('admin/websites/', ['failure' => 'Website not found']); // Redirect
    }
    $saved_image = $image['image_file'] ? true : false;          // Has an image been uploaded
}

$id = $cms->getSession()->id;         
//user's id from session
if ($id != 1) {
    redirect('index/');                 // must be Uber Admin to run this program.
}



// Part B: Get and validate form data

if ($_SERVER['REQUEST_METHOD'] == 'POST') {              // If form submitted
    // If file bigger than limit in php.ini or .htaccess store error message
    $website['id']              = $_POST['website_id'];
    
    $website['name']            = $_POST['name'];            // Get name
    $website['uber_id']         = $website['id'];
    //$website['image_file']      = $_POST['image_file'];      // Get image_file
    $website['alt']             = $_POST['alt'];             // Get alt
    $errors['image_file'] = ($_FILES['image_file']['error'] === 1) ? 'File too big ' : '';

    // If image was uploaded, get image data and validate
  
    if ($temp and $_FILES['image_file']['error'] == 0) {      // Check file
            
        // Validate image data
        $errors['image_file']  = in_array(mime_content_type($temp), MEDIA_TYPES)
            ? '' : 'Wrong file type. ';                                  // Validate file type
     
        $extension = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION)); // File extension in lowercase

        $errors['image_file'] = in_array($extension, FILE_EXTENSIONS)
            ? '' : 'Wrong file extension. ';                             // Validate file extension
        $errors['image_file'] .= ($_FILES['image_file']['size'] <= MAX_SIZE)
            ? '' : ' Resize. ';                                     // Validate file size
        $errors['alt']   = Validate::isText($website['alt'], 1, 254)
            ? '' : 'Alt text can be 1-1000 characters.';                 // Alt text

        if ($errors['image_file'] === '' and $errors['alt'] === '') {     // If valid
            $website['image_file'] = create_filename($_FILES['image_file']['name'], UPLOADS); // Path
            $destination = UPLOADS . $website['image_file'];             // Destination
        }
    }
   



    $purifier               = new HTMLPurifier();                     // Create Purifier
    $purifier->config->set('HTML.Allowed', 'p,br,strong,em,b,i,a[href],img[src|alt]'); // Permitted tags$purifier->config->set('HTML.Allowed', 'p,br,strong,em,b,i,a[href],img[src|alt]'); // Permitted tags
/*
disabling purify during development only to create guides and documentation
$story['content']     = $purifier->purify($story['content']); // Purify content
*/
    // Check if all data was valid and create error messages if it is invalid
    $errors['name']    = Validate::isText($website['name'], 1, 80)
        ? '' : 'Name should be 1 - 80 characters.';     // Validate title
    $errors['image_file']  = Validate::isText($website['image_file'], 1, 80)
        ? '' : 'Summary should be 0 - 254 characters.';  // Validate summary
    $errors['alt']  = Validate::isText($website['alt'], 1, 80)
        ? '' : 'Alt should be 1 - 80 characters.'; // Validate content
    // Part C: Check if data is valid, if so update database

 /*      if ($invalid) { 
 
        ($website);                                                  // If invalid data
        $errors['warning'] = 'Please correct form errors';              // Store error
    } else {  
 */                                                       // Otherwise
        $arguments = $website;  
        if ($arguments['id']>0) { 
// If id exists update
            $saved = $cms->getWebsite()->update($arguments, $temp, $destination); // Update story
        } else {  
        // No id create
            unset($arguments['id']);

            $saved = $cms->getWebsite()->create($arguments, $temp, $destination); // Create story
        }

        if ($saved == true) {   
                                        // If updated
          redirect('admin/websites/', ['success' => 'Story saved']); // Redirect
        } else { 
                                                      // Otherwise
            $errors['warning'] = 'Website name already in use';         // Store message
  
        }
    }

   // $website['image_file'] = $saved_image ? $website['image_file'] : ''; // Remove image if new website


$data['website']    = $website;                            // Story data for template
$data['errors']     = $errors;                              // Error data for template
echo $twig->render('admin/website.html', $data);            // Render Twig templateFile too big.