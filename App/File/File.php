<?php

abstract class File
{
    protected string $fpath;
    protected $perms = 0600;
    protected $fd = false;
    protected bool $opened = false;
    protected string $mode = 'c+';
    protected bool $locked = false;
    protected $lock_flags = LOCK_UN;
    protected int $errno = 0;
    protected string $error_message = '';

    public function exists(): bool
    {
        return file_exists($this->fpath) && is_file($this->fpath);
    }


    /**
     * Close file. Unlock if locked.
     */
    public function close(): void
    {

        fclose($this->fd);
        $this->opened = false;
        $this->mode = '';
        $this->fd = false;
    }

    /**
     * Read file content.
     */
    public function read(int $start = 0, int $length = 0): string
    {
        if (!$this->isReadable())
            return "";
        $file_size = $this->size();

        if ($start < 0)
            $start = 0;
        if ($start > $file_size)
            $start = $file_size - 1;
        if ($length <= 0)
            $length = $file_size;
        if (($start + $length > $file_size) || $length == 0)
            $length = $file_size - $start;

        if ($start > 0 && $start < $file_size) {
            if (fseek($this->fd, $start, SEEK_SET) == -1) {
                $this->addError("File::read()");
            }
        }
        $result = "";
        if ($file_size > 0) {
            $result = fread($this->fd, $length);
        }

        if ($result === false) {
            $this->addError("File::read()");
            $result = "";
        }

        return $result;
    }
    /**
     * Write content to file.
     */
    public function write(string $data, int $start = 0): void
    {
        if (!$this->isWritable())
            return;

        if ($start < 0)
            $start = 0;

        if (fseek($this->fd, $start, SEEK_SET) == -1) {
            $this->addError("File::read()");
        }
        $length = strlen($data);
        $result = fwrite($this->fd, $data, $length);
        if ($result === false) {
            $this->addError("File::read()");
        }
    }

    /**
     * Read file with shred lock
     */
    public function readLock(int $start = 0, int $length = 0): string
    {
        $this->lockSH();
        $result = $this->read($start, $length);
        $this->unlock();
        return $result;
    }
    /**
     * Write file with exclusive lock
     */
    public function writeLock(string $data, int $start = 0, int $length = 0): void
    {
        $this->lockEX();
        $this->write($data, $start, $length);
        $this->unlock();
    }

    /**
     * Get file size. If file not exists trying to create it
     */
    public function size(): int
    {
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
            $this->addError("Fle::lock()");
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
            $this->addError("Fle::lock()");
        } else {
            $this->locked = false;
            $this->lock_flags = LOCK_UN;
        }
    }

    /**
     *  Acquire an exclusive lock (writer).
     */
    public function lockEX(): void
    {
        if ($this->isWritable())
            $this->lock(LOCK_EX);
    }

    /**
     * Acquire a shared lock (reader).
     */
    public function lockSH(): void
    {
        if ($this->isReadable())
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
    public function isReadable(): bool
    {
        return $this->opened && in_array($this->mode, ['r', 'r+', 'w+', 'a+', 'x+', 'c+']);
    }

    /**
     * File opened for write
     */
    public function isWritable(): bool
    {
        return $this->opened && $this->mode != 'r';
    }

    /**
     * File open for read/write
     */
    public function isRW(): bool
    {
        return $this->isReadable() && $this->isWritable();
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
            $this->addError("File::chmod: ");
    }

    /* error methods */
    public function errorNumber(): int
    {
        return $this->errno;
    }
    public function errorMessage(): string
    {
        return $this->error_message;
    }
    public function errorClear(): void
    {
        $this->errno = 0;
        $this->error_message = '';
    }
    protected function addError(string $prefix = ''): void
    {
        $error = error_get_last();
        $this->error_message .= '[' . $error['type'] . '] ' . $error['message'] . "\n";
        $this->errno = $error['type'];
    }
    public function isError(): bool
    {
        return $this->errno != 0;
    }
}
