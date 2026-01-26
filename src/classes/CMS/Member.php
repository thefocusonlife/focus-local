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

    public function updateSorttype(int $memberId, int $sorttypeId): void
    {
        $sql = "UPDATE member
            SET sorttype = :sorttype
            WHERE id = :id
            LIMIT 1";

        $this->db->runSql($sql, [
            'sorttype' => $sorttypeId,
            'id' => $memberId,
        ]);
    }

    public function updateRole(int $memberId, string $role): void
    {
        $sql = 'UPDATE member SET role = :role WHERE id = :id LIMIT 1;';
        $this->db->runSql($sql, [
            'role' => $role,
            'id' => $memberId,
        ]);
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
        $params = [
            'website' => (int) ($member['website'] ?? 0),
            'forename' => trim((string) ($member['forename'] ?? '')),
            'surname' => trim((string) ($member['surname'] ?? '')),
            'email' => trim((string) ($member['email'] ?? '')),
            'email_master' => trim((string) ($member['email_master'] ?? '')),
            'password' => (string) ($member['password'] ?? ''),
            'role' => (string) ($member['role'] ?? 'admin'),
            'status' => (string) ($member['status'] ?? 'pending'),
            'photo_limit' => (int) ($member['photo_limit'] ?? 0),
            'agegroup' => (int) ($member['agegroup'] ?? 0),
            'plan' => (int) ($member['plan'] ?? 1),
            'pagelimit' => (int) ($member['pagelimit'] ?? 50),
            'sorttype' => (int) ($member['sorttype'] ?? 1),
            'publik' => (int) ($member['publik'] ?? 1),
            'termsok' => (int) ($member['termsok'] ?? 0),
        ];

        if ($params['website'] <= 0 || $params['email'] === '' || $params['password'] === '') {
            return false;
        }

        $params['password'] = password_hash($params['password'], PASSWORD_DEFAULT);

        $started = false;
        error_log('[MEMBER create] ENTERED create()');

        try {
            $started = $this->db->beginTransaction();
            if (!$started) {
                throw new \RuntimeException('Failed to start transaction');
            }

            $sql = "
            INSERT INTO member
                (website, forename, surname, email, email_master, password, role, status,
                 photo_limit, agegroup, plan, pagelimit, sorttype, publik, termsok)
            VALUES
                (:website, :forename, :surname, :email, :email_master, :password, :role, :status,
                 :photo_limit, :agegroup, :plan, :pagelimit, :sorttype, :publik, :termsok);
        ";
            $this->db->runSql($sql, $params);

            $newId = (int) $this->db->lastInsertId();
            if ($newId <= 0) {
                throw new \RuntimeException('lastInsertId() returned 0');
            }

            $this->db->runSql(
                'UPDATE member SET account_id = :account_id WHERE id = :id LIMIT 1;',
                ['account_id' => $newId, 'id' => $newId],
            );

            if ($this->db->inTransaction()) {
                $this->db->commit();
            }

            return true;
        } catch (\PDOException $e) {
            error_log('[MEMBER CREATE ERROR]');
            error_log('SQLSTATE=' . $e->getCode());
            error_log('Message=' . $e->getMessage());
            return false;
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062) {
                return false;
            }

            throw $e;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // Update an existing member
    public function update(array $member): bool
    {
        unset($member['joined'], $member['picture']);

        // Whitelist fields that are actually updatable from the profile form
        $params = [
            'id' => (int) ($member['id'] ?? 0),
            'forename' => (string) ($member['forename'] ?? ''),
            'surname' => (string) ($member['surname'] ?? ''),
            'email' => (string) ($member['email'] ?? ''),
            'publik' => (int) ($member['publik'] ?? 0),
            'termsok' => (int) ($member['termsok'] ?? 0),
            'account_id' => (int) ($member['account_id'] ?? 0),
            'photo_limit' => (int) ($member['photo_limit'] ?? 0),
            'agegroup' => (int) ($member['agegroup'] ?? 0),
            'plan' => (int) ($member['plan'] ?? 0),
        ];

        if ($params['id'] <= 0) {
            return false;
        }

        $sql = "
        UPDATE member
           SET forename    = :forename,
               surname     = :surname,
               email       = :email,
               publik      = :publik,
               termsok     = :termsok,
               account_id  = :account_id,
               photo_limit = :photo_limit,
               agegroup    = :agegroup,
               plan        = :plan
         WHERE id = :id
         LIMIT 1;
    ";

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->runSQL($sql, $params);

            $this->db->commit();

            // rowCount() can be 0 if user saved without changing anything — treat that as success
            return $stmt !== false;
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062) {
                // duplicate email
                return false;
            }

            throw $e;
        }
    }

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
