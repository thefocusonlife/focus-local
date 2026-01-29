<?php
error_log('[PAGE-NOT-FOUND] FILE=' . __FILE__);
error_log('[PAGE-NOT-FOUND] CWD=' . getcwd());
error_log('[PAGE-NOT-FOUND] uri=' . ($_SERVER['REQUEST_URI'] ?? 'NA'));

error_log('[PAGE-NOT-FOUND] AFTER header logs');

$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12);
error_log('[PAGE-NOT-FOUND] bt_count=' . count($bt));

foreach ($bt as $i => $f) {
    error_log("[PAGE-NOT-FOUND] bt#$i file=" . ($f['file'] ?? 'NA') . ' line=' . ($f['line'] ?? 0));
}

http_response_code(404);

// Keep website context if it exists; otherwise default to 1.
// IMPORTANT: do not override a valid session website here.
$websiteId = (int) ($_SESSION['website'] ?? 1);
if ($websiteId <= 0) {
    $websiteId = 1;
}

$data = [];
$data['session'] = $_SESSION; // optional, if template uses it
$data['website_id'] = $websiteId; // lets template build a safe "Back to Home" link

// Do NOT pass navigation/menus on 404 — avoids messy header + avoids info leakage
echo $twig->render('page-not-found.html', $data);
