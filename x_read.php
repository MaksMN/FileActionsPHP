<?php
define('DEBUG_MODE', 1);
define('APP_ROOT_DIR', __DIR__);
define('DIRECT_ACCESS_KEY', 1);

$file_path = __DIR__ . '/test/18_09_2024__14_50_59__9ICIbZYhEf.txt';
require_once __DIR__ . '/App/autoload.php';
try {
    $simple_file = new File\SimpleFile($file_path, "c+", 0644);
} catch (\Throwable $th) {
    echo $th->getMessage();
}

echo "x_read start\n";
echo "x_read: " . $simple_file->readLock();
