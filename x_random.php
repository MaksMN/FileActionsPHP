<?php
define('DEBUG_MODE', 1);
define('APP_ROOT_DIR', __DIR__);
define('DIRECT_ACCESS_KEY', 1);

$file_path = __DIR__ . '/test';
require_once __DIR__ . '/App/autoload.php';
try {
    $file = new File\RandomFile($file_path, "c+", 'txt', 0644);
} catch (\Throwable $th) {
    echo $th->getMessage();
}

echo "x_write start\n";

$file->writeLock("Locked");
$file->lockEX();

sleep(30);
echo "x_write end\n";
