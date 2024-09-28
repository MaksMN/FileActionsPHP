<?php

namespace File;

use File;

if (!defined('DIRECT_ACCESS_KEY')) die('direct access');

class SimpleFile extends File
{
    public function __construct(string $file_path, string $mode = 'c+', $perms = 0755)
    {
        $dir = dirname($file_path);
        if (file_exists($dir)) {
            if (is_dir($file_path)) {
                throw new \Exception("ERROR: SimpleFile::__construct(): directory is file ", 1);
            }
        } else {
            if (!mkdir($dir, $perms, true)) {
                $error = error_get_last();
                throw new \Exception("ERROR: SimpleFile::__construct(): Could not create directory. " . $error['message'], 1);
            }
        }
        $fd = fopen($file_path, $mode);
        if ($fd === false) {
            $error = error_get_last();
            throw new \Exception("ERROR: SimpleFile::__construct(): Could not open a file. " . $error['message'], 1);
        }
        $this->fd = $fd;
        $this->fpath = $file_path;
        $this->mode = $mode;
        $this->perms = $perms;
        $this->opened = true;
        $this->chmod($perms);
    }
}
