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
    protected $lock_flags = LOCK_UN;
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

    /**
     * Open file. Reopen if file exists. Unlock if locked.
     */
    public function open(string $mode = 'c+', $perms = null): bool
    {
        $perms = $perms ?? $this->perms;

        if ($this->opened)
            $this->close();

        $this->create();

        $fd = fopen($this->fpath, $mode);
        if ($fd === false) {
            $this->opened = false;
            $this->opened_mode = '';
            $this->add_error("File::open(): ");
            $this->fd = $fd;
            return false;
        } else {
            $this->fd = $fd;
            $this->opened = true;
            $this->opened_mode = $mode;
            $this->perms = $perms;
            return true;
        }
    }

    /**
     * Close file. Unlock if locked.
     */
    public function close(): void
    {
        if ($this->locked)
            $this->unlock();
        if ($this->opened) {
            if ($this->fd)
                fclose($this->fd);
        }
        $this->opened = false;
        $this->opened_mode = '';
        $this->fd = false;
    }

    public function read(int $start = 0, int $length = 0, $lock_flags = 0): mixed
    {
        $reopen = !$this->is_readable();
        if ($reopen) {
            $this->open('r');
        }
        if (($lock_flags & (LOCK_EX | LOCK_SH | LOCK_UN)) > 0) {
            if ($this->locked)
                $this->unlock();
            $this->lock($lock_flags);
        }
    }

    public function size(): int
    {
        if (!$this->create()) {
            return 0;
        }
        return filesize($this->fpath);
    }

    /**
     * Lock file
     */
    public function lock(int $lock_flags): void
    {
        if ($this->locked)
            $this->unlock();
        if (!$this->opened)
            return;
        $l = flock($this->fd, $lock_flags);
        if (!$l) {
            $this->add_error("Fle::lock()");
            $this->locked = false;
            $this->lock_flags = LOCK_UN;
        } else {
            $this->locked = true;
            $this->lock_flags = $lock_flags;
        }
    }

    /**
     * Unlock file
     */
    public function unlock(): void
    {
        if (!$this->locked && !$this->opened)
            return;
        $l = flock($this->fd, LOCK_UN);
        if (!$l) {
            $this->add_error("Fle::lock()");
        } else {
            $this->locked = false;
            $this->lock_flags = LOCK_UN;
        }
    }

    /**
     *  Acquire an exclusive lock (writer).
     */
    public function lock_ex(): void
    {
        if ($this->is_writable())
            $this->lock(LOCK_EX);
    }

    /**
     * Acquire a shared lock (reader).
     */
    public function lock_sh(): void
    {
        if ($this->is_readable())
            $this->lock(LOCK_SH);
    }

    /**
     * File is locked
     */
    public function is_locked(): bool
    {
        return $this->locked;
    }

    /**
     * File opened for read
     */
    public function is_readable(): bool
    {
        return $this->opened && in_array($this->opened_mode, ['r', 'r+', 'w+', 'a+', 'x+', 'c+']);
    }

    /**
     * File opened for write
     */
    public function is_writable(): bool
    {
        return $this->opened && $this->opened_mode != 'r';
    }

    /**
     * File open for read/write
     */
    public function is_rw(): bool
    {
        return $this->is_readable() && $this->is_writable();
    }

    /**
     * Get file permissions
     */
    public function perms()
    {
        if (!$this->exists()) {
            return 0000;
        }
        $perms = fileperms($this->fpath);
        if ($perms === false) {
            return 0000;
        } else {
            return $perms;
        }
    }

    /**
     * Change file permissions
     */
    public function chmod(int $mode = 0600)
    {
        if (!chmod($this->fpath, $mode))
            $this->add_error("File::chmod: ");
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