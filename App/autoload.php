<?php

if (!defined('DIRECT_ACCESS_KEY')) exit;

spl_autoload_register(
    function ($class) {

        $class = str_replace('\\', '/', $class);

        // загрузка как есть 
        // Applications/class.php
        // Applications/Namespace/Class.php
        $fn = __DIR__ . '/' . $class . '.php';
        if (loadClass($fn))
            return;

        // загрузка с добавлением имени класса в конец пути
        // Applications/Class/Class.php
        $fn = __DIR__ . '/' . $class . '/' . basename($class) . '.php';
        if (loadClass($fn))
            return;

        error_log('Could not load file: ' . $fn . "\n");
        exit;
    }
);

function loadClass(string $file_name): bool
{
    if (defined('DEBUG_MODE'))
        echo 'autoload  file: ' . $file_name . "\n";
    if (file_exists($file_name)) {
        require_once $file_name;
        return true;
    }
    return false;
}
