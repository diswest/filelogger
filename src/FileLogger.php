<?php

namespace Diswest\FileLogger;

use DateTime;
use Exception;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

/**
 * Simple file logger
 */
class FileLogger extends AbstractLogger
{
    /**
     * Name of the log
     * @var string
     */
    protected $name = null;

    /**
     * Minimum handled level
     * @var string
     * @see Psr\Log\LogLevel
     */
    protected $level = null;

    /**
     * Maximum size of logfile
     * @var int
     */
    protected $maxFileSize = null;

    /**
     * Filesystem handler
     * @var FilesystemHandlerInterface
     */
    protected $filesystemHandler = null;

    /**
     * List of correct levels with priority
     * @var array
     */
    protected $levels = array(
        LogLevel::DEBUG     => 10,
        LogLevel::INFO      => 20,
        LogLevel::NOTICE    => 30,
        LogLevel::WARNING   => 40,
        LogLevel::ERROR     => 50,
        LogLevel::CRITICAL  => 60,
        LogLevel::ALERT     => 70,
        LogLevel::EMERGENCY => 80,
    );

    /**
     * @param string $name Name of the log
     * @param string $level Minimum handled level
     * @param int    $maxFileSize Maximum size of logfile
     */
    public function __construct($name, $level = LogLevel::DEBUG, $maxFileSize = 100)
    {
        // Handle unsupported level
        if (isset($this->levels[$level])) {
            throw new InvalidArgumentException('Unknown log level: ' . var_export($level, true));
        }

        $this->name        = $name;
        $this->level       = $level;
        $this->maxFileSize = $maxFileSize;
    }

    /**
     * Set filesystem handler
     *
     * @param FilesystemHandlerInterface $filesystemHandler
     *
     * @return $this
     */
    public function setFilesystemHandler(FilesystemHandlerInterface $filesystemHandler) {
        $this->filesystemHandler = $filesystemHandler;
        return $this;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return null
     */
    public function log($level, $message, array $context = array())
    {
        // Handle unsupported level
        if (isset($this->levels[$level])) {
            throw new InvalidArgumentException('Unsupported log level: ' . var_export($level, true));
        }

        // Skip message if it level is less than handled level of log
        if ($this->levels[$level] < $this->levels[$this->level]) {
            return;
        }

        $logFile = $this->getLogFile();
        $preparedMessage = $this->prepareMessage($message, $context);
        $this->getFileSystemHandler()->write($logFile, $preparedMessage);
    }

    /**
     * Check that the variable can be converted to string
     * @param mixed $var
     *
     * @return bool
     */
    protected function isConvertibleToString($var)
    {
        if (is_array($var)) {
            return false;
        }

        if (is_object($var) && !method_exists($var, '__toString')) {
            return false;
        }

        if (settype($var, 'string') === false) {
            return false;
        }

        return true;
    }

    /**
     * Returns filesystem handler object
     *
     * @return FilesystemHandlerInterface
     */
    protected function getFileSystemHandler()
    {
        if (!$this->filesystemHandler) {
            $this->filesystemHandler = new FilesystemHandler();
        }

        return $this->filesystemHandler;
    }

    /**
     * Returns logfile
     *
     * @return string mixed
     */
    protected function getLogFile()
    {
        $name = $this->name;
        return $name;
    }

    protected function createLogDirectory()
    {

    }

    /**
     * Prepare message for writing to log
     *
     * @param string $message
     * @param array  $context
     *
     * @return mixed
     */
    protected function prepareMessage($message, array $context)
    {
        $messageData = array(
            $this->getCurrentTime(),
            strtoupper($this->level),
            $this->interpolate($message, $context),
        );
        $preparedMessage = sprintf('%s\t%s\t', $messageData);
        return $preparedMessage;
    }

    /**
     * Return current time in ISO-8601 (YYYY-MM-DDThh:mm:ss.mmm)
     *
     * @return string
     */
    protected function getCurrentTime()
    {
        list($ms, $time) = explode(' ', microtime());

        return sprintf(
            '%s.%03d',
            (new DateTime())->setTimestamp($time)->format('Y-m-d\TH:i:s'),
            $ms * 1000
        );
    }

    /**
     * Process placeholders
     *
     * @param string $message
     * @param array  $context
     *
     * @return string
     */
    protected function interpolate($message, array $context)
    {
        foreach ($context as $key => $item) {
            if (!$this->isConvertibleToString($item)) {
                continue;
            }

            if (is_object($item)) {
                // Item with key 'exception' must be instance of Exception
                if ($key == 'exception' && !($item instanceof Exception)) {
                    continue;
                }

                // Convert object to string
                $replace = $item->__toString();
            } elseif (is_bool($item)) {
                // Readable boolean
                $replace = ($item) ? 'true' : 'false';
            } else {
                // Any other string-compatible value
                $replace = (string) $item;
            }

            $message = str_replace('{' . $key . '}', $replace, $message);
        }

        return $message;
    }
}
