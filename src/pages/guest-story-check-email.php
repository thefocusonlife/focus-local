<?php
declare(strict_types=1);
//temp log
error_log(
    'SWS TRACE ' .
        basename(__FILE__) .
        ' ' .
        print_r(
            [
                'GET' => $_GET,
                'POST' => $_POST,
                'session_id' => session_id(),
                'id' => $_SESSION['id'] ?? null,
                'website' => $_SESSION['website'] ?? null,
                'websiteid' => $_SESSION['websiteid'] ?? null,
                'menu_website' => $_SESSION['menu_website'] ?? null,
                'guest_story' => $_SESSION['guest_story'] ?? null,
                'guest_story_draft' => !empty($_SESSION['guest_story_draft']),
            ],
            true,
        ),
);
$websiteId = 1;

$data = [
    'website_id' => 1,
    'website' => 1,
    'email' => $_SESSION['guest_story_email'] ?? '',
];
echo $twig->render('guest-story-check-email.html', $data);
