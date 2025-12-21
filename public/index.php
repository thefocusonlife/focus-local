<?php
if (!isset($_SESSION)) {
    require_once '../src/bootstrap.php';
}
          // get path for website and menus                             // Setup file
//$id = 2;
$parts[] = null;
$path  = mb_strtolower($_SERVER['REQUEST_URI']);             // Get path in lowercase
$path  = substr($path, strlen(DOC_ROOT));
$parts = explode('/', $path);                                // Split into array at /

if ($parts[0] != 'admin') {                                  // If an admin page
    $page = $parts[0] ?: 'index';                            // Page name (or use index)
    if (!isset($parts[1]) and $parts[0] === "") {
        $page="home";
        $php_page = APP_ROOT . '/src/pages/' . $page . '.php';

        include $php_page;
        exit;
    }

    $id   = $parts[1] ?? 1;
                              // Get ID (or use null)
} else {                                                     // If not an admin page
    $page = 'admin/' . ($parts[1] ?? '');
    if (isset($parts[2])  ) {                   // Page name
    $id   = intval($parts[2]) ?? 1;             // Get ID
    }

}
//if (isset($_SESSION['id']) and $_SESSION['id']==1) {
//    $cms->getSession()->create(0,$id);
//}
//var_dump_pre($path);
//var_dump_pre($parts);
//var_dump_pre($id);
//echo "public/index.php -24";
if (isset($id)) {
$id = filter_var($id, FILTER_VALIDATE_INT);                  // Validate ID
}
    $php_page = APP_ROOT . '/src/pages/' . $page . '.php';       // Path to PHP page

if (!file_exists($php_page)) {                               // If page not in array
    $php_page = APP_ROOT . '/src/pages/page-not-found.php';  // Include page not found
}
 //var_dump_pre($php_page);
include $php_page;
