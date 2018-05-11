<?php

namespace Patagona\Pricemonitor\Core\Interfaces;

interface LoggerService
{

    /**
     * Logging message in external system
     *
     * @param $message
     * @param $level
     * @param string $contractId
     */
    public function logMessage($message, $level, $contractId = '');
    
}