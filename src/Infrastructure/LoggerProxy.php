<?php

namespace Patagona\Pricemonitor\Core\Infrastructure;

class LoggerProxy extends BaseProxy
{

    const BASE_API_URL = 'https://app.patagona.de/api/2/log';

    /**
     * Creates Pricemonitor API logger proxy
     *
     * @param string $email Pricemonitor account email
     * @param string $password Pricemonitor account password
     *
     * @return \Patagona\Pricemonitor\Core\Infrastructure\LoggerProxy for Pricemonitor API
     */
    public static function createFor($email, $password)
    {
        return new self($email, $password, ServiceRegister::getHttpClient());
    }
    
    /**
     * Send log messages to PM API
     *
     * @param $data
     */
    public function logMessage($data)
    {
        $this->requestAsync('POST', '/messages', [], $data);
    }
    
}