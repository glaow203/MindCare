
<?php
header('Content-Type: text/plain; charset=utf-8');

echo "PHP version: " . PHP_VERSION . "\n";

if (function_exists('curl_init')) {
    echo "PHP cURL: ENABLED\n";
} else {
    echo "PHP cURL: DISABLED\n";
    echo "กรุณาเปิดใช้งาน cURL ใน php.ini\n";
}
