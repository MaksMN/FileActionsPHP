<?php
define('S_IRUSR', 00400); // Чтение для владельца.
define('S_IWUSR', 00200); // Запись для владельца.
define('S_IXUSR', 00100); // Исполнение для владельца.

define('S_IRGRP', 00040); // Чтение для группы.
define('S_IWGRP', 00020); // Запись для группы.
define('S_IXGRP', 00010); // Исполнение для группы.

define('S_IROTH', 00004); // Чтение для остальных.
define('S_IWOTH', 00002); // Запись для остальных.
define('S_IXOTH', 00001); // Исполнение для остальных.

abstract class File
{
    protected string $fpath;
    protected $perms = 0600;
    protected $fd = false;
}
