<?php

namespace Patagona\Pricemonitor\Core\Tests\Infrastructure;

use Exception;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestHttpClient;
use PHPUnit\Framework\TestCase;
use Patagona\Pricemonitor\Core\Interfaces\Queue\Storage;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Interfaces\LoggerService;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestQueueStorage;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestLoggerService;

/**
 * @covers \Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister
 */
class ServiceRegisterTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        // Reset service registry instance
        new ServiceRegister();
    }

    /**
     * Test simple registering the service and getting the instance back
     *
     * @throws Exception
     */
    public function testSimpleRegisterAndGet()
    {
        new ServiceRegister([
            Storage::class => new TestQueueStorage(),
        ]);

        $result = ServiceRegister::getQueueStorage();

        $this->assertInstanceOf(Storage::class, $result);
    }

    /**
     * Test throwing exception when service is not registered
     *
     * @throws Exception
     */
    public function testThrowingException()
    {
        $this->expectException(Exception::class);
        ServiceRegister::getConfigService();
    }

    /**
     * Test registering several logger services and checking if get method returns array of logger service instance
     *
     * @throws Exception
     */
    public function testLoggerService()
    {
        new ServiceRegister([], [
            new TestLoggerService(),
            new TestLoggerService(),
        ]);

        $result = ServiceRegister::getLoggerService();

        $this->assertContainsOnlyInstancesOf(LoggerService::class, $result);
    }

    public function testItShoudBePossibleToRegisterHttpClientInServiceRegister()
    {
        $client = new TestHttpClient();

        ServiceRegister::registerHttpClient($client);

        $this->assertSame($client, ServiceRegister::getHttpClient());
    }

}
