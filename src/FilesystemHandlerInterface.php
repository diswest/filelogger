<?php

namespace Diswest\FileLogger;

interface FilesystemHandlerInterface {
    public function open($file);
    public function close($file);
    public function write($file, $message);
    public function mv($target, $destination);
    public function mkdir($dir);
}
