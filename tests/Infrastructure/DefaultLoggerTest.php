<?php

namespace Patagona\Pricemonitor\Core\Tests\Infrastructure;

use Patagona\Pricemonitor\Core\Infrastructure\DefaultLogger;
use Patagona\Pricemonitor\Core\Infrastructure\Logger;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Interfaces\ConfigService;
use Patagona\Pricemonitor\Core\Interfaces\HttpClient;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestConfigService;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestHttpClient;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestHttpResponse;
use PHPUnit\Framework\TestCase;

class DefaultLoggerTest extends TestCase
{
    /** @var  ConfigService */
    private $configService;

    /** @var TestHttpClient */
    private $client;

    public function setUp()
    {
        $this->client = new TestHttpClient();
        $this->configService = new TestConfigService();

        new ServiceRegister([
            ConfigService::class => $this->configService,
            HttpClient::class => $this->client,
        ], [
            new DefaultLogger()
        ]);
    }

    /**
     * Test request parameters when contract id is not sent
     */
    public function testDefaultLoggerRequestParamsWithoutContractId()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(200),
        ]);

        $message = 'Test info message';
        Logger::logInfo($message);

        $this->assertEquals(
            [
                'type' => TestHttpClient::REQUEST_TYPE_ASYNC,
                'method' => 'POST',
                'url' => 'https://app.patagona.de/api/2/log/messages',
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode("test@example.com:test"),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'message' => $message,
                    'severity' => 'info',
                    'source' => $this->configService->getSource(),
                    'component' => $this->configService->getComponentName(),
                ]),
            ],
            $this->client->getLastRequest()
        );
    }

    /**
     * Test request parameters when contract id is sent
     */
    public function testDefaultLoggerRequestParamsWithContractId()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(200),
        ]);

        $message = 'Test info message';
        $contractId = '3p7h3i';
        Logger::logInfo($message, $contractId);

        $postData = json_decode($this->client->getLastRequest()['body'], true);

        $this->assertArrayHasKey('contractId', $postData);
        $this->assertEquals($contractId, $postData['contractId']);
    }

}
