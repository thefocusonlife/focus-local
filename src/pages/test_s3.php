<?php
include '../src/s3.php';

try {
    $result = $s3->listBuckets();
    $buckets = $result->get('Buckets');
    echo "Success! You have access to the following buckets:\n";
    foreach ($buckets as $bucket) {
        echo $bucket['Name'] . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
