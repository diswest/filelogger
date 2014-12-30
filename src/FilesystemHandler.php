<?php

namespace Diswest\FileLogger;

use Exception\FilesystemException;

/**
 * Filesystem wrapper
 */
class FilesystemHandler
{
    /**
     * Handler of opened file
     * @var resource
     */
    protected $fh = null;

    /**
     * Destructor
     */
    public function __destruct()
    {
        if (is_resource($this->fh)) {
            $this->close($this->fh);
        }
    }

    /**
     * Write text to file
     *
     * @param $file
     * @param $message
     *
     * @throws FilesystemException
     */
    public function write($file, $message)
    {
        if (!is_resource($this->fh)) {
            $this->fh = $this->open($file);
        }

        fwrite($this->fh, $message);
    }

    /**
     * Move file
     *
     * @param $target
     * @param $destination
     */
    public function mv($target, $destination)
    {
        rename($target, $destination);
    }

    /**
     * Creates directory
     *
     * @param $dir
     *
     * @return bool
     */
    public function mkdir($dir)
    {
        return mkdir($dir, 0777, true);
    }

    /**
     * Checks that log path is directory and writable
     *
     * @param string $path
     *
     * @return bool
     */
    public function checkPath($path)
    {
        if (!is_dir($path)) {
            return false;
        }

        if (!is_writable($path)) {
            return false;
        }

        return true;
    }

    /**
     * Checks whether a file or directory exists
     *
     * @param $file
     *
     * @return bool
     */
    public function isExists($file)
    {
        return file_exists($file);
    }

    /**
     * Tells whether the filename is a directory
     *
     * @param $path
     *
     * @return bool
     */
    public function isDir($path)
    {
         return is_dir($path);
    }

    /**
     * Returns file size
     *
     * @param $file
     *
     * @return int
     */
    public function getFileSize($file)
    {
        return filesize($file);
    }

    /**
     * Closes file
     */
    public function close()
    {
        fclose($this->fh);
    }

    /**
     * Opens file
     *
     * @param $file
     *
     * @return resource
     * @throws FilesystemException
     */
    protected function open($file)
    {
        $fh = fopen($file, 'a');
        if (!is_resource($fh)) {
            throw new FilesystemException('Can\'t open file ' . $file);
        }

        return $fh;
    }
}
