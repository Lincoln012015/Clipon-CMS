<?php

class AtomicFileWriter
{
    public static function write(string $file, string $content): bool
    {
        $dir = dirname($file);
        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }

        $tmp = @tempnam($dir, 'afw_');
        if ($tmp === false) {
            return false;
        }

        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            @unlink($tmp);
            return false;
        }

        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }

        return true;
    }
}