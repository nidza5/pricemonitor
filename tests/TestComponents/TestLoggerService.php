<?php

namespace Patagona\Pricemonitor\Core\Tests\TestComponents;

use Patagona\Pricemonitor\Core\Infrastructure\Logger;
use Patagona\Pricemonitor\Core\Interfaces\LoggerService;

class TestLoggerService implements LoggerService
{
    /**
     * Type of message
     * 
     * @var string
     */
    public $level;

    /**
     * Log message
     * 
     * @var string
     */
    public $message;

    public $messageLog = [
        Logger::INFO => [],
        Logger::WARNING => [],
        Logger::ERROR => [],
    ];
    
    /**
     * Logging message in external system
     *
     * @param $message
     * @param $level
     * @param string $contractId
     *
     * @return mixed|void
     */
    public function logMessage($message, $level, $contractId = '')
    {
        $this->level = $level;
        $this->message = $message;

        $this->messageLog[$level][] = $message;
    }
    
}

class OtherTestLoggerService extends TestLoggerService
{

}