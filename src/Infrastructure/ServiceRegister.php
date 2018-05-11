<?php

namespace Patagona\Pricemonitor\Core\Infrastructure;

use Exception;
use Patagona\Pricemonitor\Core\Interfaces\ConfigService;
use Patagona\Pricemonitor\Core\Interfaces\FilterStorage;
use Patagona\Pricemonitor\Core\Interfaces\HttpClient;
use Patagona\Pricemonitor\Core\Interfaces\LoggerService;
use Patagona\Pricemonitor\Core\Interfaces\MapperService;
use Patagona\Pricemonitor\Core\Interfaces\PriceService;
use Patagona\Pricemonitor\Core\Interfaces\ProductService;
use Patagona\Pricemonitor\Core\Interfaces\Queue\Storage;
use Patagona\Pricemonitor\Core\Interfaces\TransactionHistoryStorage;

class ServiceRegister
{

    private static $instance;

    /**
     * Array of registered services
     *
     * @var array
     */
    private $services;

    /**
     * Getting service register instance
     *
     * @return ServiceRegister
     */
    public static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new ServiceRegister([], [new DefaultLogger()]);
        }

        return self::$instance;
    }

    public function __construct($services = [], $loggers = []) {
        if (!empty($services)) {
            foreach ($services as $type => $service) {
                $this->register($type, $service);
            }
        }

        if (!empty($loggers)) {
            foreach ($loggers as $logger) {
                $this->addLogger($logger);
            }
        }

        self::$instance = $this;
    }

    /**
     * Gets queue storage instance
     *
     * @return Storage
     * @throws Exception
     */
    public static function getQueueStorage()
    {
        return self::getInstance()->get(Storage::class, 'Queue storage not defined.');
    }

    /**
     * Gets config service instance
     *
     * @return ConfigService
     * @throws Exception
     */
    public static function getConfigService()
    {
        return self::getInstance()->get(ConfigService::class, 'Config service not defined.');
    }

    /**
     * Gets price service instance
     *
     * @return PriceService
     * @throws Exception
     */
    public static function getPriceService()
    {
        return self::getInstance()->get(PriceService::class, 'Price service not defined.');
    }

    /**
     * Gets product service instance
     *
     * @return ProductService
     * @throws Exception
     */
    public static function getProductService()
    {
        return self::getInstance()->get(ProductService::class, 'Product service not defined.');
    }

    /**
     * Gets mapper service instance
     *
     * @return MapperService
     * @throws Exception
     */
    public static function getMapperService()
    {
        return self::getInstance()->get(MapperService::class, 'Mapper service not defined.');
    }

    /**
     * Gets logger service instance
     *
     * @return LoggerService[]
     * @throws Exception
     */
    public static function getLoggerService()
    {
        return self::getInstance()->get(LoggerService::class, 'Logger service not defined.');
    }

    /**
     * Gets http client service instance
     *
     * @return HttpClient
     * @throws Exception
     */
    public static function getHttpClient()
    {
        return self::getInstance()->get(HttpClient::class, 'HttpClient service not defined.');
    }

    /**
     * Gets TransactionHistoryStorage instance
     * 
     * @return TransactionHistoryStorage
     * 
     * @throws Exception
     */
    public static function getTransactionHistoryStorage()
    {
        return self::getInstance()->get(
            TransactionHistoryStorage::class, 
            'TransactionHistoryStorage service not defined.'
        );
    }

    /**
     * Gets FilterStorage instance
     *
     * @return FilterStorage
     *
     * @throws Exception
     */
    public static function getFilterStorage()
    {
        return self::getInstance()->get(FilterStorage::class, 'FilterStorage service not defined.');
    }

    /**
     * Registers queue storage class
     *
     * @param $queueStorageClass
     */
    public static function registerQueueStorage($queueStorageClass)
    {
        self::getInstance()->register(Storage::class, $queueStorageClass);
    }

    /**
     * Registers config service class
     *
     * @param $configServiceClass
     */
    public static function registerConfigService($configServiceClass)
    {
        self::getInstance()->register(ConfigService::class, $configServiceClass);
    }

    /**
     * Registers price service class
     *
     * @param $priceServiceClass
     */
    public static function registerPriceService($priceServiceClass)
    {
        self::getInstance()->register(PriceService::class, $priceServiceClass);
    }

    /**
     * Registers product service class
     *
     * @param $productServiceClass
     */
    public static function registerProductService($productServiceClass)
    {
        self::getInstance()->register(ProductService::class, $productServiceClass);
    }

    /**
     * Registers mapper service class
     *
     * @param $mapperServiceClass
     */
    public static function registerMapperService($mapperServiceClass)
    {
        self::getInstance()->register(MapperService::class, $mapperServiceClass);
    }

    /**
     * Registers logger service class
     *
     * @param $loggerServiceClass
     */
    public static function registerLoggerService($loggerServiceClass)
    {
        self::getInstance()->addLogger($loggerServiceClass);
    }

    /**
     * Registers http client service class
     *
     * @param HttpClient $client
     */
    public static function registerHttpClient(HttpClient $client)
    {
        self::getInstance()->register(HttpClient::class, $client);
    }

    /**
     * Registers TransactionHistoryStorage class
     * 
     * @param TransactionHistoryStorage $transactionStorage
     */
    public static function registerTransactionHistoryStorage(TransactionHistoryStorage $transactionStorage)
    {
        self::getInstance()->register(TransactionHistoryStorage::class, $transactionStorage);
    }

    /**
     * Registers FilterStorage class
     *
     * @param FilterStorage $filterStorage
     */
    public static function registerFilterStorage(FilterStorage $filterStorage)
    {
        self::getInstance()->register(FilterStorage::class, $filterStorage);
    }

    /**
     * Register service
     *
     * @param $type
     * @param $class
     */
    private function register($type, $class)
    {
        $this->services[$type] = $class;
    }

    /**
     * Getting service
     *
     * @param $type
     * @param $exceptionMessage
     *
     * @return mixed
     * @throws Exception
     */
    private function get($type, $exceptionMessage)
    {
        if (empty($this->services[$type])) {
            throw new Exception($exceptionMessage);
        }

        return $this->services[$type];
    }

    /**
     * Adding logger service
     *
     * @param $class
     */
    private function addLogger($class)
    {
        if (!empty($this->services[LoggerService::class]) && in_array($class, $this->services[LoggerService::class])) {
            return;
        }

        $this->services[LoggerService::class][] = $class;
    }

}