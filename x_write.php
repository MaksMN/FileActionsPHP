<?php
define('DEBUG_MODE', 1);
define('APP_ROOT_DIR', __DIR__);
define('DIRECT_ACCESS_KEY', 1);

$file_path = __DIR__ . '/test/test.txt';
require_once __DIR__ . '/App/autoload.php';
try {
    $simple_file = new File\SimpleFile($file_path, "c+", 0644);
} catch (\Throwable $th) {
    echo $th->getMessage();
}

echo "x_write start\n";

$simple_file->writeLock("Locked");
$simple_file->lockEX();

sleep(30);
echo "x_write end\n";
