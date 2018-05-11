<?php

namespace Patagona\Pricemonitor\Core\Infrastructure;

use Patagona\Pricemonitor\Core\Interfaces\LoggerService;

class DefaultLogger implements LoggerService
{

    /**
     * @var LoggerProxy
     */
    private $loggerProxy;
    
    public function __construct($loggerProxy = null)
    {
        $this->loggerProxy = $loggerProxy;
    }

    /**
     * Logging message in external system
     *
     * @param $message
     * @param $level
     * @param string $contractId
     */
    public function logMessage($message, $level, $contractId = '')
    {
        $configService = ServiceRegister::getConfigService();

        $data = [
            'message' => $message,
            'severity' => strtolower($level),
            'source' => $configService->getSource(),
            'component' => $configService->getComponentName(),
        ];

        if (!empty($contractId)) {
            $data['contractId'] = $contractId;
        }
        
        $this->getProxy()->logMessage($data);
    }

    /**
     * Get logger proxy
     * 
     * @return LoggerProxy
     */
    private function getProxy()
    {
        if (empty($this->loggerProxy)) {
            $configService = ServiceRegister::getConfigService();
            $credentials = $configService->getCredentials();
            $this->loggerProxy = LoggerProxy::createFor($credentials['email'], $credentials['password']);
        }
        
        return $this->loggerProxy;
    }
    
}