<?php
//is_admin($session->role);                                          // Check if admin
//if (!$id) {                                                        // If no id
//    redirect('page-not-found/');                                   // Page not found
//}

$data['plan'] = 'plan';

echo $twig->render('plans.html', $data);
