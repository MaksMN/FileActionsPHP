<?php

class SimpleFile extends File
{
    public function __construct(string $file_path, $perms = 0600)
    {
        $fd = fopen($file_path, 'c');
        if ($fd === false) {
            $error = error_get_last();
            throw new Exception("ERROR: SimpleFile::__construct(): Could not open a file. " . $error['message'], 1);
        }
        fclose($fd);
        chmod($file_path, $perms);
        $this->fpath = $file_path;
        $this->perms = $perms;
    }
}
