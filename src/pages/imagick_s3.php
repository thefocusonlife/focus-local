<?php
declare(strict_types = 1); 
if (!empty($_FILES["input_file"])) {    

if ($_FILES["input_file"]["error"] !== UPLOAD_ERR_OK) {

    echo "<p>An error occurred.</p>";
    exit;
}    

// Move/Copy from temporary file to local file

$success = move_uploaded_file($_FILES["input_file"]["tmp_name"],
        'local_temp_file_directory/' . $_FILES["input_file"]["name"]);

if (!$success) {

    echo "<p>Unable to save file.</p>";
    exit;
}    
}

// I make a function to get the local file and save the edited file

function resizeImg($img_from_local_file, $width, $height, $pathToSaveImg) {

$i = new Imagick($img_from_local_file);
$gig = $i->getImageGeometry();

if(($gig['width']/$width) < ($gig['height']/$height)) {

    $i->cropImage($gig['width'], floor($height * $gig['width'] / $width), 0, (($gig['height'] - ($height * $gig['width'] / $width)) / 2));

} else {

    $i->cropImage(ceil($width * $gig['height'] / $height), $gig['height'], (($gig['width'] - ($width * $gig['height'] / $height)) / 2), 0);
}

$i->ThumbnailImage($width, $height,true);
$i->setImageFormat("jpeg");
$i->setImageCompressionQuality(90);
$i->writeImage($pathToSaveImg);
return $i->getimage();
}

// Call the resizeImg function

resizeImg("local_temp_file_directory/".$_FILES["input_file"]["name"], 40, 40, "local_temp_file_directory/resized_".$_FILES["input_file"]["name"]);

$s3->putObject(
S3::inputFile("local_temp_file_directory/resized_".$_FILES["input_file"]["name"]),
BUCKET_NAME, 
"make_a_new_image_name".date("his").".jpg", 
S3::ACL_PUBLIC_READ, 
array(), 
array("Content-Type"=>'image/jpeg'));

// Remove the temporary local file
unlink("local_temp_file_directory/".$_FILES["input_file"]["name"]);
unlink("local_temp_file_directory/resized_".$_FILES["input_file"]["name"]);
// Thats it... Enjoy it!