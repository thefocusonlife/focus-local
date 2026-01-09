<?php
namespace PhpBook\CMS; // Namespace declaration

class Member
{
    protected $db; // Holds ref to Database object

    public function __construct(Database $db)
    {
        $this->db = $db; // Add ref to Database object
    }

    // Get individual member by id
    public function get(int $id)
    {
        $sql = "SELECT id, website, forename, surname, email, email_master, joined, picture, role, status, account_id, photo_limit, agegroup, plan, pagelimit,sorttype,  publik, termsok
                  FROM member
                 WHERE id = :id;"; // SQL to get member
        return $this->db->runSQL($sql, [$id])->fetch(); // Return member
    }

    // Get details of all members
    public function getAll(): array
    {
        $sql = "SELECT id, website, forename, surname, email, email_master, joined, picture, role, status, account_id, photo_limit, agegroup, plan, pagelimit,sorttype,  publik, termsok
                  FROM member;"; // SQL to get all members
        return $this->db->runSQL($sql)->fetchAll(); // Return all members
    }
    // Get details of all members by website
    public function getAll2(int $id): array
    {
        $arguments = [$id];
        $sql = "SELECT id, website, forename, surname, email, email_master, joined, picture, role, status, account_id, photo_limit, agegroup, plan, pagelimit,sorttype,  publik, termsok
                  FROM member
                  WHERE website = :id;"; // SQL to get all members
        return $this->db->runSQL($sql, $arguments)->fetchAll(); // Return all members
    }
    public function getAll3(int $id): array
    {
        $arguments = [$id];
        $sql = "SELECT id, website, forename, surname, email, email_master, joined, picture, role, status, account_id, photo_limit, agegroup, plan, pagelimit,sorttype,  publik, termsok
                  FROM member
                  WHERE account_id = :id;"; // SQL to get all members
        return $this->db->runSQL($sql, $arguments)->fetchAll(); // Return all members
    }

    // Get individual member data using their emailDESC
    public function getIdByEmail(string $email)
    {
        $sql = "SELECT id
                  FROM member
                 WHERE email = :email;"; // SQL query to get member id
        return $this->db->runSQL($sql, [$email])->fetchColumn(); // Run SQL and return member id
    }

    //get last id
    public function getLastId(): array
    {
        $sql = "SELECT id
                  FROM member
                  ORDER BY id DESC;"; // SQL to get all members
        return $this->db->runSQL($sql)->fetch(); // Return all members
    }

    // Login: returns member data if authenticated, false if not
    public function login(string $email, string $password, int $website)
    {
        $arguments['email'] = $email;
        $arguments['website'] = $website;
        $sql = "SELECT id, website, forename, surname, joined, email, email_master, password, picture, role, status, account_id, photo_limit, agegroup, plan, pagelimit,sorttype,  publik, termsok
                  FROM member
                 WHERE email = :email
                 AND website = :website;"; // SQL to collect member data

        $member = $this->db->runSQL($sql, $arguments)->fetch(); // Run SQL
        if (!$member) {
            // If no member found
            return false; // Return false
        } // Otherwise
        $authenticated = password_verify($password, $member['password']);
        if (!$authenticated) {
            return false;
        }
        /*
        $status = (string) ($member['status'] ?? 'active'); // transitional default
        if ($status !== 'active') {
            return false; // pending/suspended cannot log in
        }
*/
        return $member;
    }
    // Login: returns member data if authenticated, false if not
    public function login2(string $email, string $password)
    {
        $arguments['email'] = $email;

        $sql = "SELECT id, website, forename, surname, joined, email, email_master, password, picture, role, status, account_id, photo_limit, agegroup, plan, pagelimit,sorttype,  publik, termsok
              FROM member
             WHERE email = :email;"; // SQL to collect member data

        $member = $this->db->runSQL($sql, $arguments)->fetch(); // Run SQL
        if (!$member) {
            // If no member found
            return false; // Return false
        } // Otherwise
        $authenticated = password_verify($password, $member['password']);
        if (!$authenticated) {
            return false;
        }
        /*
        $status = (string) ($member['status'] ?? 'active'); // transitional default
        if ($status !== 'active') {
            return false; // pending/suspended cannot log in
        }
        */
        return $member;
    }
    public function count(): int
    {
        $sql = "SELECT COUNT(id) FROM member
                WHERE member.account_id = $_SESSION[id];"; // SQL to count number of members
        return $this->db->runSQL($sql)->fetchColumn(); // Run SQL and return count
    }

    // Create a new member
    public function create(array $member): bool
    {
        $member['password'] = password_hash($member['password'], PASSWORD_DEFAULT); // Hash password

        try {
            // Try to add member
            $sql = "INSERT INTO member (website, forename, surname, email, email_master, password, role, account_id, photo_limit, agegroup, plan, pagelimit,sorttype,  publik, termsok)
                    VALUES (:website, :forename, :surname, :email, :email_master, :password, :role, :account_id, :photo_limit, :agegroup, :plan, :pagelimit, :sorttype, :publik, :termsok);"; // SQL to add member

            $this->db->runSQL($sql, $member); // Run SQL
            return true; // Return true
        } catch (\PDOException $e) {
            // If PDOException thrown
            if ($e->errorInfo[1] === 1062) {
                // If error indicates duplicate entry
                return false;
            } else {
                throw $e;
            } // Re-throw exception
        }
    }

    // Update an existing member
    public function update(array $member): bool
    {
        unset($member['joined'], $member['picture']); // Remove joined and member from array
        try {
            $this->db->beginTransaction(); // Start                                                         // Try to update member
            $sql = "UPDATE member
                       SET website = :website, forename = :forename, surname = :surname, email = :email, email_master = :email_master, role = :role,
                       account_id =:account_id, photo_limit = :photo_limit, agegroup = :agegroup, plan = :plan,
                       pagelimit = :pagelimit,sorttype = :sorttype,  publik = :publik, termsok = :termsok
                       WHERE id = :id;";
            // SQL to update member
            $this->db->runSQL($sql, $member);
            $this->db->commit(); // Commit transaction

            return true; // Return true
        } catch (\PDOException $e) {
            // If PDOException thrown
            if ($e->errorInfo[1] == 1062) {
                // If a duplicate (email in use)
                return false; // Return false
            } else {
                throw $e; // Any other error
            }
        } // Re-throw exception
    }

    // Upload member profile image
    /*  public function pictureCreate(int $id, string $filename, string $temporary, string $destination): bool
    {
        if ($temporary) {
            // If image uploaded
        // Crop and save file
        //$file_size = (filesize($temporary)/ 1000000);
        //var_dump_pre($file_size);

         $image_data  = getimagesize($temporary);              // Get tempory image data
        $orig_width  = $image_data[0];                        // Image width
        $orig_height = $image_data[1];                        // Image length
        // set cropping size for upload image

            $new_width = 350;                          // Square -- may want to give it a fixed siz later on
            $new_height = 350;

        // var_dump_pre($temporary);
        // var_dump_pre($image_data);
        //     exit;     // for testing image data
        $file_string = $destination;
        $file_extension = pathinfo($file_string, PATHINFO_EXTENSION);
        $file_extension = strtolower($file_extension);

        if ($file_extension == "jpg" or $file_extension =="jpeg") {
            $original_image = imagecreatefromjpeg($temporary);
        } elseif ($file_extension == "png") {
            $original_image = imagecreatefrompng($temporary);
        } elseif ($file_extension == "gif") {
            $original_image = imagecreatefromgif($temporary);
        } elseif ($file_extension == "bmp") {
            $original_image = imagecreatefrombmp($temporary);
        } else {

        }
         // See if it failed

    if(!$original_image)
    {
    // Create a black image
        $im  = imagecreatetruecolor(150, 30);
        $bgc = imagecolorallocate($im, 255, 255, 255);
        $tc  = imagecolorallocate($im, 0, 0, 0);

    imagefilledrectangle($im, 0, 0, 150, 30, $bgc);

    // Output an error message
    imagestring($im, 1, 5, 5, 'Error loading ' . $temporary, $tc);
    }



    $original_width = imagesx($original_image);
    $original_height = imagesy($original_image);

    // Calculate the new image dimensions
    $scale_ratio = min($new_width / $original_width, $new_height / $original_height);
    $width = intval($original_width * $scale_ratio);
    $height = intval($original_height * $scale_ratio);
    // Create a new blank image
    $new_image = imagecreatetruecolor($width, $height);
    // Resize the original image to fit the new image size
    // Load the original image

    //$source_image = imagecreatefromjpeg('path/to/small_image.jpg');

    // Get the dimensions of the original image
     $source_width = imagesx($new_image);
    $source_height = imagesy($new_image);

    // Create a new blank image with the desired dimensions

        $target_width = 350;
        $target_height = 350;

    $target_image = imagecreatetruecolor($target_width, $target_height);


    // Copy and resample the original image to the new image size
    imagecopyresampled($target_image, $new_image, 0, 0, 0, 0, $target_width, $target_height, $source_width, $source_height);
    $white = imagecolorallocate($new_image, 255,255, 255); // Set the background color to red
    //imagefill($new_image, 0, 0, $white);
    // Save the resized image to a file

    //imagejpeg($target_image, 'path/to/large_image.jpg');

    // Free up memory used by the image resources
    //imagedestroy($source_image);
    imagedestroy($target_image);

    imagecopyresampled($new_image, $original_image, 0, 0, 0, 0, $width, $height, $original_width, $original_height);

    // Set the crop coordinates
    $crop_x = ($width - $new_width) / 2;
    $crop_y = ($height - $new_height) / 2;

    // Create a new cropped image
    //$cropped_image = imagecrop($new_image, ['x' => $crop_x, 'y' => $crop_y, 'width' => $new_width, 'height' => $new_height]);



    // Save the image to a file
    $filename = $file_string;
    $folder = UPLOADS;
    $filepath = $filename;
    imagejpeg($new_image, $filepath);

    // Free up memory
    imagedestroy($original_image);
    imagedestroy($new_image);
    imagedestroy($target_image);
    $imageName = basename($filename);
        $sql = "UPDATE member
                   SET picture = :picture
                 WHERE id = :id;";                                  // SQL to create picture
        $this->db->runSQL($sql, ['id'=>$id, 'picture'=>$imageName]); // Run SQL pass in user id and filename
        return true;                                                // Done return true
    }
    }
*/
    // new code
    public function pictureCreate(
        int $id,
        string $filename,
        string $temporary,
        string $destination,
    ): bool {
        // Bail out early if no temp file provided
        if (!$temporary) {
            return false;
        }

        // Get image data and sizing
        $image_data = getimagesize($temporary);
        $orig_width = $image_data[0];
        $orig_height = $image_data[1];

        $new_width = 350;
        $new_height = 350;

        $file_string = $destination;
        $file_extension = strtolower(pathinfo($file_string, PATHINFO_EXTENSION));

        // Load the uploaded image based on its extension
        if ($file_extension === 'jpg' || $file_extension === 'jpeg') {
            $original_image = imagecreatefromjpeg($temporary);
        } elseif ($file_extension === 'png') {
            $original_image = imagecreatefrompng($temporary);
        } elseif ($file_extension === 'gif') {
            $original_image = imagecreatefromgif($temporary);
        } elseif ($file_extension === 'bmp') {
            $original_image = imagecreatefrombmp($temporary);
        } else {
            // Unsupported file type
            return false;
        }

        // Make sure we actually got an image resource
        if (!$original_image) {
            // couldn't load source image
            return false;
        }

        $original_width = imagesx($original_image);
        $original_height = imagesy($original_image);

        // Compute scale to fit inside 350x350
        $scale_ratio = min($new_width / $original_width, $new_height / $original_height);
        $width = intval($original_width * $scale_ratio);
        $height = intval($original_height * $scale_ratio);

        // Create resized canvas
        $new_image = imagecreatetruecolor($width, $height);

        // Prepare target square canvas (350x350)
        $target_width = 350;
        $target_height = 350;
        $target_image = imagecreatetruecolor($target_width, $target_height);

        // Resize original -> new_image
        imagecopyresampled(
            $new_image,
            $original_image,
            0,
            0,
            0,
            0,
            $width,
            $height,
            $original_width,
            $original_height,
        );

        // Now copy new_image into target_image, centered or padded
        // (Right now your code copies target_image <- new_image with all zeros,
        // which will stretch instead of center. You may want smarter centering,
        // but I'll leave your basic intent.)
        imagecopyresampled(
            $target_image,
            $new_image,
            0,
            0,
            0,
            0,
            $target_width,
            $target_height,
            imagesx($new_image),
            imagesy($new_image),
        );

        // Save final 350x350 as JPEG
        $filepath = $file_string;
        imagejpeg($target_image, $filepath);

        // Cleanup
        imagedestroy($original_image);
        imagedestroy($new_image);
        imagedestroy($target_image);

        // Save name in DB
        $imageName = basename($file_string);
        $sql = "UPDATE member
               SET picture = :picture
             WHERE id = :id;";
        $this->db->runSQL($sql, ['id' => $id, 'picture' => $imageName]);

        // Success
        return true;
    }

    // Delete member profile image
    public function pictureDelete(array $member, string $uploads): bool
    {
        $path = $uploads . $member['picture']; // Create path for image
        $unlink = unlink($path); // Delete image file
        if ($unlink === false) {
            // If failed throw exception
            throw new \Exception('Unable to delete image or image is missing');
        }
        $sql = "UPDATE member
                   SET picture = null
                 WHERE id = :id;"; // SQL to set picture to null
        $this->db->runSQL($sql, ['id' => $member['id']]); // Run SQL
        return true; // Return true
    }

    // Update member password
    public function passwordUpdate(int $id, string $password): bool
    {
        $hash = password_hash($password, PASSWORD_DEFAULT); // Hash the password
        $sql = 'UPDATE member
                   SET password = :password
                 WHERE id = :id'; // SQL to update password
        $this->db->runSQL($sql, ['id' => $id, 'password' => $hash]); // Run SQL
        return true; // Return true
    }

    // Get age groups
    public function getAgegroups(): array
    {
        $sql = "SELECT age, agegroup
                  FROM agegroup;"; // SQL to get all agegroup records
        return $this->db->runSQL($sql)->fetchAll(); // Return all agegroup records
    }

    // Get Plans
    public function getPlans(): array
    {
        $sql = "SELECT id, active, name, description, photolimit, monthly, annual
                  FROM plan
                  WHERE active = 1;"; // SQL to get all plan records
        return $this->db->runSQL($sql)->fetchAll(); // Return all plan records
    }

    // Get photolimit
    public function getphotolimit(int $id)
    {
        $sql = "SELECT photolimit, active
                  FROM plan
                  WHERE id = :id
                  AND active = 1;"; // SQL to get photolimit
        return $this->db->runSQL($sql, [$id])->fetch(); // plan photolimit   /
    }

    // Get Follow
    public function getFollows(): array
    {
        $sql = "SELECT to_id, to_account_id, from_id, from_account_id, status, request_date, response_date
                  FROM follow;"; // SQL to get all plan records
        return $this->db->runSQL($sql)->fetchAll(); // Return all plan records
    }
}
