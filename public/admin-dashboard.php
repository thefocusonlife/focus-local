<?php
declare(strict_types=1) ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Local Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 2rem;
        }

        h1 {
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: #666;
            margin-bottom: 2rem;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.5rem;
        }

        .card {
            background: #fff;
            border-radius: 10px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 6px 20px rgba(0,0,0,0.06);
        }

        .card h2 {
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
        }

        .card a {
            display: block;
            margin: 0.4rem 0;
            color: #0066cc;
            text-decoration: none;
            font-weight: 500;
        }

        .card a:hover {
            text-decoration: underline;
        }

        footer {
            margin-top: 3rem;
            font-size: 0.85rem;
            color: #888;
        }
    </style>
</head>
<body>

<h1>🛠 Local Admin Dashboard</h1>
<div class="subtitle">Focus-Local / XAMPP development shortcuts</div>

<div class="grid">

    <div class="card">
        <h2>Database</h2>
        <a href="http://localhost/phpmyadmin" target="_blank">phpMyAdmin</a>
    </div>

    <div class="card">
        <h2>TFOL Sites</h2>
        <a href="http://localhost/focus-local/public/index/1" target="_blank">Website 1 – Home</a>
        <a href="https://thefocusonlife.org/_stage/" target="_blank">Staging – Home</a>
        <a href="https://thefocusonlife.org/index/1" target="_blank">Production – Home</a>
    </div>

    <div class="card">
        <h2>Admin</h2>
        <a href="http://localhost/focus-local/public/login" target="_blank">Login</a>
        <a href="http://localhost/focus-local/public/admin/member" target="_blank">Member Admin</a>
        <a href="http://localhost/focus-local/public/admin/menu" target="_blank">Menu Admin</a>
    </div>

    <div class="card">
        <h2>Logs</h2>
        <a href="file:///opt/lampp/logs/php_error_log" target="_blank">PHP Error Log</a>
        <a href="file:///opt/lampp/logs/access_log" target="_blank">Apache Access Log</a>
    </div>

    <div class="card">
        <h2>System</h2>
        <a href="http://localhost" target="_blank">XAMPP Dashboard</a>
        <a href="http://localhost/phpinfo.php" target="_blank">phpinfo()</a>
    </div>

</div>

<footer>
    Local only • No auth • Bookmark this page
</footer>

</body>
</html>
