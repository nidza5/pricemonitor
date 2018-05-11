<?php

namespace Patagona\Pricemonitor\Core\Tests\Infrastructure;

use Exception;
use Patagona\Pricemonitor\Core\Tests\TestComponents\OtherTestLoggerService;
use PHPUnit\Framework\TestCase;
use Patagona\Pricemonitor\Core\Infrastructure\Logger;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestLoggerService;

/**
 * @covers \Patagona\Pricemonitor\Core\Infrastructure\Logger
 */
class LoggerTest extends TestCase
{
    protected $logger1;
    protected $logger2;

    public function setup()
    {
        $this->logger1 = new TestLoggerService();
        $this->logger2 = new OtherTestLoggerService();

        new ServiceRegister([], [$this->logger1, $this->logger2]);
    }

    /**
     * Test logging info messages
     *
     * @throws Exception
     */
    public function testLogInfoMessage()
    {
        $message = 'Test info message';

        Logger::logInfo($message);

        $this->assertEquals($message, $this->logger1->message);
        $this->assertEquals('Info', $this->logger1->level);
    }

    /**
     * Test logging error messages with 2 logger services
     *
     * @throws Exception
     */
    public function testLogErrorMessage()
    {
        $message = 'Test error message';

        Logger::logError($message);

        $this->assertEquals($message, $this->logger1->message);
        $this->assertEquals('Error', $this->logger1->level);

        $this->assertEquals($message, $this->logger2->message);
        $this->assertEquals('Error', $this->logger2->level);
    }
}
