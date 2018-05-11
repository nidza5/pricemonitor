<?php

namespace Patagona\Pricemonitor\Core\Tests\Sync\PriceImport;

use Patagona\Pricemonitor\Core\Infrastructure\Logger;
use Patagona\Pricemonitor\Core\Infrastructure\Proxy;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Interfaces\ConfigService;
use Patagona\Pricemonitor\Core\Interfaces\FilterStorage;
use Patagona\Pricemonitor\Core\Interfaces\HttpClient;
use Patagona\Pricemonitor\Core\Interfaces\LoggerService;
use Patagona\Pricemonitor\Core\Interfaces\MapperService;
use Patagona\Pricemonitor\Core\Interfaces\PriceService;
use Patagona\Pricemonitor\Core\Interfaces\ProductService;
use Patagona\Pricemonitor\Core\Interfaces\TransactionHistoryStorage;
use Patagona\Pricemonitor\Core\Sync\PriceImport\Importer;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryDetail;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryMaster;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryStatus;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryType;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestConfigService;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestFilterStorage;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestHttpClient;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestHttpResponse;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestLoggerService;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestMapperService;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestPriceService;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestProductService;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestTransactionHistory;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestTransactionHistoryStorage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Patagona\Pricemonitor\Core\Sync\PriceImport\Importer
 */
class ImporterTest extends TestCase
{
    /** @var  TestPriceService */
    protected $priceService;

    /** @var  TestConfigService */
    protected $configService;

    /** @var  TestLoggerService */
    protected $loggerService;

    /** @var  TestTransactionHistory */
    protected $transactionHistory;

    /** @var TestHttpClient */
    protected $client;

    /** @var Proxy */
    protected $proxy;

    protected $priceAPIResponseBody;
    protected $priceAPIEmptyResponseBody;
    protected $pricesFromAPI;

    public function setUp()
    {
        parent::setUp();

        $this->priceAPIResponseBody = $this->getFakeResponseBody("importPrices.json");
        $this->priceAPIEmptyResponseBody = $this->getFakeResponseBody("importPricesEmpty.json");

        $bodyContent = json_decode($this->priceAPIResponseBody, true);
        $this->pricesFromAPI = array_map(function ($priceRecommendation) {
            $priceRecommendation['currency'] = 'EUR';
            return $priceRecommendation;
        }, $bodyContent['priceRecommendations']);

        $this->priceService = new TestPriceService();
        $this->configService = new TestConfigService();
        $this->loggerService = new TestLoggerService();
        $this->client = new TestHttpClient();
        new ServiceRegister([
            PriceService::class => $this->priceService,
            ConfigService::class => $this->configService,
            HttpClient::class => $this->client,
            TransactionHistoryStorage::class => new TestTransactionHistoryStorage(),
            FilterStorage::class => new TestFilterStorage(),
            ProductService::class => new TestProductService(),
            MapperService::class => new TestMapperService()
        ],[
            LoggerService::class => $this->loggerService,
        ]);

        $this->transactionHistory = TestTransactionHistory::getInstance();
        $this->proxy = Proxy::createFor('test@example.com', 'test');
    }

    protected function getFakeResponseBody($fakeFile)
    {
        return file_get_contents(TESTS_FAKE_API_RESPONSES_DIR . "/$fakeFile");
    }

    /**
     * Test PM API prices are fetched in batches
     */
    public function testImporterShouldImportAllPricesInBatchesAndKeepTrackOfLastImportDate()
    {
        $batchSize = Importer::BATCH_SIZE;
        $contractId = 'my_test_contract_id';
        $lastImportTimestampKey = "contract_{$contractId}.lastPriceImportTimestamp";
        $beforeImportExecutedTimestamp = (new \DateTime())->getTimestamp();

        $this->client->appendMockResponses([
            new TestHttpResponse(200, [], $this->priceAPIResponseBody),
            new TestHttpResponse(200, [], $this->priceAPIResponseBody),
            new TestHttpResponse(200, [], $this->priceAPIEmptyResponseBody),
        ]);

        $transactionHistory = TestTransactionHistory::getInstance();
        $importer = new Importer($this->proxy, $contractId, $transactionHistory);
        $transactionId = $transactionHistory->startTransaction($contractId, TransactionHistoryType::IMPORT_PRICES);
        $importer->execute($transactionId);

        $this->assertCount(3, $this->client->getHistory());

        $history = $this->client->getHistory();

        $request1 = $history[0];
        $this->assertContains("start=0&limit={$batchSize}", $request1['url']);
        $this->assertContains("/$contractId/", $request1['url']);

        $request2 = $history[1];
        $this->assertContains("start=1000&limit={$batchSize}", $request2['url']);

        $request3 = $history[2];
        $this->assertContains("start=2000&limit={$batchSize}", $request3['url']);

        $this->assertNotEmpty($this->configService->get($lastImportTimestampKey));
        $this->assertGreaterThanOrEqual($this->configService->get($lastImportTimestampKey), $beforeImportExecutedTimestamp);
    }

    /**
     * Test PM API prices are limited with date since filter
     */
    public function testImporterShouldImportAllPricesUsingSinceDateFilter()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(200, [], $this->priceAPIResponseBody),
            new TestHttpResponse(200, [], $this->priceAPIResponseBody),
            new TestHttpResponse(200, [], $this->priceAPIEmptyResponseBody),
        ]);

        $sinceDate = new \DateTime();
        $this->configService->set('contract_my_test_contract_id.lastPriceImportTimestamp', $sinceDate->getTimestamp());

        $transactionHistory = TestTransactionHistory::getInstance();
        $importer = new Importer($this->proxy, 'my_test_contract_id', $transactionHistory);
        $transactionId = $transactionHistory->startTransaction('my_test_contract_id', TransactionHistoryType::IMPORT_PRICES);
        try {
            $importer->execute($transactionId);
        } catch (\Exception $e) {

        }

        $this->assertCount(3, $this->client->getHistory());

        $expectedSince = urlencode($sinceDate->setTimezone(new \DateTimeZone('UTC'))->format("Y-m-d\TH:i:s.u\Z"));

        $history = $this->client->getHistory();

        $request1 = $history[0];
        $this->assertContains("since={$expectedSince}", $request1['url']);

        $request2 = $history[1];
        $this->assertContains("since={$expectedSince}", $request2['url']);

        $request3 = $history[2];
        $this->assertContains("since={$expectedSince}", $request3['url']);
    }

    /**
     * Test that price service update method is called with prices from PM API and that errors are logged using logger service
     */
    public function testImporterShouldCallPriceServiceWithPricesFromProxyAndLogErrorsReturnedFromPriceServiceResponse()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(200, [], $this->priceAPIResponseBody),
            new TestHttpResponse(200, [], $this->priceAPIEmptyResponseBody),
        ]);

        $this->priceService->updatePricesResponse = [[
            'productId' => '2',
            'errors' => [
                'Error message for procut with id 2',
                'Other error message for procut with id 2',
            ],
            'status' => TransactionHistoryStatus::FAILED
        ],[
            'productId' => '4',
            'errors' => [
                'Error message for procut with id 1',
                'Other error message for procut with id 1',
            ],
            'status' => TransactionHistoryStatus::FAILED
        ]];

        $transactionHistory = TestTransactionHistory::getInstance();
        $importer = new Importer($this->proxy, 'test', $transactionHistory);
        $transactionId = $transactionHistory->startTransaction('test', TransactionHistoryType::IMPORT_PRICES);
        $importer->execute($transactionId);

        foreach ($this->priceService->priceList as &$price) {
            unset($price['name']);
        }

        $this->assertEquals($this->priceService->priceList, $this->pricesFromAPI);
        $this->assertEquals(
            array_merge(
                $this->priceService->updatePricesResponse[0]['errors'],
                $this->priceService->updatePricesResponse[1]['errors']
            ),
            $this->loggerService->messageLog[Logger::ERROR]
        );
    }

    /**
     * Test that transactionHistory methods are called with proper arguments
     */
    public function testImporterExecuteShouldInvokeTransactionHistory()
    {
        $this->client->appendMockResponses([
            new TestHttpResponse(200, [], $this->priceAPIResponseBody),
            new TestHttpResponse(200, [], $this->priceAPIResponseBody),
            new TestHttpResponse(200, [], $this->priceAPIEmptyResponseBody),
        ]);

        $this->priceService->updatePricesResponse = [[
            'productId' => '2',
            'errors' => [
                'Error message for procut with id 2',
                'Other error message for procut with id 2',
            ],
            'status' => TransactionHistoryStatus::FAILED
        ],[
            'productId' => '4',
            'errors' => [
                'Error message for procut with id 1',
                'Other error message for procut with id 1',
            ],
            'status' => TransactionHistoryStatus::FAILED
        ]];

        $transactionHistory = TestTransactionHistory::getInstance();
        $importer = new Importer($this->proxy, 'test', $transactionHistory);
        $transactionId = $transactionHistory->startTransaction('test', TransactionHistoryType::IMPORT_PRICES);
        $importer->execute($transactionId);

        $this->assertArrayHasKey('contractId', $this->transactionHistory->transactionLastCallParams);
        $this->assertEquals('test', $this->transactionHistory->transactionLastCallParams['contractId']);

        /** @var TransactionHistoryMaster $importMasterData */
        $importMasterData = $this->transactionHistory->addedTransactionMasterData[1];
        $this->assertEquals('finished', $importMasterData->getStatus());
        $this->assertEquals(5, count($this->transactionHistory->addedTransactionDetails[$transactionId]));

        /** @var TransactionHistoryDetail $importDetail1 */
        $importDetail1 = $this->transactionHistory->addedTransactionDetails[1][0];
        $this->assertEquals($transactionId, $importDetail1->getMasterId());
        $this->assertEquals(12345678, $importDetail1->getGtin());
        $this->assertEquals(
            TransactionHistoryType::IMPORT_PRICES,
            $this->transactionHistory->transactionLastCallParams['type']
        );
    }

    /**
     * @throws \Exception
     * @expectedException \Exception
     */
    public function testImportShouldFinishWhenProxyThrowsException()
    {
        $this->client->setMockResponses([
            new TestHttpResponse(403),
        ]);

        try {
            $transactionHistory = TestTransactionHistory::getInstance();
            $importer = new Importer($this->proxy, 'test', $transactionHistory);
            $transactionId = $transactionHistory->startTransaction('test', TransactionHistoryType::IMPORT_PRICES);
            $importer->execute($transactionId);
        } catch (\Exception $ex) {
            /** @var TransactionHistoryMaster $importMasterData */
            $importMasterData = $this->transactionHistory->addedTransactionMasterData[1];
            $this->assertEquals('failed', $importMasterData->getStatus());
            throw $ex;
        }

        $this->fail('Exception should be thrown in case of communication error');
    }
}
