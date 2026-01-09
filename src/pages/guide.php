<?php
declare(strict_types=1);

// src/pages/guide.php
// Handles both:
//   /guide
//   /guide/<slug>

$slug = $parts[1] ?? ''; // assuming $parts is path segments after routing
$slug = trim((string) $slug, "/ \t\n\r\0\x0B");

// -------------------------
// Shared: build Guide index data
// -------------------------
$guideIndex = [
    'intro' =>
        '<strong>Welcome.</strong> This Guide explains how FocusOnLife works and how to use it confidently.',
    'groups' => [
        [
            'title' => 'Foundations',
            'summary' => 'Start here to understand the big picture.',
            'items' => [
                [
                    'slug' => 'getting-started',
                    'title' => 'Getting Started',
                    'note' => 'Your first 10 minutes',
                ],
                [
                    'slug' => 'websites-menus-stories',
                    'title' => 'Websites, Menus, and Stories',
                    'note' => 'The core TFOL concept',
                ],
                [
                    'slug' => 'basic-navigation',
                    'title' => 'Basic Navigation',
                    'note' => 'How to move around TFOL',
                ],
            ],
        ],
        [
            'title' => 'Account & Roles',
            'summary' => 'Identity, login, and what different roles can do.',
            'items' => [
                [
                    'slug' => 'registration-login',
                    'title' => 'Registration and Login',
                    'note' => 'Create an account and sign in',
                ],
                [
                    'slug' => 'user-roles',
                    'title' => 'User Roles & Member Options',
                    'note' => 'Guest, Member, Admin, UberAdmin',
                ],
                [
                    'slug' => 'security-deep-links',
                    'title' => 'Security: Roles, Permissions, and Safe Sharing',
                    'note' => 'Deep links without risk',
                ],
            ],
        ],
        [
            'title' => 'Creating Content',
            'summary' => 'How to create menus and publish stories.',
            'items' => [
                [
                    'slug' => 'creating-menus-stories',
                    'title' => 'Creating Menus and Stories',
                    'note' => 'From idea to published',
                ],
            ],
        ],
        [
            'title' => 'Community',
            'summary' => 'How following and notifications work (and why).',
            'items' => [
                [
                    'slug' => 'following-notifications',
                    'title' => 'Following and Notifications',
                    'note' => 'Stay connected',
                ],
                [
                    'slug' => 'community-vs-individual',
                    'title' => 'Community vs Individual Websites',
                    'note' => 'Ownership and sharing',
                ],
            ],
        ],
    ],
];

// If no slug, render the Guide landing page
if ($slug === '' || $slug === 'index') {
    $data['guide_index'] = $guideIndex;
    echo $twig->render('guide/index.html', $data);
    exit();
}

// -------------------------
// Topic lookup (Phase 1: simple hard-coded map)
// Later: move to DB or config files
// -------------------------
$topics = [
    'getting-started' => [
        'title' => 'Getting Started',
        'subtitle' => 'Your first 10 minutes on FocusOnLife',
        'meta' => ['read_time' => '4 min', 'updated' => '2026-01-06', 'level' => 'Beginner'],

        'what_this_is' =>
            '<p>This topic helps you get oriented—what to click first, and what “menus” and “stories” mean in TFOL.</p>',
        'why_it_matters' =>
            '<p>TFOL is designed so each person (and each “website”) can evolve over time. A quick mental model helps everything else make sense.</p>',

        'sections' => [
            [
                'id' => 'overview',
                'title' => 'Big picture',
                'summary' => 'TFOL is organized as Websites → Menus → Stories.',
                'content' =>
                    '<p>Think of a <strong>Website</strong> as a container, a <strong>Menu</strong> as a category, and a <strong>Story</strong> as an individual piece of content.</p>',
                'callouts' => [
                    [
                        'kind' => 'tip',
                        'title' => 'Quick start',
                        'body' =>
                            '<p>You can browse as a guest. Register only when you’re ready to follow, comment (if enabled), or create content.</p>',
                    ],
                ],
            ],
            [
                'id' => 'first-steps',
                'title' => 'Do this first',
                'steps' => [
                    [
                        'title' => 'Browse a menu',
                        'text' => '<p>Click any menu item in the top navigation bar.</p>',
                    ],
                    [
                        'title' => 'Open a story',
                        'text' => '<p>Click a story card from the grid to read it.</p>',
                    ],
                    [
                        'title' => 'Use Search',
                        'text' =>
                            '<p>Use the Search icon in the nav bar to find content quickly.</p>',
                    ],
                ],
            ],
        ],

        'related' => [
            [
                'slug' => 'websites-menus-stories',
                'title' => 'Websites, Menus, and Stories',
                'note' => 'Core concept',
            ],
            [
                'slug' => 'basic-navigation',
                'title' => 'Basic Navigation',
                'note' => 'Where things live',
            ],
        ],

        'prev' => null,
        'next' => ['slug' => 'websites-menus-stories', 'title' => 'Websites, Menus, and Stories'],
    ],

    // Placeholder topics (so links from index work right away)
    'websites-menus-stories' => [
        'title' => 'Websites, Menus, and Stories',
        'subtitle' => 'The core TFOL model',
        'meta' => ['read_time' => '5 min', 'updated' => '2026-01-06', 'level' => 'Beginner'],
        'what_this_is' =>
            '<p>A plain-English explanation of the three building blocks of TFOL.</p>',
        'why_it_matters' =>
            '<p>Once you get this, navigation, roles, and publishing all become intuitive.</p>',
        'sections' => [
            [
                'id' => 'coming-soon',
                'title' => 'Draft in progress',
                'content' => '<p>This Guide topic is coming next.</p>',
            ],
        ],
        'related' => [['slug' => 'getting-started', 'title' => 'Getting Started']],
        'prev' => ['slug' => 'getting-started', 'title' => 'Getting Started'],
        'next' => ['slug' => 'basic-navigation', 'title' => 'Basic Navigation'],
    ],

    'basic-navigation' => [
        'title' => 'Basic Navigation',
        'subtitle' => 'How to move around TFOL',
        'meta' => ['read_time' => '4 min', 'updated' => '2026-01-06', 'level' => 'Beginner'],
        'what_this_is' => '<p>Navigation basics: menu bar, grids, story pages, and search.</p>',
        'why_it_matters' =>
            '<p>When navigation feels effortless, the content becomes the focus.</p>',
        'sections' => [
            [
                'id' => 'coming-soon',
                'title' => 'Draft in progress',
                'content' => '<p>This Guide topic is coming soon.</p>',
            ],
        ],
        'related' => [['slug' => 'getting-started', 'title' => 'Getting Started']],
        'prev' => ['slug' => 'websites-menus-stories', 'title' => 'Websites, Menus, and Stories'],
        'next' => ['slug' => 'registration-login', 'title' => 'Registration and Login'],
    ],

    'registration-login' => [
        'title' => 'Registration and Login',
        'subtitle' => 'Create an account and sign in',
        'meta' => ['read_time' => '4 min', 'updated' => '2026-01-06', 'level' => 'Beginner'],
        'what_this_is' =>
            '<p>How registration and login work, and what changes once you’re signed in.</p>',
        'why_it_matters' =>
            '<p>Registration unlocks your identity: saved preferences, following, and member features.</p>',
        'sections' => [
            [
                'id' => 'coming-soon',
                'title' => 'Draft in progress',
                'content' => '<p>This Guide topic is coming soon.</p>',
            ],
        ],
        'related' => [['slug' => 'user-roles', 'title' => 'User Roles & Member Options']],
        'prev' => ['slug' => 'basic-navigation', 'title' => 'Basic Navigation'],
        'next' => ['slug' => 'user-roles', 'title' => 'User Roles & Member Options'],
    ],

    'user-roles' => [
        'title' => 'User Roles & Member Options',
        'subtitle' => 'What different roles can do',
        'meta' => ['read_time' => '6 min', 'updated' => '2026-01-06', 'level' => 'Beginner'],
        'what_this_is' =>
            '<p>Roles define what actions you can take (and what you can manage).</p>',
        'why_it_matters' => '<p>Roles keep TFOL flexible while staying safe and organized.</p>',
        'sections' => [
            [
                'id' => 'coming-soon',
                'title' => 'Draft in progress',
                'content' => '<p>This Guide topic is coming soon.</p>',
            ],
        ],
        'related' => [['slug' => 'registration-login', 'title' => 'Registration and Login']],
        'prev' => ['slug' => 'registration-login', 'title' => 'Registration and Login'],
        'next' => ['slug' => 'creating-menus-stories', 'title' => 'Creating Menus and Stories'],
    ],

    'security-deep-links' => [
        'title' => 'Security: Roles, Permissions, and Safe Sharing (Deep Links)',
        'subtitle' => 'Deep links are great—when permissions are enforced server-side',
        'meta' => [
            'read_time' => '6 min',
            'updated' => '2026-01-07',
            'level' => 'Beginner → Intermediate',
        ],

        'what_this_is' => '
        <p>This topic explains how FocusOnLife keeps admin capabilities safe even when users share direct links (deep links) to stories, menus, and pages.</p>
        <p>It also explains what admins can safely share—and what happens when someone opens an admin link without permission.</p>
    ',

        'why_it_matters' => '
        <p>Deep links make TFOL feel modern: you can bookmark a story, share a website, or jump straight to a Guide topic.</p>
        <p>The only risk is confusing “a link you can open” with “a power you can use.” TFOL avoids that by enforcing roles and permissions on the server for every sensitive action.</p>
    ',

        'sections' => [
            [
                'id' => 'deep-links-good',
                'title' => 'Why TFOL allows deep links',
                'content' => '
                <ul>
                    <li><strong>Sharing:</strong> Send someone straight to a story or guide topic.</li>
                    <li><strong>Bookmarks:</strong> Your browser bookmarks work naturally.</li>
                    <li><strong>Support:</strong> You can return to (or share) the exact page where something happened.</li>
                    <li><strong>Discovery:</strong> Public content can be found and referenced more easily.</li>
                </ul>
            ',
                'callouts' => [
                    [
                        'kind' => 'note',
                        'title' => 'Important idea',
                        'body' =>
                            '<p>Deep links improve usability. They are not a security feature and should not be treated like one.</p>',
                    ],
                ],
            ],

            [
                'id' => 'permissions-vs-visibility',
                'title' => 'Visibility vs permission',
                'content' => '
                <p><strong>Visibility</strong> answers: “Can someone view this page?”</p>
                <p><strong>Permission</strong> answers: “Can someone perform this action?” (create, edit, delete, manage)</p>
                <p>In TFOL, a person may be able to <em>reach</em> an admin URL, but they cannot <em>use</em> admin powers unless the server confirms their role and access.</p>
            ',
            ],

            [
                'id' => 'what-happens',
                'title' => 'What happens if someone opens an admin link?',
                'steps' => [
                    [
                        'title' => 'If they are not logged in',
                        'text' =>
                            '<p>They should be sent to Login (or shown a friendly “Please log in” message). After login, they return only if they have the right permissions.</p>',
                    ],
                    [
                        'title' => 'If they are logged in but not an admin',
                        'text' =>
                            '<p>They should see an “Access denied” page (403) or a “Not found” page (404). Either is acceptable; the key is they cannot perform the action.</p>',
                    ],
                    [
                        'title' => 'If they are an admin but not for that website',
                        'text' =>
                            '<p>They should be blocked the same way. Admin power should be scoped to the correct website/ownership model.</p>',
                    ],
                ],
                'callouts' => [
                    [
                        'kind' => 'tip',
                        'title' => 'UX tip',
                        'body' =>
                            '<p>For non-admins, showing a friendly “You don’t have permission” page with a Login link is often better than a blank error.</p>',
                    ],
                ],
            ],

            [
                'id' => 'required-controls',
                'title' => 'The required security controls',
                'content' => '
                <p>To keep deep links safe, TFOL should enforce these checks on the server:</p>
                <ul>
                    <li><strong>Authentication:</strong> the user must be logged in.</li>
                    <li><strong>Role check:</strong> the user must have the required role (Member/Admin/UberAdmin).</li>
                    <li><strong>Ownership/scope:</strong> the user must have rights for the target website/content.</li>
                    <li><strong>Method safety:</strong> write actions must use POST (not GET).</li>
                    <li><strong>CSRF protection:</strong> every create/edit/delete must include a valid CSRF token.</li>
                </ul>
            ',
                'callouts' => [
                    [
                        'kind' => 'warn',
                        'title' => 'Rule',
                        'body' =>
                            '<p>A GET request should never delete or change data. If it changes data, it must be POST + CSRF.</p>',
                    ],
                ],
            ],

            [
                'id' => 'safe-sharing',
                'title' => 'Safe sharing guidelines for admins',
                'content' => '
                <ul>
                    <li>It’s safe to share links to <strong>public content</strong> (stories, menus, guide topics).</li>
                    <li>Be cautious sharing links to <strong>admin pages</strong>; they won’t grant power, but they can confuse users.</li>
                    <li>If you share an admin link with a collaborator, make sure they have the right role and website access.</li>
                </ul>
            ',
            ],
        ],

        'related' => [
            [
                'slug' => 'user-roles',
                'title' => 'User Roles & Member Options',
                'note' => 'Who can do what',
            ],
            [
                'slug' => 'registration-login',
                'title' => 'Registration and Login',
                'note' => 'Identity and sessions',
            ],
            [
                'slug' => 'creating-menus-stories',
                'title' => 'Creating Menus and Stories',
                'note' => 'Where permissions matter most',
            ],
        ],

        'prev' => ['slug' => 'user-roles', 'title' => 'User Roles & Member Options'],
        'next' => ['slug' => 'creating-menus-stories', 'title' => 'Creating Menus and Stories'],
    ],

    'creating-menus-stories' => [
        'title' => 'Creating Menus and Stories',
        'subtitle' => 'From idea to published',
        'meta' => ['read_time' => '7 min', 'updated' => '2026-01-06', 'level' => 'Intermediate'],
        'what_this_is' => '<p>How to create menus and write stories in TFOL.</p>',
        'why_it_matters' => '<p>This is where TFOL becomes your personal publishing platform.</p>',
        'sections' => [
            [
                'id' => 'coming-soon',
                'title' => 'Draft in progress',
                'content' => '<p>This Guide topic is coming soon.</p>',
            ],
        ],
        'related' => [['slug' => 'user-roles', 'title' => 'User Roles & Member Options']],
        'prev' => ['slug' => 'user-roles', 'title' => 'User Roles & Member Options'],
        'next' => ['slug' => 'following-notifications', 'title' => 'Following and Notifications'],
    ],

    'following-notifications' => [
        'title' => 'Following and Notifications',
        'subtitle' => 'Stay connected to new content',
        'meta' => ['read_time' => '5 min', 'updated' => '2026-01-06', 'level' => 'Intermediate'],
        'what_this_is' =>
            '<p>Following lets you track people or content streams; notifications keep you informed.</p>',
        'why_it_matters' =>
            '<p>It’s how TFOL becomes a living community instead of a static site.</p>',
        'sections' => [
            [
                'id' => 'coming-soon',
                'title' => 'Draft in progress',
                'content' => '<p>This Guide topic is coming soon.</p>',
            ],
        ],
        'related' => [
            ['slug' => 'community-vs-individual', 'title' => 'Community vs Individual Websites'],
        ],
        'prev' => ['slug' => 'creating-menus-stories', 'title' => 'Creating Menus and Stories'],
        'next' => [
            'slug' => 'community-vs-individual',
            'title' => 'Community vs Individual Websites',
        ],
    ],

    'community-vs-individual' => [
        'title' => 'Community vs Individual Websites',
        'subtitle' => 'Ownership, sharing, and boundaries',
        'meta' => ['read_time' => '6 min', 'updated' => '2026-01-06', 'level' => 'Intermediate'],
        'what_this_is' =>
            '<p>How TFOL supports both personal “websites” and shared community content.</p>',
        'why_it_matters' =>
            '<p>TFOL is intentionally built to support both independence and connection.</p>',
        'sections' => [
            [
                'id' => 'coming-soon',
                'title' => 'Draft in progress',
                'content' => '<p>This Guide topic is coming soon.</p>',
            ],
        ],
        'related' => [
            ['slug' => 'websites-menus-stories', 'title' => 'Websites, Menus, and Stories'],
        ],
        'prev' => ['slug' => 'following-notifications', 'title' => 'Following and Notifications'],
        'next' => null,
    ],
];

// Topic not found → show index (or you can render a 404 template)
if (!isset($topics[$slug])) {
    $data['guide_index'] = $guideIndex;
    echo $twig->render('guide/index.html', $data);
    exit();
}

// Render topic
$data['guide'] = $topics[$slug];
echo $twig->render('guide/topic.html', $data);
exit();
