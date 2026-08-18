<?php
namespace PhpBook\CMS; // Namespace declaration

class Member
{
    /** @var object Database wrapper with runSql() */
    protected $db;

    public function __construct(Database $db)
    {
        $this->db = $db; // Add ref to Database object
    }
    // Get individual member by id
    public function get(int $id)
    {
        $sql = "SELECT id, website, forename, surname, email, email_master, joined, picture, role, isUberAdmin, status, account_id, photo_limit, agegroup, plan, pagelimit,sorttype, publik, termsok,public_ride_leaderboard
                  FROM member
                 WHERE id = :id;"; // SQL to get member
        return $this->db->runSql($sql, [$id])->fetch(); // Return member
    }
    // Get member by id for sensitive flows that require password verification
    public function getForEmailChangeById(int $id): array
    {
        $sql = "
        SELECT id, website, forename, surname, email, email_master, password, role, status, account_id
        FROM member
        WHERE id = :id
        LIMIT 1;
    ";

        $stmt = $this->db->runSql($sql, ['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: [];
    }
    // Get details of all members
    public function getAll(): array
    {
        $sql = "SELECT id, website, forename, surname, email, email_master, joined, picture, role, status, account_id, photo_limit, agegroup, plan, pagelimit,sorttype,  publik, termsok
                  FROM member;"; // SQL to get all members
        return $this->db->runSql($sql)->fetchAll(); // Return all members
    }
    // Get details of all members by website
    public function getAll2(int $id): array
    {
        $arguments = [$id];
        $sql = "SELECT id, website, forename, surname, email, email_master, joined, picture, role, status, account_id, photo_limit, agegroup, plan, pagelimit,sorttype,  publik, termsok
                  FROM member
                  WHERE website = :id;"; // SQL to get all members
        return $this->db->runSql($sql, $arguments)->fetchAll(); // Return all members
    }

    public function getAll3(int $id): array
    {
        $arguments = [$id];
        $sql = "SELECT id, website, forename, surname, email, email_master, joined, picture, role, status, account_id, photo_limit, agegroup, plan, pagelimit,sorttype,  publik, termsok
                  FROM member
                  WHERE account_id = :id;"; // SQL to get all members
        return $this->db->runSql($sql, $arguments)->fetchAll(); // Return all members
    }

    // Get individual member data using their emailDESC
    public function getIdByEmail(string $email)
    {
        $sql = "SELECT id
                  FROM member
                 WHERE email = :email;"; // SQL query to get member id
        return $this->db->runSql($sql, [$email])->fetchColumn(); // Run SQL and return member id
    }

    //get last id
    public function getLastId(): array
    {
        $sql = "SELECT id
                  FROM member
                  ORDER BY id DESC;"; // SQL to get all members
        return $this->db->runSql($sql)->fetch(); // Return all members
    }

    // Login: returns member data if authenticated, false if not
    public function login(string $email, string $password, int $website)
    {
        $arguments['email'] = $email;
        $arguments['website'] = $website;
        $sql = "SELECT id, website, forename, surname, joined, email, email_master, password, picture, role, isUberAdmin, status, account_id, photo_limit, agegroup, plan, pagelimit,sorttype, publik, termsok
                  FROM member
                 WHERE email = :email
                 AND website = :website;"; // SQL to collect member data

        $member = $this->db->runSql($sql, $arguments)->fetch(); // Run SQL
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

        $sql = "SELECT id, website, forename, surname, joined, email, email_master, password,
           picture, role, isUberAdmin, status, account_id, photo_limit, agegroup, plan,
                   pagelimit, sorttype, publik, termsok, email_verified, email_verified_at,
                   failed_login_attempts, last_failed_login, lock_until
              FROM member
             WHERE email = :email
             LIMIT 1;";

        $member = $this->db->runSql($sql, $arguments)->fetch();

        if (!$member) {
            return false;
        }

        $authenticated = password_verify($password, $member['password']);
        if (!$authenticated) {
            return false;
        }

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
        return $this->db->runSql($sql)->fetchColumn(); // Run SQL and return count
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
            'status' => (string) ($member['status'] ?? 'active'),
            'photo_limit' => (int) ($member['photo_limit'] ?? 0),
            'agegroup' => (int) ($member['agegroup'] ?? 0),
            'plan' => (int) ($member['plan'] ?? 1),
            'pagelimit' => (int) ($member['pagelimit'] ?? 50),
            'sorttype' => (int) ($member['sorttype'] ?? 1),
            'publik' => (int) ($member['publik'] ?? 1),
            'termsok' => (int) ($member['termsok'] ?? 0),
            'policy_version' => isset($member['policy_version'])
                ? (string) $member['policy_version']
                : null,
            'policy_accepted_at' => $member['policy_accepted_at'] ?? null,
            'email_verified' => (int) ($member['email_verified'] ?? 0),
            'email_verified_at' => $member['email_verified_at'] ?? null,
        ];

        if ($params['website'] <= 0 || $params['email'] === '' || $params['password'] === '') {
            return false;
        }
        if (
            $params['termsok'] !== 1 ||
            empty($params['policy_version']) ||
            empty($params['policy_accepted_at'])
        ) {
            throw new \InvalidArgumentException(
                'Policy acceptance, version, and acceptance date are required.',
            );
        }
        $params['password'] = password_hash($params['password'], PASSWORD_DEFAULT);

        error_log('[MEMBER create] ENTERED create()');

        try {
            $started = $this->db->beginTransaction();
            if (!$started) {
                throw new \RuntimeException('Failed to start transaction');
            }

            $sql = "
            INSERT INTO member
                (
                    website,
                    forename,
                    surname,
                    email,
                    email_master,
                    password,
                    role,
                    status,
                    photo_limit,
                    agegroup,
                    plan,
                    pagelimit,
                    sorttype,
                    publik,
                    termsok,
                    policy_version,
                    policy_accepted_at,
                    email_verified,
                    email_verified_at
                )
            VALUES
                (
                    :website,
                    :forename,
                    :surname,
                    :email,
                    :email_master,
                    :password,
                    :role,
                    :status,
                    :photo_limit,
                    :agegroup,
                    :plan,
                    :pagelimit,
                    :sorttype,
                    :publik,
                    :termsok,
                    :policy_version,
                    :policy_accepted_at,
                    :email_verified,
                    :email_verified_at
                );
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
            'public_ride_leaderboard' => (int) ($member['public_ride_leaderboard'] ?? 0),
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
               plan        = :plan,
               public_ride_leaderboard = :public_ride_leaderboard
         WHERE id = :id
         LIMIT 1;
    ";

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->runSql($sql, $params);

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
        $this->db->runSql($sql, ['id' => $id, 'picture' => $imageName]);

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
        $this->db->runSql($sql, ['id' => $member['id']]); // Run SQL
        return true; // Return true
    }

    // Update member password
    public function passwordUpdate(int $id, string $password): bool
    {
        $hash = password_hash($password, PASSWORD_DEFAULT); // Hash the password
        $sql = 'UPDATE member
                   SET password = :password
                 WHERE id = :id'; // SQL to update password
        $this->db->runSql($sql, ['id' => $id, 'password' => $hash]); // Run SQL
        return true; // Return true
    }

    // Get age groups
    public function getAgegroups(): array
    {
        $sql = "SELECT age, agegroup
                  FROM agegroup;"; // SQL to get all agegroup records
        return $this->db->runSql($sql)->fetchAll(); // Return all agegroup records
    }

    // Get Plans
    public function getPlans(): array
    {
        $sql = "SELECT id, active, name, description, photolimit, monthly, annual
                  FROM plan
                  WHERE active = 1;"; // SQL to get all plan records
        return $this->db->runSql($sql)->fetchAll(); // Return all plan records
    }

    // Get photolimit
    public function getphotolimit(int $id)
    {
        $sql = "SELECT photolimit, active
                  FROM plan
                  WHERE id = :id
                  AND active = 1;"; // SQL to get photolimit
        return $this->db->runSql($sql, [$id])->fetch(); // plan photolimit   /
    }

    // Get Follow
    public function getFollows(): array
    {
        $sql = "SELECT to_id, to_account_id, from_id, from_account_id, status, request_date, response_date
                  FROM follow;"; // SQL to get all plan records
        return $this->db->runSql($sql)->fetchAll(); // Return all plan records
    }
    public function updateAccountIdForWebsite(int $memberId, int $websiteId, int $accountId): bool
    {
        $sql = 'UPDATE member
            SET account_id = :aid
            WHERE id = :id AND website = :wid
            LIMIT 1';

        $params = [
            'aid' => $accountId,
            'id' => $memberId,
            'wid' => $websiteId,
        ];

        $stmt = $this->db->runSql($sql, $params);

        // If your wrapper returns false/null on failure, fail closed
        if (!$stmt instanceof \PDOStatement) {
            return false;
        }

        return $stmt->rowCount() === 1;
    }

    public function createEmailVerification(int $userId, string $tokenHash, string $expiresAt): bool
    {
        if ($userId <= 0 || $tokenHash === '' || $expiresAt === '') {
            return false;
        }

        $sql = '
        INSERT INTO email_verifications (user_id, token_hash, expires_at)
        VALUES (:user_id, :token_hash, :expires_at)
    ';

        $stmt = $this->db->runSql($sql, [
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);

        return $stmt !== false;
    }

    public function markEmailVerified(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $sql = '
        UPDATE member
        SET email_verified = 1,
            email_verified_at = NOW()
        WHERE id = :id
        LIMIT 1
    ';

        $stmt = $this->db->runSql($sql, ['id' => $userId]);

        return $stmt !== false;
    }

    public function getEmailVerificationByTokenHash(string $tokenHash): array
    {
        $sql = '
        SELECT ev.id AS verification_id,
               ev.user_id,
               ev.token_hash,
               ev.expires_at,
               ev.used_at,
               m.email_verified
        FROM email_verifications ev
        INNER JOIN member m ON m.id = ev.user_id
        WHERE ev.token_hash = :token_hash
        LIMIT 1
    ';

        $stmt = $this->db->runSql($sql, ['token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        return $row ?: [];
    }

    public function markEmailVerificationUsed(int $verificationId): bool
    {
        if ($verificationId <= 0) {
            return false;
        }

        $sql = '
        UPDATE email_verifications
        SET used_at = NOW()
        WHERE id = :id
        LIMIT 1
    ';

        $stmt = $this->db->runSql($sql, ['id' => $verificationId]);

        return $stmt !== false;
    }
    public function getByEmailMaster(string $emailMaster): array
    {
        $sql = '
        SELECT id, website, forename, surname, email, email_master, status, email_verified
        FROM member
        WHERE email_master = :email_master
        LIMIT 1
    ';

        $stmt = $this->db->runSql($sql, ['email_master' => trim($emailMaster)]);
        $row = $stmt->fetch();

        return $row ?: [];
    }
    public function expireUnusedEmailVerifications(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $sql = '
        UPDATE email_verifications
        SET used_at = NOW()
        WHERE user_id = :user_id
          AND used_at IS NULL
    ';

        $stmt = $this->db->runSql($sql, ['user_id' => $userId]);

        return $stmt !== false;
    }

    public function expireUnusedPasswordResets(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $sql = '
        UPDATE password_resets
        SET used_at = NOW()
        WHERE user_id = :user_id
          AND used_at IS NULL
    ';

        $stmt = $this->db->runSql($sql, ['user_id' => $userId]);

        return $stmt !== false;
    }

    public function createPasswordReset(int $userId, string $tokenHash, string $expiresAt): bool
    {
        if ($userId <= 0 || $tokenHash === '' || $expiresAt === '') {
            return false;
        }

        $sql = '
        INSERT INTO password_resets (user_id, token_hash, expires_at)
        VALUES (:user_id, :token_hash, :expires_at)
    ';

        $stmt = $this->db->runSql($sql, [
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);

        return $stmt !== false;
    }
    public function getPasswordResetByTokenHash(string $tokenHash): array
    {
        $sql = '
        SELECT pr.id AS reset_id,
               pr.user_id,
               pr.expires_at,
               pr.used_at,
               m.email,
               m.email_master,
               m.forename,
               m.status
        FROM password_resets pr
        INNER JOIN member m ON m.id = pr.user_id
        WHERE pr.token_hash = :token_hash
        LIMIT 1
    ';

        $stmt = $this->db->runSql($sql, ['token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        return $row ?: [];
    }

    public function markPasswordResetUsed(int $resetId): bool
    {
        if ($resetId <= 0) {
            return false;
        }

        $sql = '
        UPDATE password_resets
        SET used_at = NOW()
        WHERE id = :id
        LIMIT 1
    ';

        $stmt = $this->db->runSql($sql, ['id' => $resetId]);

        return $stmt !== false;
    }

    public function updatePasswordById(int $userId, string $newPassword): bool
    {
        if ($userId <= 0 || $newPassword === '') {
            return false;
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $sql = '
        UPDATE member
        SET password = :password
        WHERE id = :id
        LIMIT 1
    ';

        $stmt = $this->db->runSql($sql, [
            'password' => $passwordHash,
            'id' => $userId,
        ]);

        return $stmt !== false;
    }

    public function isLoginLocked(string $email): bool
    {
        $sql = '
        SELECT lock_until
        FROM member
        WHERE email = :email
        LIMIT 1
    ';

        $stmt = $this->db->runSql($sql, ['email' => trim($email)]);
        $row = $stmt->fetch();

        if (!$row || empty($row['lock_until'])) {
            return false;
        }

        $lockUntil = new \DateTimeImmutable((string) $row['lock_until']);
        $now = new \DateTimeImmutable('now');

        return $lockUntil > $now;
    }

    public function recordFailedLogin(
        string $email,
        int $maxAttempts = 5,
        int $lockMinutes = 10,
    ): bool {
        $email = trim($email);
        if ($email === '') {
            return false;
        }

        $sql = '
        SELECT id, failed_login_attempts
        FROM member
        WHERE email = :email
        LIMIT 1
    ';

        $stmt = $this->db->runSql($sql, ['email' => $email]);
        $member = $stmt->fetch();

        if (!$member) {
            return false;
        }

        $userId = (int) ($member['id'] ?? 0);
        $attempts = (int) ($member['failed_login_attempts'] ?? 0) + 1;

        $lockUntil = null;
        if ($attempts >= $maxAttempts) {
            $lockDate = new \DateTimeImmutable('now +' . $lockMinutes . ' minutes');
            $lockUntil = $lockDate->format('Y-m-d H:i:s');
        }

        $sql = '
        UPDATE member
        SET failed_login_attempts = :failed_login_attempts,
            last_failed_login = NOW(),
            lock_until = :lock_until
        WHERE id = :id
        LIMIT 1
    ';

        $stmt = $this->db->runSql($sql, [
            'failed_login_attempts' => $attempts,
            'lock_until' => $lockUntil,
            'id' => $userId,
        ]);

        return $stmt !== false;
    }

    public function clearFailedLogin(string $email): bool
    {
        $email = trim($email);
        if ($email === '') {
            return false;
        }

        $sql = '
        UPDATE member
        SET failed_login_attempts = 0,
            last_failed_login = NULL,
            lock_until = NULL
        WHERE email = :email
        LIMIT 1
    ';

        $stmt = $this->db->runSql($sql, ['email' => $email]);

        return $stmt !== false;
    }

    public function getAllByEmailMaster(string $emailMaster): array
    {
        $sql = '
        SELECT id, website, email, email_master
        FROM member
        WHERE email_master = :email_master
        ORDER BY website ASC, id ASC
    ';

        $stmt = $this->db->runSql($sql, ['email_master' => trim($emailMaster)]);
        return $stmt->fetchAll() ?: [];
    }

    public function emailLoginExistsOutsideEmailMaster(string $email, string $emailMaster): bool
    {
        $sql = '
        SELECT id
        FROM member
        WHERE email = :email
          AND email_master <> :email_master
        LIMIT 1
    ';

        $stmt = $this->db->runSql($sql, [
            'email' => trim($email),
            'email_master' => trim($emailMaster),
        ]);

        $row = $stmt->fetch();
        return !empty($row);
    }

    public function expireUnusedEmailChangeRequestsByEmailMaster(string $emailMaster): bool
    {
        if ($emailMaster === '') {
            return false;
        }

        $sql = '
        UPDATE email_change_requests
        SET used_at = NOW()
        WHERE old_email_master = :old_email_master
          AND used_at IS NULL
    ';

        $stmt = $this->db->runSql($sql, ['old_email_master' => trim($emailMaster)]);
        return $stmt !== false;
    }

    public function updateAllEmailsByEmailMaster(
        string $oldEmailMaster,
        string $newEmailMaster,
    ): bool {
        $oldEmailMaster = trim(strtolower($oldEmailMaster));
        $newEmailMaster = trim(strtolower($newEmailMaster));

        if ($oldEmailMaster === '' || $newEmailMaster === '') {
            return false;
        }

        $members = $this->getAllByEmailMaster($oldEmailMaster);
        if (!$members) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            foreach ($members as $member) {
                $userId = (int) ($member['id'] ?? 0);
                $websiteId = (int) ($member['website'] ?? 0);

                if ($userId <= 0 || $websiteId <= 0) {
                    throw new \RuntimeException('Invalid member row in email change update.');
                }

                $newLoginEmail = $websiteId > 1 ? $newEmailMaster . $websiteId : $newEmailMaster;

                $sql = '
                UPDATE member
                SET email = :email,
                    email_master = :email_master,
                    email_verified = 1,
                    email_verified_at = NOW()
                WHERE id = :id
                LIMIT 1
            ';

                $this->db->runSql($sql, [
                    'email' => $newLoginEmail,
                    'email_master' => $newEmailMaster,
                    'id' => $userId,
                ]);
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
    public function emailLoginExists(string $email): bool
    {
        $sql = '
        SELECT id
        FROM member
        WHERE email = :email
        LIMIT 1
    ';

        $stmt = $this->db->runSql($sql, ['email' => trim($email)]);
        $row = $stmt->fetch();

        return !empty($row);
    }
    public function expireUnusedEmailChangeRequests(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $sql = '
        UPDATE email_change_requests
        SET used_at = NOW()
        WHERE user_id = :user_id
          AND used_at IS NULL
    ';

        $stmt = $this->db->runSql($sql, ['user_id' => $userId]);

        return $stmt !== false;
    }
    public function createEmailChangeRequest(
        int $userId,
        string $oldEmailMaster,
        string $newEmailMaster,
        string $tokenHash,
        string $expiresAt,
    ): bool {
        if (
            $userId <= 0 ||
            $oldEmailMaster === '' ||
            $newEmailMaster === '' ||
            $tokenHash === '' ||
            $expiresAt === ''
        ) {
            return false;
        }

        $sql = '
        INSERT INTO email_change_requests (
            user_id,
            old_email_master,
            new_email_master,
            token_hash,
            expires_at
        )
        VALUES (
            :user_id,
            :old_email_master,
            :new_email_master,
            :token_hash,
            :expires_at
        )
    ';

        $stmt = $this->db->runSql($sql, [
            'user_id' => $userId,
            'old_email_master' => trim($oldEmailMaster),
            'new_email_master' => trim($newEmailMaster),
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);

        return $stmt !== false;
    }

    public function getEmailChangeRequestByTokenHash(string $tokenHash): array
    {
        $sql = '
        SELECT ecr.id AS request_id,
               ecr.user_id,
               ecr.old_email_master,
               ecr.new_email_master,
               ecr.expires_at,
               ecr.used_at,
               m.website,
               m.forename
        FROM email_change_requests ecr
        INNER JOIN member m ON m.id = ecr.user_id
        WHERE ecr.token_hash = :token_hash
        LIMIT 1
    ';

        $stmt = $this->db->runSql($sql, ['token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        return $row ?: [];
    }
    public function markEmailChangeRequestUsed(int $requestId): bool
    {
        if ($requestId <= 0) {
            return false;
        }

        $sql = '
        UPDATE email_change_requests
        SET used_at = NOW()
        WHERE id = :id
        LIMIT 1
    ';

        $stmt = $this->db->runSql($sql, ['id' => $requestId]);
        return $stmt !== false;
    }
    public function updateMemberEmailById(
        int $userId,
        string $newEmailLogin,
        string $newEmail,
    ): bool {
        if ($userId <= 0 || $newEmailLogin === '' || $newEmail === '') {
            return false;
        }

        $sql = '
        UPDATE member
        SET email = :email,
            email_master = :email_master,
            email_verified = 1,
            email_verified_at = NOW()
        WHERE id = :id
        LIMIT 1
    ';

        $stmt = $this->db->runSql($sql, [
            'email' => trim($newEmailLogin),
            'email_master' => trim($newEmail),
            'id' => $userId,
        ]);

        return $stmt !== false;
    }

    public function getByEmailMasterAndWebsite(string $emailMaster, int $websiteId): array
    {
        if ($emailMaster === '' || $websiteId <= 0) {
            return [];
        }

        $sql = '
        SELECT id, website, forename, surname, email, email_master, status, email_verified
        FROM member
        WHERE email_master = :email_master
          AND website = :website
        LIMIT 1
    ';

        $stmt = $this->db->runSql($sql, [
            'email_master' => trim(strtolower($emailMaster)),
            'website' => $websiteId,
        ]);

        $row = $stmt->fetch();
        return $row ?: [];
    }
    public function markEmailMasterVerified(string $emailMaster): bool
    {
        $sql = "UPDATE member
            SET email_verified = 1,
                email_verified_at = NOW()
            WHERE email_master = :email_master";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'email_master' => strtolower(trim($emailMaster)),
        ]);
    }

    public function getMessageRecipients(): array
    {
        $sql = "SELECT id, forename, surname, website
              FROM member
             WHERE forename <> 'Uber'
             and role<>'Guest'
             and member.id<>3
             and member.status = 'active'
             ORDER BY surname, forename";

        return $this->db->runSql($sql)->fetchAll();
    }

    /**
     * Determine whether a member belongs to a website.
     */
    public function isMemberOfWebsite(int $memberId, int $websiteId): bool
    {
        $sql = "SELECT COUNT(*)
              FROM member
             WHERE id = :member_id
               AND website = :website_id";

        $statement = $this->db->runSql($sql, [
            'member_id' => $memberId,
            'website_id' => $websiteId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }
}
