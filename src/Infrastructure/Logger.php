<?php

namespace Patagona\Pricemonitor\Core\Infrastructure;

class Logger
{
    const INFO = 'Info';
    const WARNING = 'Warning';
    const ERROR = 'Error';

    /**
     * Logging info message
     *
     * @param string $message
     * @param string $contractId
     */
    public static function logInfo($message, $contractId = '')
    {
        self::logMessage($message, self::INFO, $contractId);
    }

    /**
     * Logging error message
     *
     * @param string $message
     * @param string $contractId
     */
    public static function logError($message, $contractId = '')
    {
        self::logMessage($message, self::ERROR, $contractId);
    }

    /**
     * Logging warning message
     *
     * @param string $message
     * @param string $contractId
     */
    public static function logWarning($message, $contractId = '')
    {
        self::logMessage($message, self::WARNING, $contractId);
    }

    /**
     * Logging message
     *
     * @param $message
     * @param $level
     * @param string $contractId
     */
    private static function logMessage($message, $level, $contractId = '')
    {
        $loggerServices = ServiceRegister::getLoggerService();
        foreach ($loggerServices as $loggerService) {
            $loggerService->logMessage($message, $level, $contractId);
        }
    }
    
}