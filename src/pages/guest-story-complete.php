<?php
declare(strict_types=1);

if (empty($_SESSION['id'])) {
    header('Location: ' . DOC_ROOT . 'login?guest_story=1');
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
$memberId = (int) $_SESSION['id'];
$familyId = (int) ($_SESSION['account_id'] ?? $memberId);
$contributionsMenuId = 572; // TODO: replace with real Contributions menu id
$accountId = (int) ($_SESSION['account_id'] ?? $memberId);

// Try first member menu
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

if (!empty($menu['id'])) {
    $menuId = (int) $menu['id'];
} else {
    // Create starter menu for new member
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

$stmt = $cms->getDb()->runSql(
    "
    SELECT COALESCE(MAX(storyorder), 0) + 10 AS next_storyorder
    FROM story
    WHERE menu_id = :menu_id
    ",
    [
        'menu_id' => $menuId,
    ],
);

$row = $stmt->fetch();
$storyorder = (int) ($row['next_storyorder'] ?? 10);

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

$storyId = (int) $cms->getDb()->lastInsertId();

unset(
    $_SESSION['guest_story_draft'],
    $_SESSION['guest_story_email'],
    $_SESSION['guest_story_register_email'],
    $_SESSION['guest_story_login_notice'],
);

header('Location: ' . DOC_ROOT . 'work/' . $storyId);
exit();
