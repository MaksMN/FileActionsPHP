<?php

/*
 * Автозагрузка классов
 */

spl_autoload_register(
    function ($class) {
        $a = explode('\\', $class);
        $last = array_pop($a);
        $fn = $class . '/' . $last . '.php';
        $fn = __DIR__ . '/' . str_replace('\\', '/', $fn);

        if (defined('DEBUG_MODE'))
            echo '<b>autoload: ' . $class . '</b> file: ' . $fn . '<br>';

        if (file_exists($fn))
            require_once $fn;
    }
);
