<?php

declare(strict_types=1); // Use strict types
use PhpBook\Validate\Validate; // Use Validate class

require_once APP_ROOT . '/src/security/guard.php';
require_once APP_ROOT . '/src/security/ownership.php';
require_once APP_ROOT . '/src/security/redirects.php';
guardMember();

$member = [];
$temp = $_FILES['image']['tmp_name'] ?? ''; // Temporary image
$destination = ''; // Where to save file
$saved = null; // Did story save
$photocount = 0;
$used = 0;
$storyorder = 0;
$families = null;
$image = [];
$website = [];
$landscape = 1;
$blog = 2;
$allow_comment = 1;

// Initialize variables needed for the HTML page
// Always initialize/parse id BEFORE building default story
$isMobileRoute = false;
$id = 0;

// Detect mobile work form
$isMobileRoute = false;
$id = 0;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', trim($path, '/'))));

$isMobileRoute =
    (isset($_GET['mobile']) && $_GET['mobile'] === '1') || in_array('mobile', $segments, true);

if (!$isMobileRoute) {
    $lastSegment = end($segments);

    if ($lastSegment !== false && ctype_digit((string) $lastSegment)) {
        $id = (int) $lastSegment;
    }
}
$story = [
    'id' => $id,
    'website' => '',
    'title' => '',
    'summary' => '.',
    'content' => '.',
    'member_id' => 0,
    'family_id' => 0,
    'menu_id' => 0,
    'image_id' => null,
    'published' => 0,
    'image_file' => '',
    'image_alt' => '',
    'storyorder' => 0,
    'landscape' => '1',
    'blog' => 2,
    'allow_comment' => '0',
    'keyword' => 'none',
]; // Story data

$errors = [
    'warning' => '',
    'website' => '',
    'title' => '',
    'summary' => '',
    'content' => '',
    'author' => '',
    'menu' => '',
    'image_file' => '',
    'image_alt' => '',
];

// Resolve logged-in member id (prefer session service; fallback to $_SESSION)
$sessionMemberId = 0;

if (isset($cms) && method_exists($cms, 'getSession') && isset($cms->getSession()->id)) {
    $sessionMemberId = (int) $cms->getSession()->id;
}

if ($sessionMemberId <= 0) {
    $sessionMemberId = (int) ($_SESSION['id'] ?? 0);
}

// If this page requires login (admin/work), fail fast
// Treat 0 (no session) and 2 (guest account) as not logged in
if ($sessionMemberId <= 0 || $sessionMemberId === 2) {
    redirect('login', ['failure' => 'Please log in to add or edit stories.']);
    exit();
}

$websiteId = (int) ($_SESSION['website'] ?? 0);
if ($websiteId <= 0) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

$website = $cms->getWebsite()->getById($websiteId);
if (!$website) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

if ($id > 0) {
    $story = $cms->getStory()->get($id, false);

    if (!$story || !is_array($story)) {
        include APP_ROOT . '/src/pages/page-not-found.php';
        exit();
    }

    // ---- Permission guard (author OR scoped-admin OR uber/future) ----
    $role = (string) ($_SESSION['role'] ?? 'guest');
    $isUber = $role === 'uber'; // future
    $isAdmin = $role === 'admin' || $isUber;

    $member = $cms->getMember()->get($sessionMemberId);
    if (!$member || !is_array($member)) {
        include APP_ROOT . '/src/pages/page-not-found.php';
        exit();
    }

    $storyOwnerId = (int) ($story['member_id'] ?? 0);
    $storyWebsiteId = (int) ($story['website'] ?? ($story['website_id'] ?? 0));
    $memberWebsiteId = (int) ($member['website'] ?? 0);

    $canEdit = $storyOwnerId === $sessionMemberId;

    if (!$canEdit) {
        include APP_ROOT . '/src/pages/page-not-found.php';
        exit();
    }
} else {
    // Create mode: ensure author is set
    $story['member_id'] = $sessionMemberId;
}

$storyWebsiteId = (int) ($story['website'] ?? 0);

if ($storyWebsiteId > 0 && $storyWebsiteId !== (int) $websiteId) {
    // Switch tenant context to the story's website
    $_SESSION['website'] = $storyWebsiteId;

    // Persist cookie (matches website_context.php signature)
    require_once APP_ROOT . '/src/tenancy/website_context.php';
    setWebsiteCookie('tfol_tid', $storyWebsiteId);

    // Update locals
    $websiteId = $storyWebsiteId;
    $website = $cms->getWebsite()->getById($websiteId) ?: [];
}

//user's id from session
//if ($id === 0) {
if ($sessionMemberId === 0) {
    //logged in
    redirect('login/');
    //not found
}

if ($story['id'] == false) {
    $authors = $cms->getMember()->get($_SESSION['id']);
} else {
    $authors = $cms->getMember()->get($story['member_id']);
    // Get all members
}
//$menus       = $cms->getMenu()->getAll2($_SESSION['website'],$_SESSION['account_id']);                    // Get menus
$menus = $cms->getMenu()->getAll2($authors['website'], $authors['account_id']); // Get menus
$member = $cms->getMember()->get((int) $sessionMemberId);
if (!$member || !is_array($member)) {
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}
if ($story['id'] == false) {
    $families = $cms->getMember()->get($member['account_id']);
    $photocount = 0;
} else {
    $families = $cms->getMember()->get($story['family_id']);
    $photocount = intval($cms->getStory()->used($story['member_id']));
}

$story['website'] = (int) ($_SESSION['website'] ?? 0);

if (empty($_SESSION) or $_SESSION['id'] == 2) {
    if ($website['id'] > 1 and $website['non_members'] == 0) {
        redirect('index/99999', [
            'failure' => 'You must register as a member to access GET FOCUSED websites.
        Click the "Register" link on top of this page to see pricing.',
        ]);
        exit();
    }
}
/* normalize inconsistent naming conventions */
$accountId =
    (int) ($story['account_id'] ??
        ($story['family_id'] ?? ($authors['account_id'] ?? ($member['account_id'] ?? 0))));

$story['account_id'] = $accountId;

$story['website'] = (int) ($_SESSION['website'] ?? 0);
$story['menu_id'] =
    (int) ($story['menu_id'] ??
        ($_GET['menu_id'] ?? ($_POST['menu_id'] ?? ($_SESSION['menu_id'] ?? 0))));

$isNewStory = empty($story['id']);

if ($isNewStory) {
    $accountId = (int) ($authors['account_id'] ?? 0);

    $story['account_id'] = $accountId;
    $story['family_id'] = $accountId;

    $story['menu_id'] = (int) ($_GET['menu_id'] ?? ($_POST['menu_id'] ?? ($story['menu_id'] ?? 0)));

    if ($story['menu_id'] < 1 && $accountId > 0) {
        $story['menu_id'] = $cms->getMenu()->getDefaultMenuIdForAccount($accountId);
    }

    if ($story['menu_id'] > 0) {
        $storyorder = $cms->getStory()->getStoryorder($story['menu_id']);
        $story['storyorder'] = (int) ($storyorder['storyorder'] ?? 10);
    } else {
        $story['storyorder'] = 10;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form submitted

    // ---- WRITE BOUNDARY (must be first meaningful enforcement) ----

    $role = (string) ($_SESSION['role'] ?? 'guest');
    $isUber = $role === 'uber';

    // Enforce ownership for edits (owner or uber). For create: force author.
    $storyId = !empty($story['id']) ? (int) $story['id'] : null;
    if ($storyId > 0) {
        assertStoryOwnership($cms, $storyId, (int) $sessionMemberId, $isUber);
    } else {
        // Create mode: force author to session member to prevent spoofing
        $story['member_id'] = (int) $sessionMemberId;
    }

    // Only handle save when the Save button was used
    if (isset($_POST['update'])) {
        // ---- CSRF validation ----
        $posted = (string) ($_POST['csrf'] ?? '');

        if ($posted === '' || !verify_csrf($posted)) {
            $errors['warning'] =
                'Security check failed (CSRF). Please reload the page and try again.';
            // Do NOT process the save
        } else {
            // -----------------------------
            // A) Build $story from POST
            // -----------------------------
            $story['id'] =
                isset($_POST['id']) && $_POST['id'] !== ''
                    ? (int) $_POST['id']
                    : $story['id'] ?? null;

            $story['image_id'] =
                isset($_POST['image_id']) && $_POST['image_id'] !== ''
                    ? (int) $_POST['image_id']
                    : $story['image_id'] ?? null;

            $story['title'] = $_POST['title'] ?? '';
            $story['summary'] = $_POST['summary'] ?? '';
            $story['content'] = $_POST['content'] ?? '.';

            // Force author: never trust POST member_id
            if (!empty($story['id'])) {
                // editing: keep the owner from the loaded record
                $story['member_id'] = (int) ($story['member_id'] ?? 0);
            } else {
                // creating: force to logged-in user
                $story['member_id'] = (int) $sessionMemberId;
            }

            $story['family_id'] = isset($_POST['family_id'])
                ? (int) $_POST['family_id']
                : $story['family_id'] ?? 0;

            $story['menu_id'] = isset($_POST['menu_id'])
                ? (int) $_POST['menu_id']
                : $story['menu_id'] ?? 0;

            $story['published'] = !empty($_POST['published']) ? 1 : 0;
            $story['seo_title'] = create_seo_name($story['title']);

            $story['storyorder'] = isset($_POST['storyorder'])
                ? (int) $_POST['storyorder']
                : $story['storyorder'] ?? 0;

            // Checkboxes / toggles
            $story['landscape'] = !empty($_POST['landscape']) ? 1 : 0;
            $story['allow_comment'] = !empty($_POST['allow_comment']) ? 1 : 0;

            $story['keyword'] = $_POST['keyword'] ?? '';
            $story['website'] = (int) ($_SESSION['website'] ?? 0);

            $story['blog'] = isset($_POST['blog']) ? (int) $_POST['blog'] : $story['blog'] ?? 0;

            $memberId = (int) ($story['member_id'] ?? 0);
            $authors = $cms->getMember()->get($memberId);

            // -----------------------------
            // B) Validate story fields
            // -----------------------------
            $errors['title'] = Validate::isText($story['title'], 1, 80)
                ? ''
                : 'Title should be 1 - 80 characters.';

            $errors['summary'] = Validate::isText($story['summary'], 1, 254)
                ? ''
                : 'Summary should be 0 - 254 characters.';

            $errors['content'] = Validate::isText($story['content'], 1, 100000)
                ? ''
                : 'Content should be 0 - 100,000 characters.';

            $errors['menu'] = Validate::isMenuId($story['menu_id'], $menus)
                ? ''
                : 'Not a valid menu';

            $errors['keyword'] = Validate::isText($story['keyword'], 1, 80)
                ? ''
                : 'Keyword should be 1 - 80 characters.';

            $invalid = implode($errors);
            $failure = '';
            // -----------------------------
            // C) Save if valid
            // -----------------------------

            $storyId = !empty($story['id']) ? (int) $story['id'] : null;
            $arguments = $story;

            // Duplicate title check
            if (empty($errors['title'])) {
                if ($storyId === null) {
                    if ($cms->getStory()->titleExists($story['title'])) {
                        $errors['title'] = 'A story with this title already exists.';
                    }
                } else {
                    if ($cms->getStory()->titleExists($story['title'], $storyId)) {
                        $errors['title'] = 'Another story with this title already exists.';
                    }
                }
            }

            // Final validation gate
            $invalid = implode($errors);

            if ($invalid) {
                $errors['warning'] = 'Please correct form errors';
            } else {
                // ------------------------------------------------------------
                // Image upload orchestration (Story Create + Update)
                // ------------------------------------------------------------
                $hasUpload =
                    isset($_FILES['image']) &&
                    ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK &&
                    is_uploaded_file($_FILES['image']['tmp_name'] ?? '');

                $uploadFailed = false;

                if ($hasUpload) {
                    // Normalize image_id
                    $arguments['image_id'] = !empty($arguments['image_id'])
                        ? (int) $arguments['image_id']
                        : null;

                    // Determine alt text
                    $alt = trim((string) ($arguments['image_alt'] ?? ''));
                    if ($alt === '.' || $alt === '') {
                        $name = (string) ($_FILES['image']['name'] ?? '');
                        $alt = $name ? pathinfo($name, PATHINFO_FILENAME) : '';
                    }
                    if ($alt === '.') {
                        $alt = '';
                    }

                    // Ensure we have an image row
                    $imageId = (int) ($arguments['image_id'] ?? 0);

                    if ($imageId <= 0) {
                        $sql = 'INSERT INTO image (file, alt) VALUES (:file, :alt);';
                        $cms->getDb()->runSql($sql, [
                            'file' => '',
                            'alt' => $alt,
                        ]);

                        $imageId = (int) $cms->getDb()->lastInsertId();
                        $arguments['image_id'] = $imageId;
                    } else {
                        $sql = 'UPDATE image SET alt = :alt WHERE id = :id;';
                        $cms->getDb()->runSql($sql, [
                            'alt' => $alt,
                            'id' => $imageId,
                        ]);
                    }

                    try {
                        // Save uploaded image via ImageService
                        $result = $cms
                            ->getImageService()
                            ->saveUploadedStoryImage(
                                $_FILES['image'],
                                $imageId,
                                $arguments['title'] ?? '',
                            );

                        // Update image row with final filename + alt
                        $sql = 'UPDATE image SET file = :file, alt = :alt WHERE id = :id;';
                        $cms->getDb()->runSql($sql, [
                            'file' => $result['filename'],
                            'alt' => $alt,
                            'id' => $imageId,
                        ]);

                        // Propagate derived values back into story args
                        $arguments['landscape'] = (int) ($result['landscape'] ?? 0);
                    } catch (\Throwable $e) {
                        $msg = $e->getMessage();

                        if (str_contains($msg, 'Unsupported image type')) {
                            $errors['warning'] =
                                'Invalid image type. Please upload a JPG or PNG image.';
                        } elseif (str_contains($msg, 'File too large')) {
                            $errors['warning'] = $msg;
                        } else {
                            $errors['warning'] =
                                'Sorry. A problem occurred while uploading your image.';
                        }

                        $uploadFailed = true;
                    }
                }

                // Save story only if image upload did not fail
                if (!$uploadFailed) {
                    $storyorder = $cms->getStory()->getstoryorder((int) $story['menu_id']);

                    error_log('after getStoryorder, raw value=' . print_r($storyorder, true));
                    if ($storyId !== null) {
                        $arguments['id'] = $storyId;
                        $saved = $cms->getStory()->update($arguments);
                    } else {
                        unset($arguments['id']);
                        $saved = $cms->getStory()->create($arguments);
                    }

                    // Optional alt text update after save
                    if ($saved) {
                        $imageId = (int) ($arguments['image_id'] ?? 0);
                        $alt = trim((string) ($_POST['image_alt'] ?? ''));

                        if ($imageId > 0 && $alt !== '') {
                            $cms->getStory()->altUpdate($imageId, $alt);
                        }

                        redirect('admin/stories/', ['success' => 'Story saved']);
                    } else {
                        $errors['warning'] = 'Story could not be saved';
                    }
                }
            }
        }
    }
}

$member = $cms->getMember()->get($sessionMemberId);

if (!$member || !is_array($member)) {
    error_log('[work.php] Member lookup failed for id=' . $sessionMemberId);
    include APP_ROOT . '/src/pages/page-not-found.php';
    exit();
}

$data['story'] = $story; // Story data for template
$data['menus'] = $menus; // Menu data for template
$data['authors'] = $authors; // Author data data for template//
$data['member'] = $member;
$data['errors'] = $errors; // Error data for template
$data['storyorder'] = $storyorder;
$data['photocount'] = $photocount;
$data['family'] = $families;
// Website for template: prefer story.website (or story.website_id), fallback to session/default
$websiteId = 0;

// If your story has website id stored under 'website' (common in your arrays)
if (isset($story['website']) && ctype_digit((string) $story['website'])) {
    $websiteId = (int) $story['website'];
}

// Or if it’s stored as website_id
if ($websiteId <= 0 && isset($story['website_id'])) {
    $websiteId = (int) $story['website_id'];
}

// Fallback to session website_id if your app uses it
if ($websiteId <= 0) {
    $websiteId = (int) ($_SESSION['website_id'] ?? 0);
}

// Final fallback: choose a sane default (adjust if your default is not 1)
if ($websiteId <= 0) {
    $websiteId = 1;
}

$data['website'] = $cms->getWebsite()->getById($websiteId) ?: [];
$data['csrf_token'] = generate_csrf_token();

// Image panel UI state (default minimized)
$data['image_panel_minimized'] = (bool) ($_SESSION['ui']['image_panel_minimized'] ?? true);

$debugPanel = null;

if (defined('DEV') && DEV) {
    // Safe: only compute resolved driver if the class exists
    $resolved = '(n/a)';
    if (class_exists('ImageCapabilities') && method_exists('ImageCapabilities', 'resolvedDriver')) {
        try {
            $resolved = ImageCapabilities::resolvedDriver();
        } catch (\Throwable $e) {
            $resolved = 'ERROR: ' . $e->getMessage();
        }
    }

    $debugPanel = [
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
        'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? '',
        'cwd' => getcwd(),
        'uploads' => defined('UPLOADS') ? UPLOADS : '(undef)',

        'IMAGE_DRIVER' => defined('IMAGE_DRIVER') ? IMAGE_DRIVER : '(undef)',
        'resolved_driver' => $resolved,
        'imagick_loaded' => extension_loaded('imagick') ? 'yes' : 'no',
        'gd_loaded' => extension_loaded('gd') ? 'yes' : 'no',

        'MAX_DIM' => defined('IMAGE_MAX_DIM') ? IMAGE_MAX_DIM : '(undef)',
        'THUMB' =>
            defined('IMAGE_THUMB_W') && defined('IMAGE_THUMB_H')
                ? IMAGE_THUMB_W . 'x' . IMAGE_THUMB_H
                : '(undef)',
        'QUALITY' => defined('IMAGE_QUALITY') ? IMAGE_QUALITY : '(undef)',
        'JPEG_QUALITY' => defined('IMAGE_JPEG_QUALITY') ? IMAGE_JPEG_QUALITY : '(undef)',
        'FORMAT' => defined('IMAGE_OUTPUT_FORMAT') ? IMAGE_OUTPUT_FORMAT : '(undef)',
    ];
}

if (defined('DEV') && DEV) {
    $data['debug_panel'] = $debugPanel;
}

$template = $isMobileRoute ? 'work-mobile.html' : 'work.html';
$template = $isMobileRoute ? 'work-mobile.html' : 'work.html';

echo $twig->render($template, $data);
