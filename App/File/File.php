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
    protected bool $opened = false;
    protected string $opened_mode = '';
    protected bool $locked = false;
    protected int $lock_flags = 0;
    protected int $errno = 0;
    protected string $error_message = '';

    public function exists(): bool
    {
        return file_exists($this->fpath) && is_file($this->fpath);
    }

    /**
     * Creates a file if it does not exist. Does nothing and returns true if the file exists.
     */
    private function create(): bool
    {
        if (!$this->exists()) {
            $fd = fopen($this->fpath, 'c+');
            if ($fd == false) {
                $this->add_error("File::create(): ");
                return false;
            }
            fclose($fd);
        }
        return true;
    }
    public function open(string $mode = 'c+', $perms = null): void
    {
        $perms = $perms ?? $this->perms;

        if ($this->opened)
            $this->close();

        $this->create();

        $fd = fopen($this->fpath, $mode);
        if ($fd === false) {
            $this->opened = false;
            $this->add_error("File::open(): ");
        } else {
            $this->opened = true;
            $this->perms = $perms;
        }
    }
    public function close(): void
    {
    }
    public function lock(int $lock_flags): void
    {
    }

    public function unlock(): void
    {
    }

    public function perms()
    {
        if (!$this->exists()) {
            return 0000;
        }
        return fileperms($this->fpath);
    }

    /* error methods */
    public function error_number(): int
    {
        return $this->errno;
    }
    public function error_message(): string
    {
        return $this->error_message;
    }
    public function error_clear(): void
    {
        $this->errno = 0;
        $this->error_message = '';
    }
    protected function add_error(string $prefix = ''): void
    {
        $error = error_get_last();
        $this->error_message .= '[' . $error['type'] . '] ' . $error['message'] . "\n";
        $this->errno = $error['type'];
    }
    public function is_error(): bool
    {
        return $this->errno != 0;
    }
}

/*
Особенности!
При создании экземпляра класса файл создается или выбрасываются исключения.
Далее приложения должны работать таким образом чтобы файл не был удален до разрушения класса.
Так же надо позаботиться о правах доступа. 
Класс работает с учетом того, что файл доступен для чтения и записи.
Класс работает с учетом того что файл существует.
Некоторые методы будут проверять существование файла и пересоздавать его.
Некоторые методы в случае отсутствия файла будут возвращать нули, пустые строки итп.

В случае каких либо противоречивых условий, проблем доступа, невозможности создания файла
класс не будет прерывать работу и выбрасывать исключений. Методы будут выдавать нули, пустые строки, false итп.
*/