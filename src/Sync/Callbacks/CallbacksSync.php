<?php

namespace Patagona\Pricemonitor\Core\Sync\Callbacks;

use Patagona\Pricemonitor\Core\Infrastructure\Logger;
use Patagona\Pricemonitor\Core\Infrastructure\Proxy;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;

class CallbacksSync
{
    /** @var  CallbackDTO[] $callbacks */
    private $callbacks;
    
    /** @var  Proxy */
    private $proxy;
    
    public function __construct()
    {
        $apiCredentials = ServiceRegister::getConfigService()->getCredentials();
        $this->proxy = Proxy::createFor($apiCredentials['email'], $apiCredentials['password']);
    }

    /**
     * @param CallbackDTO[] $callbacks
     * @param $contractId
     * @param array $callbacksNamesForDelete
     *
     * @throws \Exception
     */
    public function registerCallbacks(array $callbacks, $contractId, array $callbacksNamesForDelete = [])
    {
        $this->callbacks = $callbacks;
        $this->validateCallbacksForRegistration();
       
        $callbacksForRegistration = $this->createCallbacksForRegistration($contractId, $callbacksNamesForDelete);
        $registeredCallbacks = $this->proxy->registerCallbacks($callbacksForRegistration, $contractId);
        $this->validateRegisteredCallbacks($registeredCallbacks, $callbacksForRegistration);
    }

    private function validateCallbacksForRegistration()
    {
        $callbacksNames = [];

        /** @var CallbackDTO $callback */
        foreach ($this->callbacks as $callback) {
            $callbackName = $callback->getName();
            if (!empty($callbacksNames[$callbackName]) || empty($callbackName)) {
                $errorMessage = "'Callback names must be unique and must not be empty. Callback name: {$callbackName} not unique.";
                Logger::logError($errorMessage);
                throw new \Exception($errorMessage);
            }

            $callbacksNames[$callbackName] = $callbackName;

            if (!in_array(strtoupper($callback->getMethod()), ['GET', 'HEAD', 'PUT', 'PATCH', 'POST', 'DELETE'])) {
                $errorMessage =
                    'Unknown HTTP method: ' . $callback->getMethod() . ' for callback with name:' . $callbackName . '.';
                Logger::logError($errorMessage);
                throw new \Exception($errorMessage);
            }

            if (!filter_var($callback->getUrl(), FILTER_VALIDATE_URL)) {
                $errorMessage =
                    'Passed url: ' . $callback->getUrl() . ' is not valid for callback with name:' . $callbackName . '.';
                Logger::logError($errorMessage);
                throw new \Exception($errorMessage);
            }
        }
    }

    /**
     * @param string $contractId
     * @param array $callbacksNamesForDelete
     * 
     * @return array
     */
    private function createCallbacksForRegistration($contractId, array $callbacksNamesForDelete)
    {
        $existingCallbacks = $this->proxy->getCallbacks($contractId);
        $callbacksForRegistration = [];

        foreach ($this->callbacks as $callback) {
            if (empty($callbacksForRegistration[$callback->getName()]) &&
                !in_array($callback->getName(), $callbacksNamesForDelete)
            ) {
                $callbacksForRegistration[$callback->getName()] = $callback;
            }
        }

        foreach ($existingCallbacks as $existingCallback) {
            if (!isset($callbacksForRegistration[$existingCallback->getName()]) &&
                !in_array($existingCallback->getName(), $callbacksNamesForDelete)
            ) {
                $callbacksForRegistration[$existingCallback->getName()] = $existingCallback;
            }
        }

        return array_values($callbacksForRegistration);
    }

    private function validateRegisteredCallbacks($registeredCallbacks, $callbacksForRegistration)
    {
        if (count($registeredCallbacks) !== count($callbacksForRegistration)) {
            $errorMessage = 'Passed and saved callbacks do not match.';
            Logger::logError($errorMessage);
            throw new \Exception($errorMessage);
        }
    }

}