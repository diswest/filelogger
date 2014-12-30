<?php

namespace Diswest\FileLogger;

use DateTime;
use Diswest\FileLogger\Exception\IncorrectPathException;
use Exception;
use Exception\FilesystemException;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

/**
 * Simple file logger
 */
class FileLogger extends AbstractLogger
{
    /**
     * Number of bytes in megabyte
     */
    const BYTES_IN_MB = 1048576;

    /**
     * Extension of log files
     */
    const LOG_EXTENSION = '.log';

    /**
     * Log path
     * @var string
     */
    protected $path = null;

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
    protected $levels = [
        LogLevel::DEBUG     => 10,
        LogLevel::INFO      => 20,
        LogLevel::NOTICE    => 30,
        LogLevel::WARNING   => 40,
        LogLevel::ERROR     => 50,
        LogLevel::CRITICAL  => 60,
        LogLevel::ALERT     => 70,
        LogLevel::EMERGENCY => 80,
    ];

    /**
     * @param string                     $path Log path
     * @param string                     $name Name of the log
     * @param string                     $level Minimum handled level
     * @param int                        $maxFileSize Maximum size of logfile
     * @param FilesystemHandlerInterface $filesystemHandler
     *
     * @throws IncorrectPathException
     */
    public function __construct(
        $path,
        $name,
        $level = LogLevel::DEBUG,
        $maxFileSize = 100,
        FilesystemHandlerInterface $filesystemHandler = null
    ) {
        // Handle unsupported level
        if (!isset($this->levels[$level])) {
            throw new InvalidArgumentException('Unknown log level: ' . var_export($level, true));
        }

        $this->path              = $path;
        $this->name              = $name;
        $this->level             = $level;
        $this->maxFileSize       = $maxFileSize * self::BYTES_IN_MB;

        if (!$filesystemHandler) {
            $filesystemHandler = new FilesystemHandler();
        }
        $this->filesystemHandler = $filesystemHandler;

        if (!$filesystemHandler->checkPath($this->path)) {
            throw new IncorrectPathException($this->path);
        }
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
        if (!isset($this->levels[$level])) {
            throw new InvalidArgumentException('Unsupported log level: ' . var_export($level, true));
        }

        // Skip message if it level is less than handled level of log
        if ($this->levels[$level] < $this->levels[$this->level]) {
            return;
        }

        $logFile = $this->getLogFile();
        $preparedMessage = $this->prepareMessage($message, $context);
        $this->filesystemHandler->write($logFile, $preparedMessage);
    }

    /**
     * Checks whether the variable can be converted to string
     *
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
     * Returns logfile
     *
     * @return string mixed
     */
    protected function getLogFile()
    {
        $logFileWithoutExtension = $this->getLogDirectory() . DIRECTORY_SEPARATOR . $this->name;
        $logFile = $logFileWithoutExtension . self::LOG_EXTENSION;

        // Rotate file if full
        if ($this->filesystemHandler->isExists($logFile) && $this->isLogFileFull($logFile)) {
            $endTime = (new DateTime())->format('Y-m-d H:i:s');
            $endTime = strtr($endTime, ' ', '_');
            $archiveLogFile = $logFileWithoutExtension . '_' . $endTime . self::LOG_EXTENSION;
            $this->filesystemHandler->mv($logFile, $archiveLogFile);
            $this->filesystemHandler->close();
        }

        return $logFile;
    }

    /**
     * Returns actual directory for current log
     *
     * @return string
     * @throws FilesystemException
     */
    protected function getLogDirectory()
    {
        $logDir = $this->path . DIRECTORY_SEPARATOR . $this->name;

        // Create dir if not exists
        if (!$this->filesystemHandler->isDir($logDir)) {
            if (!$this->filesystemHandler->mkdir($logDir)) {
                throw new FilesystemException('Can\'t create log dir ' . $logDir);
            }
        }

        return $logDir;
    }

    /**
     * Checks size of log file
     *
     * @param $logFile
     *
     * @return bool
     */
    protected function isLogFileFull($logFile)
    {
        $fileSize = $this->filesystemHandler->getFileSize($logFile);

        return $fileSize >= $this->maxFileSize;
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
        $messageData = [
            $this->getCurrentTime(),
            strtoupper($this->level),
            $this->interpolate($message, $context),
        ];

        $preparedMessage = vsprintf("%s\t%s\t%s\n", $messageData);

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
        $replace = [];

        foreach ($context as $key => $item) {
            if (!$this->isConvertibleToString($item)) {
                continue;
            }

            $wrappedKey = '{' . $key . '}';

            // Item with key 'exception' must be instance of Exception
            if ($key == 'exception' && !($item instanceof Exception)) {
                continue;
            }

            if (is_object($item)) {
                // Convert object to string
                $replace[$wrappedKey] = $item->__toString();
            } elseif (is_bool($item)) {
                // Readable boolean
                $replace[$wrappedKey] = ($item) ? 'true' : 'false';
            } else {
                // Any other string-compatible value
                $replace[$wrappedKey] = (string) $item;
            }
        }

        return strtr($message, $replace);
    }
}
