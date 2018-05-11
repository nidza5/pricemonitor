<?php

namespace Patagona\Pricemonitor\Core\Tests\Infrastructure;

use Exception;
use Patagona\Pricemonitor\Core\Infrastructure\Proxy;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestHttpClient;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestHttpResponse;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Patagona\Pricemonitor\Core\Infrastructure\Proxy
 */
class ProxyTest extends TestCase
{
    /** @var TestHttpClient */
    protected $client;

    /** @var Proxy */
    protected $proxy;

    public function setUp()
    {
        // Reset service register (make sure default logger is not registered)
        new ServiceRegister();

        $this->client = new TestHttpClient();
        ServiceRegister::registerHttpClient($this->client);
        $this->proxy = Proxy::createFor('test@example.com', 'test');
    }

    protected function getFakeResponseBody($fakeFile)
    {
        return file_get_contents(TESTS_FAKE_API_RESPONSES_DIR . "/$fakeFile");
    }

    /**
     * Test fetching contracts list from PM API
     *
     * @throws Exception
     */
    public function testGetContractsRegularResponse()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(200, [], $this->getFakeResponseBody("getContracts.json")),
        ]);

        $response = $this->proxy->getContracts();

        $this->assertCount(1, $this->client->getHistory());
        $this->assertEquals(
            [
                'type' => TestHttpClient::REQUEST_TYPE_SYNC,
                'method' => 'GET',
                'url' => 'https://app.patagona.de/api/account',
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode("test@example.com:test"),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => '',
            ],
            $this->client->getLastRequest()
        );

        $this->assertCount(3, $response);

        $this->assertArrayHasKey('3p7h3i', $response);
        $this->assertArrayHasKey('3p7h3j', $response);
        $this->assertArrayHasKey('3p7h3l', $response);
        $this->assertEquals('Test account for shopware', $response['3p7h3i']);
        $this->assertArrayNotHasKey('3p7h3k', $response, 'Inactive contracts should be skipped');
    }

    /**
     * Test fetching contracts list from PM API when account has no companies defined
     *
     * @throws Exception
     */
    public function testGetContractsWithoutCompanies()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(200, [], $this->getFakeResponseBody("getContractsWithoutCompanies.json")),
        ]);

        $response = $this->proxy->getContracts();

        $this->assertCount(0, $response);
        $this->assertCount(1, $this->client->getHistory());
    }

    /**
     * Test fetching contracts list from PM API when account has no contracts defined for any of listed companies
     *
     * @throws Exception
     */
    public function testGetContractsWithoutContracts()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(200, [], $this->getFakeResponseBody("getContractsWithoutContracts.json")),
        ]);

        $response = $this->proxy->getContracts();

        $this->assertCount(0, $response);
        $this->assertCount(1, $this->client->getHistory());
    }

    /**
     * Test fetching contracts list from PM API when API returns bad request status code
     *
     * @throws Exception
     * @expectedException \Exception
     * @expectedExceptionCode 501
     * @expectedExceptionMessageRegExp /Server responded with \d+ [\w+\s]+ status for \w+ request to \w+/
     */
    public function testApiBadRequestResponsesShouldThrowException()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(501, []),
        ]);

        $this->proxy->getContracts();

        $this->fail('Exception should be thrown when response code is not one of 200 family (bad request)');
    }

    /**
     * Test fetching contracts list from PM API when API is not available
     *
     * @throws Exception
     * @expectedException \Exception
     */
    public function testGetContractsWhenAPINotAvailable()
    {
        $this->client->appendMockResponses([
            new \Exception('Communication error test'),
        ]);

        $this->proxy->getContracts();

        $this->fail('Exception should be thrown in case of communication error');
    }

    /**
     * Test fetching contracts list from PM API
     *
     * @throws Exception
     */
    public function testGetExportStatusRegularResponse()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(200, [], $this->getFakeResponseBody("getExportStatus.json")),
        ]);

        $taskId = 'e4608054-f6ec-4061-89cb-7534a9933655';
        $contractId = '3p7h3i';
        $response = $this->proxy->getExportStatus($contractId, $taskId);

        $this->assertCount(1, $this->client->getHistory());
        $this->assertEquals(
            [
                'type' => TestHttpClient::REQUEST_TYPE_SYNC,
                'method' => 'GET',
                'url' => "https://app.patagona.de/api/2/v/contracts/{$contractId}/tasks/{$taskId}",
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode("test@example.com:test"),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => '',
            ],
            $this->client->getLastRequest()
        );

        $this->assertNotEmpty($response);

        $this->assertArrayHasKey('state', $response);
        $this->assertEquals('succeeded', $response['state']);

        $this->assertArrayHasKey('taskId', $response);
        $this->assertEquals($taskId, $response['taskId']);

        $this->assertArrayHasKey('creationDate', $response);
        $this->assertEquals(
            \DateTime::createFromFormat("Y-m-d\TH:i:s.u\Z", "2017-09-05T08:58:33.312Z"),
            $response['creationDate']
        );
        $this->assertArrayHasKey('startDate', $response);
        $this->assertEquals(
            \DateTime::createFromFormat("Y-m-d\TH:i:s.u\Z", "2017-09-05T08:58:33.329Z"),
            $response['startDate']
        );
        $this->assertArrayHasKey('finishDate', $response);
        $this->assertEquals(
            \DateTime::createFromFormat("Y-m-d\TH:i:s.u\Z", "2017-09-05T08:58:33.533Z"),
            $response['finishDate']
        );
    }

    /**
     * Test fetching export status PM API when API is not available
     *
     * @throws Exception
     * @expectedException \Exception
     */
    public function testGetExportStatusWhenAPINotAvailable()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(403, []),
        ]);

        $this->proxy->getExportStatus('test123', 'test_task_123');

        $this->fail('Exception should be thrown in case of communication error');
    }

    /**
     * Test fetching recommended price list from PM API
     *
     * @throws Exception
     */
    public function testImportPricesRegularResponse()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(200, [], $this->getFakeResponseBody("importPrices.json")),
        ]);

        $contractId = '3p7h3i';
        $response = $this->proxy->importPrices($contractId);

        $this->assertCount(1, $this->client->getHistory());
        $this->assertEquals(
            [
                'type' => TestHttpClient::REQUEST_TYPE_SYNC,
                'method' => 'GET',
                'url' => "https://app.patagona.de/api/1/{$contractId}/products/analysis/pricerecommendations?start=0&limit=1000",
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode("test@example.com:test"),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => '',
            ],
            $this->client->getLastRequest()
        );

        $this->assertCount(5, $response);

        $this->assertArrayHasKey('currency', $response[0]);
        $this->assertEquals('EUR', $response[0]['currency']);
        $this->assertArrayHasKey('recommendedPrice', $response[4]);
        $this->assertEquals(156.99, $response[4]['recommendedPrice']);
    }

    /**
     * Test fetching recommended price list from PM API with start and limit parameters
     *
     * @throws Exception
     */
    public function testImportPricesWithPaginationParametersResponse()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(200, [], $this->getFakeResponseBody("importPrices.json")),
        ]);

        $contractId = '3p7h3i';
        $this->proxy->importPrices($contractId, 3, 3);

        $this->assertCount(1, $this->client->getHistory());
        $this->assertEquals(
            [
                'type' => TestHttpClient::REQUEST_TYPE_SYNC,
                'method' => 'GET',
                'url' => "https://app.patagona.de/api/1/{$contractId}/products/analysis/pricerecommendations?start=3&limit=3",
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode("test@example.com:test"),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => '',
            ],
            $this->client->getLastRequest()
        );
    }

    /**
     * Test fetching recommended price list from PM API with since parameter
     *
     * @throws Exception
     */
    public function testImportPricesWithSinceParametersResponse()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(200, [], $this->getFakeResponseBody("importPrices.json")),
        ]);

        $contractId = '3p7h3i';
        $sinceDate = new \DateTime("24 hours ago", new \DateTimeZone('Australia/Perth'));

        $this->proxy->importPrices($contractId, 3, 3, $sinceDate);


        $expectedSinceDate = $sinceDate->setTimezone(new \DateTimeZone('UTC'))->format("Y-m-d\TH:i:s.u\Z");
        $expectedUri = "https://app.patagona.de/api/1/{$contractId}/products/analysis/pricerecommendations";
        $expectedUri .= "?start=3&limit=3&since=" . urlencode($expectedSinceDate);

        $this->assertCount(1, $this->client->getHistory());
        $this->assertEquals(
            [
                'type' => TestHttpClient::REQUEST_TYPE_SYNC,
                'method' => 'GET',
                'url' => $expectedUri,
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode("test@example.com:test"),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => '',
            ],
            $this->client->getLastRequest()
        );
    }

    /**
     * Test fetching recommended price list from PM API with since parameter too far in past
     *
     * @throws Exception
     */
    public function testImportPricesWithSinceParametersNotWithin48HoursSendsRequestWithoutSinceLimitResponse()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(200, [], $this->getFakeResponseBody("importPrices.json")),
        ]);

        $contractId = '3p7h3i';
        $sinceDate = new \DateTime("48 hours ago", new \DateTimeZone('Australia/Perth'));

        $this->proxy->importPrices($contractId, 3, 3, $sinceDate);

        $this->assertCount(1, $this->client->getHistory());
        $this->assertEquals(
            [
                'type' => TestHttpClient::REQUEST_TYPE_SYNC,
                'method' => 'GET',
                'url' => "https://app.patagona.de/api/1/{$contractId}/products/analysis/pricerecommendations?start=3&limit=3",
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode("test@example.com:test"),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => '',
            ],
            $this->client->getLastRequest()
        );
    }

    /**
     * Test fetching empty list of recommended prices from PM API
     *
     * @throws Exception
     */
    public function testImportPricesEmptyResponse()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(200, [], $this->getFakeResponseBody("importPricesEmpty.json")),
        ]);

        $response = $this->proxy->importPrices('test_123');

        $this->assertCount(0, $response);
        $this->assertCount(1, $this->client->getHistory());
    }

    /**
     * Test fetching recommended price list from PM API
     *
     * @throws Exception
     */
    public function testExportProductsRegularRequest()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(202, [], $this->getFakeResponseBody("exportProducts.json")),
        ]);

        $contractId = '3p7h3i';
        $expectedTags = [[
            'key' => 'Custom field test',
            'value' => 'test value',
        ],[
            'key' => 'Other custom field test',
            'value' => 'other test value',
        ]];
        $products = [[
            'gtin' => '45073939',
            'name' => 'Test product',
            'productId' => '741',
            'referencePrice' => '1.95',
            'minPriceBoundary' => '1.85',
            'maxPriceBoundary' => '2.95',
            'tags' => [[
                'key' => 'Custom field',
                'value' => 'test value',
            ]]
        ],[
            'gtin' => 45035982,
            'name' => 'Test product 1',
            'productId' => '745',
            'referencePrice' => 2.95,
            'minPriceBoundary' => 2.85,
            'maxPriceBoundary' => 3.95,
            'tags' => $expectedTags
        ]];
        $response = $this->proxy->exportProducts($contractId, $products);

        $this->assertCount(1, $this->client->getHistory());

        $lastRequestWithoutBody = $this->client->getLastRequest();
        unset($lastRequestWithoutBody['body']);
        $this->assertEquals(
            [
                'type' => TestHttpClient::REQUEST_TYPE_SYNC,
                'method' => 'PUT',
                'url' => "https://app.patagona.de/api/2/v/contracts/{$contractId}/products",
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode("test@example.com:test"),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ],
            $lastRequestWithoutBody
        );

        $actualRequestBody = json_decode($this->client->getLastRequest()['body'], true);

        $this->assertEquals($products, $actualRequestBody);
        $this->assertSame(45073939, $actualRequestBody[0]['gtin']);
        $this->assertSame(1.95, $actualRequestBody[0]['referencePrice']);
        $this->assertSame(1.85, $actualRequestBody[0]['minPriceBoundary']);
        $this->assertSame(2.95, $actualRequestBody[0]['maxPriceBoundary']);
        $this->assertSame($expectedTags, $actualRequestBody[1]['tags']);

        $this->assertEquals('9b212093-cdea-4632-94cb-423a59b1977a', $response);
    }

    /**
     * Test fetching empty list of recommended prices from PM API
     *
     * @throws Exception
     */
    public function testExportProductsEmptyResponse()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(202),
        ]);

        $exception = null;

        try {
            $this->proxy->exportProducts('test_123', []);
        } catch (\Exception $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertCount(1, $this->client->getHistory());
    }
}
