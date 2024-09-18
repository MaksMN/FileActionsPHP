<?php

namespace File;

use File;

if (!defined('DIRECT_ACCESS_KEY')) die('direct access');

/**
 * Создает объект обработки уникального файла с рандомным именем.
 */
class RandomFile extends File
{
    /**
     * @param dir string    Directory for file
     * @param ext string|null    File extension
     * @param time_prefix_format string|null    Prefix format - date_time. NULL - ignore
     * @param prefix string Custom prefix
     * @param random_len int Random string length
     * @param lock_file_name string Lock file name
     */
    public function __construct(
        string $dir,
        string $mode = 'c+',
        string $ext = null,
        $perms = 0600,
        string $prefix = '',
        string $time_prefix_format = 'd_m_Y__H_i_s__',
        int $random_len = 10,
        string $lock_file_name = 'lock'
    ) {
        if (file_exists($dir)) {
            if (!is_dir($dir)) {
                throw new \Exception("ERROR: RandomFile::__construct(): directory is file ", 1);
            }
        } else {
            mkdir($dir);
        }

        $lock_file = fopen($dir . '/' . $lock_file_name, 'c');
        if (!$lock_file) {
            $error = error_get_last();
            throw new \Exception("ERROR: RandomFile::__construct(): Could not get a file lock. " . $error['message'], 1);
        }
        if (!flock($lock_file, LOCK_EX)) {
            $error = error_get_last();
            throw new \Exception("ERROR: RandomFile::__construct(): Could not get a file lock. " . $error['message'], 1);
        }
        $ext = is_null($ext) ? "" : ".$ext";
        $file_path = $dir . "/" . $this->stampToDate($time_prefix_format) . $prefix . $this->randomString($random_len) . $ext;
        while (file_exists($file_path)) {
            $file_path = $dir . "/" . $this->stampToDate($time_prefix_format) . $prefix . $this->randomString($random_len) . $ext;
        }
        $fd = fopen($file_path, $mode);
        if (!$fd) {
            $error = error_get_last();
            throw new \Exception("ERROR: RandomFile::__construct(): Failed to create file $file_path. " . $error['message'], 1);
        }
        fclose($lock_file);

        $this->fd = $fd;
        $this->fpath = $file_path;
        $this->mode = $mode;
        $this->perms = $perms;
        $this->opened = true;
        $this->chmod($perms);
    }

    private function randomString(int $length = 1): string
    {
        if ($length < 1)
            $length = 1;
        $bytes = openssl_random_pseudo_bytes($length);
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $characters_len = strlen($characters);

        $str_out = "";
        foreach (str_split($bytes) as $ch) {
            $num = ord($ch) % $characters_len;
            $str_out .= $characters[$num];
        }
        return $str_out;
    }
    private function stampToDate(string $format = NULL): string
    {
        if (is_null($format) || strlen($format) == 0)
            return "";
        return date($format, time());
    }
}
