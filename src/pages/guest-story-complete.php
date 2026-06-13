<?php
declare(strict_types=1);

require_once APP_ROOT . '/src/security/redirects.php';

if (empty($_SESSION['id'])) {
    $_SESSION['return_to'] = DOC_ROOT . 'guest-story-complete';
    header('Location: ' . DOC_ROOT . 'login');
    exit();
}

if (empty($_SESSION['guest_story_draft'])) {
    header('Location: ' . DOC_ROOT . 'stories-worth-saving');
    exit();
}

$draft = $_SESSION['guest_story_draft'];

$title = trim($draft['title'] ?? '');
$summary = trim($draft['summary'] ?? '');
$content = trim($draft['content'] ?? '');

if ($title === '' || $content === '') {
    header('Location: ' . DOC_ROOT . 'guest-story');
    exit();
}

$websiteId = 1;

$_SESSION['website'] = $websiteId;
$_SESSION['websiteid'] = $websiteId;
$_SESSION['menu_website'] = $websiteId;
$_SESSION['guest_story'] = 1;

$memberId = (int) $_SESSION['id'];
$accountId = (int) ($_SESSION['account_id'] ?? $memberId);
$familyId = $accountId;

// 1. Try account def ault menu first
$menuId = (int) $cms->getMenu()->getDefaultMenuIdForAccount($accountId);

// 2. If no default menu exists, use lowest-numbered menu
if ($menuId < 1) {
    $stmt = $cms->getDb()->runSql(
        "
        SELECT id
        FROM menu
        WHERE account_id = :account_id
          AND website = :website
        ORDER BY id ASC
        LIMIT 1
        ",
        [
            'account_id' => $accountId,
            'website' => $websiteId,
        ],
    );

    $menu = $stmt->fetch();

    $menuId = !empty($menu['id']) ? (int) $menu['id'] : 0;
}

// 3. If member has no menus, create starter menu
if ($menuId < 1) {
    $cms->getDb()->runSql(
        "
        INSERT INTO menu
        (
            website,
            name,
            description,
            navigation,
            account_id,
            seo_name,
            position,
            default_sorttype_id
        )
        VALUES
        (
            :website,
            'My Stories',
            'My saved stories',
            1,
            :account_id,
            'my-stories',
            10,
            6
        )
        ",
        [
            'website' => $websiteId,
            'account_id' => $accountId,
        ],
    );

    $menuId = (int) $cms->getDb()->lastInsertId();
}

$seoTitle = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
if ($seoTitle === '') {
    $seoTitle = 'guest-story-' . time();
}

$storyorderRow = $cms->getStory()->getStoryorder($menuId);
$storyorder = (int) ($storyorderRow['storyorder'] ?? 10);

$sql = "
    INSERT INTO story
    (
        website,
        title,
        summary,
        content,
        menu_id,
        member_id,
        family_id,
        image_id,
        published,
        seo_title,
        storyorder,
        landscape,
        blog,
        allow_comment,
        keyword
    )
    VALUES
    (
        :website,
        :title,
        :summary,
        :content,
        :menu_id,
        :member_id,
        :family_id,
        NULL,
        0,
        :seo_title,
        :storyorder,
        1,
        2,
        1,
        'none'
    )
";
try {
    $cms->getDb()->runSql($sql, [
        'website' => $websiteId,
        'title' => mb_substr($title, 0, 80),
        'summary' => mb_substr($summary !== '' ? $summary : '.', 0, 254),
        'content' => $content,
        'menu_id' => $menuId,
        'member_id' => $memberId,
        'family_id' => $familyId,
        'seo_title' => mb_substr($seoTitle, 0, 244),
        'storyorder' => $storyorder,
    ]);
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? 0) == 1062) {
        $data = [
            'website' => $cms->getWebsite()->getById(1),
            'doc_root' => DOC_ROOT,
            'error' =>
                'A story with that title already exists. Please return to the story form, change the title slightly, and save again.',
        ];
    }
    echo $twig->render('guest-story-complete-error.html', $data);
    exit();

    throw $e;
}
$storyId = (int) $cms->getDb()->lastInsertId();

unset(
    $_SESSION['guest_story_draft'],
    $_SESSION['guest_story_email'],
    $_SESSION['guest_story_register_email'],
    $_SESSION['guest_story_login_notice'],
);

redirect('mobile-story-image-edit', ['id' => $storyId]);
exit();
