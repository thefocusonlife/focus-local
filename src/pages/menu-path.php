<?php
declare(strict_types = 1); 
$id = 2;  

  $path  = mb_strtolower($_SERVER['REQUEST_URI']);             // Get path in lowercase
  $path  = substr($path, strlen(DOC_ROOT));                    // Remove up to DOC_ROOT 
  if($path == ""){
      $path = "index/". 1;
  }
                   
  $parts = explode('/', $path); 
                              // Split into array at /
  if (!empty($parts)) {
   
    if ($parts[0] != 'admin') {                                  // If an admin page
      $page = $parts[0] ?: 'index';                            // Page name (or use index)
      if (!empty($parts[1])) {
        $id   = (intval($parts[1])) ?? 1;
        } else {
            $id = 1;
        }                                   // Get ID (or use null)
  } else {                                                     // If not an admin page
      $page = 'admin/' . ($parts[1] ?? '');                    // Page name
      $id   = intval($parts[2]) ?? null;                               // Get ID
  }
} else {
    $page ='index';
    $id = 1;
}


 if (empty($_SESSION['id']) ) {

     $website = $cms->getWebsite()->getById(1);
 } else {
    
     $website = $cms->getWebsite()->getById(intval($_SESSION['website']));
     }
  
      
  