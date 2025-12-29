<?php

declare(strict_types=1); // Use strict types
use PhpBook\Validate\Validate; // Use Validate class

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
$blog = 1;
$allow_comment = 1;

// Initialize variables needed for the HTML page

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
    'blog' => 1,
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
if (!empty($parts[1])) {
    $id = (int) $parts[1];
}
if ($id) {
    $story = $cms->getStory()->get($id, false); // Get story data
    if (!$story) {
        // If story is empty
        $cms->getSession()->id;

        include APP_ROOT . '/src/pages/page-not-found.php'; // Page not found
    }

    if ($story['member_id'] !== $cms->getSession()->id) {
        // If not author of story
        if ($_SESSION['id'] > 1) {
            include APP_ROOT . '/src/pages/page-not-found.php'; // Page not found
        }
    }
}
if ($id) {
    // If valid id
    $story = $cms->getStory()->get($id, false); // Get story data
    if (!$story) {
        // If story empty
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

if ($story['id'] == false) {
    $authors = $cms->getMember()->get($_SESSION['id']);
} else {
    $authors = $cms->getMember()->get($story['member_id']);
    // Get all members
}
//$menus       = $cms->getMenu()->getAll2($_SESSION['website'],$_SESSION['account_id']);                    // Get menus
$menus = $cms->getMenu()->getAll2($authors['website'], $authors['account_id']); // Get menus
$member = $cms->getMember()->get($id);
if ($story['id'] == false) {
    $families = $cms->getMember()->get($member['account_id']);
    $photocount = 0;
} else {
    $families = $cms->getMember()->get($story['family_id']);
    $photocount = intval($cms->getStory()->used($story['member_id']));
}

$website = $cms->getwebsite()->getById($_SESSION['website']) ?? 1;

if (empty($_SESSION) or $_SESSION['id'] == 2) {
    if ($website['id'] > 1 and $website['non_members'] == 0) {
        redirect('index/99999', [
            'failure' => 'You must register as a member to access GET FOCUSED websites.
        Click the "Register" link on top of this page to see pricing.',
        ]);
        exit();
    }
}

if ($story['storyorder'] < 1) {
    $storyorder = $cms->getStory()->getstoryorder(intval($authors['id'])); // get last storyorder
} else {
    if ($storyorder < 1) {
        $storyorder = intval($story['storyorder']);
    }
}
$website = $cms->getwebsite()->getById($_SESSION['website']) ?? 1;

if (empty($storyorder)) {
    $story['storyorder'] = 1;
} else {
    if ($story['storyorder'] < 1) {
        $story['storyorder'] = intval($storyorder['storyorder']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form submitted

    // Only handle save when the Save button was used
    if (isset($_POST['update'])) {
        // -----------------------------
        // A) Build $story from POST
        // -----------------------------
        $story['id'] =
            isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : $story['id'] ?? null;
        $story['image_id'] =
            isset($_POST['image_id']) && $_POST['image_id'] !== ''
                ? (int) $_POST['image_id']
                : $story['image_id'] ?? null;

        $story['title'] = $_POST['title'] ?? '';
        $story['summary'] = $_POST['summary'] ?? '';
        $story['content'] = $_POST['content'] ?? '';

        $story['member_id'] = isset($_POST['member_id'])
            ? (int) $_POST['member_id']
            : $story['member_id'] ?? 0;
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

        $memberId = $story['member_id'];
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
        $errors['menu'] = Validate::isMenuId($story['menu_id'], $menus) ? '' : 'Not a valid menu';
        $errors['keyword'] = Validate::isText($story['keyword'], 1, 80)
            ? ''
            : 'Keyword should be 1 - 80 characters.';

        $invalid = implode($errors);

        // -----------------------------
        // C) Save if valid
        // -----------------------------
        if ($invalid) {
            $errors['warning'] = 'Please correct form errors';
        } else {
            $arguments = $story; // ------------------------------------------------------------
            // Image upload orchestration (Story Create + Update)
            // ------------------------------------------------------------

            $hasUpload =
                isset($_FILES['image']) &&
                ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK &&
                is_uploaded_file($_FILES['image']['tmp_name'] ?? '');

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

                $alt = trim((string) ($arguments['image_alt'] ?? ''));
                if ($alt === '.' || $alt === '') {
                    $name = (string) ($_FILES['image']['name'] ?? '');
                    $alt = $name ? pathinfo($name, PATHINFO_FILENAME) : '';
                }
                if ($alt === '.') {
                    $alt = '';
                }

                if ($imageId <= 0) {
                    $sql = 'INSERT INTO image (file, alt) VALUES (:file, :alt);';
                    $cms->getDb()->runSQL($sql, [
                        'file' => '',
                        'alt' => $alt,
                    ]);

                    $imageId = (int) $cms->getDb()->lastInsertId();
                    $arguments['image_id'] = $imageId;
                } else {
                    $sql = 'UPDATE image SET alt = :alt WHERE id = :id;';
                    $cms->getDb()->runSQL($sql, [
                        'alt' => $alt,
                        'id' => $imageId,
                    ]);
                }
                $alt = trim((string) ($arguments['image_alt'] ?? ''));
                if ($alt === '.' || $alt === '') {
                    $name = (string) ($_FILES['image']['name'] ?? '');
                    $alt = $name ? pathinfo($name, PATHINFO_FILENAME) : '';
                }
                if ($alt === '.') {
                    $alt = '';
                }

                // Save uploaded image via ImageService (resize + naming + write to /public/uploads)
                $result = $cms
                    ->getImageService()
                    ->saveUploadedStoryImage($_FILES['image'], $imageId, $arguments['title'] ?? '');

                // Update image row with final filename + alt (bulletproof)
                $sql = 'UPDATE image SET file = :file, alt = :alt WHERE id = :id;';
                $cms->getDb()->runSQL($sql, [
                    'file' => $result['filename'],
                    'alt' => $alt,
                    'id' => $imageId,
                ]);

                // Propagate derived values back into story args
                $arguments['landscape'] = (int) ($result['landscape'] ?? 0);
            }

            if (!empty($arguments['id'])) {
                $saved = $cms->getStory()->update($arguments);
            } else {
                unset($arguments['id']);
                $saved = $cms->getStory()->create($arguments);
            }

            if ($saved) {
                $imageId = (int) ($arguments['image_id'] ?? 0);
                $alt = trim((string) ($_POST['image_alt'] ?? ''));

                if ($imageId > 0 && $alt !== '') {
                    $cms->getStory()->altUpdate($imageId, $alt);
                }
            }

            if ($saved) {
                redirect('admin/stories/', ['success' => 'Story saved']);
            } else {
                $errors['warning'] = 'Story title already in use';
            }
        }
    }
}

$data['story'] = $story; // Story data for template
$data['menus'] = $menus; // Menu data for template
$data['authors'] = $authors; // Author data data for template//
$data['member'] = $member;
$data['errors'] = $errors; // Error data for template
$data['storyorder'] = $storyorder;
$data['photocount'] = $photocount;
$data['family'] = $families;
$data['website'] = $cms->getWebsite()->getById(intval($authors['website']));

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

echo $twig->render('work.html', $data);
