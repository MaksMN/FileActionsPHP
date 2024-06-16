<?php

use LDAP\Result;

define('S_IRUSR', 00400); // Чтение для владельца.
define('S_IWUSR', 00200); // Запись для владельца.
define('S_IXUSR', 00100); // Исполнение для владельца.

define('S_IRGRP', 00040); // Чтение для группы.
define('S_IWGRP', 00020); // Запись для группы.
define('S_IXGRP', 00010); // Исполнение для группы.

define('S_IROTH', 00004); // Чтение для остальных.
define('S_IWOTH', 00002); // Запись для остальных.
define('S_IXOTH', 00001); // Исполнение для остальных.
define('UNLOCK_FILE', 64);
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

    /**
     * Read file content.
     * If the lock flags are present, the file will be locked, the existing locks will be lifted.
     * If the LOCK_UN flag is enabled, the lock will be lifted after execution.
     */
    public function read(int $start = 0, int $length = 0, $lock_flags = 0): string
    {
        $reopen = !$this->is_readable();
        if ($reopen) {
            $this->open('r');
        }

        if (($lock_flags & LOCK_SH) > 0) {
            if ($this->locked)
                $this->unlock();
            $this->lock($lock_flags);
        }
        $file_size = $this->size();

        if ($start < 0)
            $start = 0;
        if ($start > $file_size)
            $start = $file_size - 1;
        if ($length < 0)
            $length = 0;
        if (($start + $length > $file_size) || $length == 0)
            $length = $file_size - $start;

        if ($start > 0 && $start < $file_size) {
            if (fseek($this->fd, $start, SEEK_SET) == -1) {
                $this->add_error("File::read()");
            }
        }
        $result = fread($this->fd, $length);
        if ($result === false) {
            $this->add_error("File::read()");
            return "";
        }

        // в php LOCK_UN = 3 это равно LOCK_SH | LOCK_EX. Просто заметка на всякий случай.
        if ($lock_flags & LOCK_UN) {
            $this->unlock();
        }
        if ($reopen) {
            $this->close();
        }
        return $result;
    }
    /**
     * Write content to file. Default mode 'w'
     * If you need to use a different opening mode, use open
     */
    public function write(string $data, int $lock_flags = 0, int $start = 0, int $length = 0): void
    {
        $reopen = !$this->is_writable();
        if ($reopen) {
            $this->open('w');
        }
        if (($lock_flags & LOCK_EX) > 0) {
            if ($this->locked)
                $this->unlock();
            $this->lock($lock_flags);
        }

        if (fseek($this->fd, $start, SEEK_SET) == -1) {
            $this->add_error("File::read()");
        }
        if ($length == 0)
            $length = strlen($data);
        $result = fwrite($this->fd, $data, $length);
        if ($result === false) {
            $this->add_error("File::read()");
        }
        if ($lock_flags & LOCK_UN) {
            $this->unlock();
        }
        if ($reopen) {
            $this->close();
        }
    }

    /**
     * Read file with shred lock
     */
    public function read_lock(int $start = 0, int $length = 0): string
    {
        return $this->read($start, $length, LOCK_SH | LOCK_UN);
    }
    /**
     * Write file with exclusive lock
     */
    public function write_lock(int $start = 0, int $length = 0): string
    {
        return $this->read($start, $length, LOCK_EX | LOCK_UN);
    }

    /**
     * Get file size. If file not exists trying to create it
     */
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
    public function chmod($mode = 0600)
    {
        $octalNumber = 0600;
        if (is_string($mode) && preg_match('/^0[0-7]*$/', $mode)) {
            $octalNumber = intval($mode, 8);
        } else {
            $octalNumber = $mode;
        }
        if (!chmod($this->fpath, $octalNumber))
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