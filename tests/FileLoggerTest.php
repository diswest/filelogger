<?php

namespace Diswest\FileLogger\Tests;

use Diswest\FileLogger\Exception\IncorrectPathException;
use Diswest\FileLogger\FileLogger;
use Diswest\FileLogger\Tests\Fixtures\Stringable;
use Diswest\FileLogger\Tests\Fixtures\NonStringable;
use org\bovigo\vfs\content\LargeFileContent;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use org\bovigo\vfs\vfsStreamWrapper;
use PHPUnit_Framework_TestCase;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

class FileLoggerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var vfsStreamDirectory
     */
    protected $root;

    public function setUp()
    {
        vfsStreamWrapper::register();
        $this->root = new vfsStreamDirectory('log');
        vfsStreamWrapper::setRoot($this->root);
    }

    /**
     * @covers Diswest\FileLogger\FileLogger::__construct()
     *
     * @expectedException \Diswest\FileLogger\Exception\IncorrectPathException
     */
    public function testLoggerInitHasIncorrectPath()
    {
        new FileLogger('wrong', 'test');
    }

    /**
     * @covers Diswest\FileLogger\FileLogger::__construct()
     */
    public function testLoggerInitHasCorrectPath()
    {
        $logDir = $this->root->url();
        try {
            $logger = new FileLogger($logDir, 'test');
            $this->assertInstanceOf('\Diswest\FileLogger\FileLogger', $logger);
        } catch (IncorrectPathException $e) {
            $this->fail();
        }
    }

    /**
     * @covers Diswest\FileLogger\FileLogger::__construct()
     *
     * @param $level
     *
     * @dataProvider levelsProvider
     */
    public function testLoggerInitHandleCorrectLevels($level)
    {
        $logDir = $this->root->url();
        try {
            $logger = new FileLogger($logDir, 'test', $level);
            $this->assertInstanceOf('\Diswest\FileLogger\FileLogger', $logger);
        } catch (InvalidArgumentException $e) {
            $this->fail();
        }
    }

    /**
     * @covers Diswest\FileLogger\FileLogger::__construct()
     *
     * @expectedException InvalidArgumentException
     */
    public function testLoggerInitThrowsExceptionOnUnsupportedLevel()
    {
        $logDir = $this->root->url();
        new FileLogger($logDir, 'test', 'wrong');
    }

    /**
     * @covers Diswest\FileLogger\FileLogger::log()
     */
    public function testLogCreatesDirectory()
    {
        $logDir = $this->root->url();
        $logger = new FileLogger($logDir, 'test');
        $logger->debug('qwe');

        $this->assertTrue($this->root->hasChild('test'));
        $this->assertTrue(is_dir($this->root->getChild('test')->url()));
    }

    /**
     * @covers Diswest\FileLogger\FileLogger::log()
     */
    public function testLogCreatesFile()
    {
        $logDir = $this->root->url();
        $logger = new FileLogger($logDir, 'test');
        $logger->debug('qwe');

        $this->assertTrue($this->root->hasChild('test/test.log'));
    }

    /**
     * @covers Diswest\FileLogger\FileLogger::log()
     */
    public function testLogWriteWellFormattedEntry()
    {
        $level = LogLevel::DEBUG;
        $message = 'qwe';

        $logDir = $this->root->url();
        $logger = new FileLogger($logDir, 'test');
        $logger->log($level, $message);

        $logEntry = $this->root->getChild('test/test.log')->getContent();
        $entryPattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}\s' . strtoupper($level) .'\s' . $message . '$/';
        $this->assertRegExp($entryPattern, $logEntry);
    }

    /**
     * @covers Diswest\FileLogger\FileLogger::log()
     *
     * @param $level
     *
     * @dataProvider levelsProvider
     */
    public function testLogHandleCorrectLevels($level)
    {
        $logDir = $this->root->url();
        $logger = new FileLogger($logDir, 'test');
        $logger->log($level, 'qwe');

        $this->assertTrue($this->root->hasChild('test/test.log'));
    }

    /**
     * @covers Diswest\FileLogger\FileLogger::log()
     *
     * @expectedException \Psr\Log\InvalidArgumentException
     */
    public function testLogThrowsExceptionOnUnsupportedLevel()
    {
        $logDir = $this->root->url();
        $logger = new FileLogger($logDir, 'test');
        $logger->log('wrong', 'qwe');
    }

    /**
     * @covers Diswest\FileLogger\FileLogger::log()
     */
    public function testLogFilterLevels()
    {
        $logDir = $this->root->url();
        $logger = new FileLogger($logDir, 'test', LogLevel::WARNING);

        // will be written
        $logger->warning('qwe');
        $logFile = $this->root->getChild('test/test.log');
        $firstRecordLogSize = $logFile->size();

        // will be written
        $logger->error('qwe');
        $secondRecordLogSize = $logFile->size();

        // will not be written
        $logger->notice('qwe');
        $thirdRecordLogSize = $logFile->size();

        $this->assertLessThan($secondRecordLogSize, $firstRecordLogSize);
        $this->assertSame($thirdRecordLogSize, $secondRecordLogSize);
    }

    /**
     * @covers Diswest\FileLogger\FileLogger::log()
     *
     * @param $message
     * @param $context
     * @param $processedText
     *
     * @dataProvider placeholdersProvider
     */
    public function testLogPlaceholders($message, $context, $processedText) {
        $logDir = $this->root->url();
        $logger = new FileLogger($logDir, 'test');

        $logger->debug($message, $context);

        /**
         * @var vfsStreamFile $logFile
         */
        $logFile = $this->root->getChild('test/test.log');

        $logEntry = $logFile->getContent();
        list($time, $level, $text) = explode("\t", $logEntry);

        $this->assertSame($processedText, trim($text));
        $logFile->truncate(0);
    }

    /**
     * @covers Diswest\FileLogger\FileLogger::log()
     */
    public function testLogRotation()
    {
        $logDir = $this->root->url();
        $logger = new FileLogger($logDir, 'test', LogLevel::DEBUG, 1);

        $logger->debug('qwe');

        /**
         * @var vfsStreamFile $logFile
         */
        $logFile = $this->root->getChild('test/test.log');
        $logFile->setContent(LargeFileContent::withMegabytes(1));

        $logger->debug('qwe');
        $files = $this->root->getChild('test')->getChildren();

        $this->assertCount(2, $files);

        $filenamePattern = '/^test(_\d{4}-\d{2}-\d{2}_\d{2}:\d{2}:\d{2})?\.log$/';
        foreach ($files as $file) {
            /**
             * @var vfsStreamFile $file
             */
            $this->assertRegExp($filenamePattern, $file->getName());
        }

    }

    /**
     * @return array
     */
    public function levelsProvider()
    {
        return [
            [LogLevel::DEBUG],
            [LogLevel::INFO],
            [LogLevel::NOTICE],
            [LogLevel::WARNING],
            [LogLevel::ERROR],
            [LogLevel::ALERT],
            [LogLevel::CRITICAL],
            [LogLevel::EMERGENCY],
        ];
    }

    /**
     * @return array
     */
    public function placeholdersProvider()
    {
        return [
            [
                '{foo} {bar}',
                ['foo' => 'baz', 'bar' => 'zoo'],
                'baz zoo',
            ],
            [
                '{foo} {bar}',
                ['foo' => 'baz'],
                'baz {bar}',
            ],
            [
                '{foo}',
                ['foo' => 'baz', 'bar' => 'zoo'],
                'baz',
            ],
            [
                '{foo} {bar}',
                ['foo' => 'baz', 'bar' => []],
                'baz {bar}',
            ],
            [
                '{foo} {bar}',
                ['foo' => 'baz', 'bar' => $this->getStringableObject()],
                'baz zoo',
            ],
            [
                '{foo} {bar}',
                ['foo' => 'baz', 'bar' => $this->getNonStringableObject()],
                'baz {bar}',
            ],
            [
                '{exception}',
                ['exception' => 'not exception'],
                '{exception}',
            ]
        ];
    }

    /**
     * @return Stringable
     */
    protected function getStringableObject()
    {
        $mock = $this->getMock('\Diswest\FileLogger\Tests\Fixtures\Stringable');
        $mock
            ->expects($this->any())
            ->method('__toString')
            ->willReturn('zoo');

        return $mock;
    }

    /**
     * @return NonStringable
     */
    protected function getNonStringableObject()
    {
        return new NonStringable();
    }
}
