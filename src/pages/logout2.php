<?php
declare(strict_types = 1);  
$key = $_SESSION['website'];                             // Use strict types
$cms->getSession()->delete();                            // Call method to end session
$cms->getSession()->create(0,$key);                                          // Redirect to home page
redirect('index/' . $key);  