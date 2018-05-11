<?php

namespace Patagona\Pricemonitor\Core\Tests\Sync\ProductExport;

use Patagona\Pricemonitor\Core\Infrastructure\Logger;
use Patagona\Pricemonitor\Core\Infrastructure\Proxy;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Interfaces\ConfigService;
use Patagona\Pricemonitor\Core\Interfaces\FilterStorage;
use Patagona\Pricemonitor\Core\Interfaces\HttpClient;
use Patagona\Pricemonitor\Core\Interfaces\LoggerService;
use Patagona\Pricemonitor\Core\Interfaces\MapperService;
use Patagona\Pricemonitor\Core\Interfaces\ProductService;
use Patagona\Pricemonitor\Core\Interfaces\Queue\Storage;
use Patagona\Pricemonitor\Core\Interfaces\TransactionHistoryStorage;
use Patagona\Pricemonitor\Core\Sync\ProductExport\Exporter;
use Patagona\Pricemonitor\Core\Sync\Queue\Queue;
use Patagona\Pricemonitor\Core\Sync\StatusCheck\Job;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryDetail;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryMaster;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryType;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestConfigService;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestFilterStorage;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestHttpClient;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestHttpResponse;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestLoggerService;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestMapperService;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestProductService;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestQueueStorage;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestTransactionHistory;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestTransactionHistoryStorage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Patagona\Pricemonitor\Core\Sync\PriceImport\Exporter
 */
class ExporterTest extends TestCase
{
    /** @var  TestProductService */
    protected $productService;

    /** @var  TestMapperService */
    protected $mapperService;

    /** @var  TestConfigService */
    protected $configService;

    /** @var  TestLoggerService */
    protected $loggerService;

    /** @var  TestTransactionHistory */
    protected $transactionHistory;

    /** @var  TestQueueStorage */
    protected $queueStorageService;

    /** @var TestHttpClient */
    protected $client;

    /** @var Proxy */
    protected $proxy;

    protected $productAPIResponseTaskId;
    protected $allRequestProducts;
    protected $badRequestProducts;

    public function setUp()
    {
        parent::setUp();

        $this->productAPIResponseTaskId = '9b212093-cdea-4632-94cb-423a59b1977a';
        $this->client = new TestHttpClient();

        $this->client->appendMockResponses([
            new TestHttpResponse(200, [], $this->getFakeResponseBody("exportProducts.json")),
        ]);

        $this->allRequestProducts = [
            'test_product_1' => [
                'gtin' => '45073939',
                'name' => 'Test product',
                'productId' => '741',
                'referencePrice' => '1.95',
                'minPriceBoundary' => '1.85',
                'maxPriceBoundary' => '2.95',
                'tags' => [
                    [
                        'key' => 'Custom field',
                        'value' => 'test value',
                    ]
                ]
            ],
            'test_product_2' => [
                'gtin' => 45035982,
                'name' => 'Test product 1',
                'productId' => '745',
                'referencePrice' => 2.95,
                'minPriceBoundary' => 2.85,
                'maxPriceBoundary' => 3.95,
                'tags' => [
                    [
                        'key' => 'Custom field',
                        'value' => 'test value',
                    ]
                ]
            ],
            'test_product_missing_productId' => [
                'name' => '0',
                'productId' => '',
                'referencePrice' => 0,
                'minPriceBoundary' => null,
                'maxPriceBoundary' => 0.0,
                'tags' => [
                    [
                        'key' => 'Custom field',
                        'value' => 'test value',
                    ]
                ]
            ],
            'test_product_bad_price_boundary' => [
                'gtin' => 45035982,
                'name' => 'Test product bad price boundary',
                'productId' => '745',
                'referencePrice' => 2.95,
                'minPriceBoundary' => 3.85,
                'maxPriceBoundary' => 2.95
            ],
        ];
        $this->badRequestProducts = [
            'test_product_missing_productId' => [
                'name' => '0',
                'productId' => '',
                'referencePrice' => 0,
                'minPriceBoundary' => null,
                'maxPriceBoundary' => 0.0,
                'tags' => [
                    [
                        'key' => 'Custom field',
                        'value' => 'test value',
                    ]
                ]
            ],
            'test_product_bad_price_boundary' => [
                'gtin' => 45035982,
                'name' => 'Test product bad price boundary',
                'productId' => '745',
                'referencePrice' => 2.95,
                'minPriceBoundary' => 3.85,
                'maxPriceBoundary' => 2.95
            ],
        ];

        $this->productService = new TestProductService();
        $this->mapperService = new TestMapperService();
        $this->configService = new TestConfigService();
        $this->loggerService = new TestLoggerService();
        $this->queueStorageService = new TestQueueStorage();
        new ServiceRegister([
            ProductService::class => $this->productService,
            MapperService::class => $this->mapperService,
            ConfigService::class => $this->configService,
            Storage::class => $this->queueStorageService,
            HttpClient::class => $this->client,
            TransactionHistoryStorage::class => new TestTransactionHistoryStorage(),
            FilterStorage::class => new TestFilterStorage(),
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
     * Test products from shop are sent to Pricemonitor
     */
    public function testExporterShouldSendDataFromProductServiceToPricemonitorProductsEndpoint()
    {
        $contractId = 'my_test_contract_id';

        $shopProducts = [
            ['id' => 'test_product_1'],
            ['id' => 'test_product_2'],
            ['id' => 'test_product_missing_productId'],
            ['id' => 'test_product_bad_price_boundary'],
        ];
        $this->productService->exportProductsResponse = $shopProducts;

        $this->mapperService->convertToPricemonitorResponse = $this->allRequestProducts;

        $transactionHistory = TestTransactionHistory::getInstance();
        $exporter = new Exporter($this->proxy, $contractId, $transactionHistory);
        $transactionId = $transactionHistory->startTransaction($contractId, TransactionHistoryType::EXPORT_PRODUCTS);
        $exporter->execute($transactionId);

        $this->assertCount(1, $this->client->getHistory());

        $history = $this->client->getHistory();

        $request = $history[0];
        $this->assertContains("/$contractId/products", $request['url']);

        $this->assertSame($this->productService->contractId, $contractId, 'Exporter should fetch shop products from product service');

        $this->assertEquals($shopProducts, $this->mapperService->productList, 'Exporter should use mapper to convert shop products');

        $actualRequestBody = json_decode($request['body'], true);
        $this->assertEquals(array_values($this->mapperService->convertToPricemonitorResponse), $actualRequestBody);
    }

    /**
     * Test that export status checking job is created and enqueued
     */
    public function testExporterShouldEnqueueStatusCheckJobWithProperTaskId()
    {
        $this->productService->exportProductsResponse = [['id' => 'test_product_1']];
        $this->mapperService->convertToPricemonitorResponse = [
            'test_product_1' => [
                'gtin' => '45073939',
                'name' => 'Test product',
                'productId' => '741',
                'referencePrice' => '1.95',
                'minPriceBoundary' => '1.85',
                'maxPriceBoundary' => '2.95',
            ],
        ];

        $transactionHistory = TestTransactionHistory::getInstance();
        $exporter = new Exporter($this->proxy, 'test', $transactionHistory);
        $transactionId = $transactionHistory->startTransaction('test', TransactionHistoryType::EXPORT_PRODUCTS);
        $exporter->execute($transactionId);

        $this->assertCount(1, $this->client->getHistory());
        $this->assertCount(1, $this->queueStorageService->queue);

        $queueTop = current($this->queueStorageService->queue);
        $this->assertEquals('StatusChecking', $queueTop['queueName']);
        $this->assertContains($this->productAPIResponseTaskId, $queueTop['payload']);

        $checkerQueue = new Queue('StatusChecking');
        $checkerJob = $checkerQueue->reserve();
        $this->assertInstanceOf(Job::class, $checkerJob);
    }

    /**
     * Test that exporter validates products before sending request to API
     */
    public function testExporterShouldValidateMapperResultBeforeSendingProductToPricemonitor()
    {
        $shopProducts = [
            ['id' => 'test_product_1'],
            ['id' => 'test_product_2'],
            ['id' => 'test_product_missing_productId'],
            ['id' => 'test_product_bad_price_boundary'],
        ];
        $this->productService->exportProductsResponse = $shopProducts;

        $this->mapperService->convertToPricemonitorResponse = $this->allRequestProducts;

        $transactionHistory = TestTransactionHistory::getInstance();
        $exporter = new Exporter($this->proxy, 'test', $transactionHistory);
        $transactionId = $transactionHistory->startTransaction('test', TransactionHistoryType::EXPORT_PRODUCTS);
        $exporter->execute($transactionId);

        $this->assertCount(1, $this->client->getHistory());

        $history = $this->client->getHistory();

        $request = $history[0];
        $actualRequestBody = json_decode($request['body'], true);
        $this->assertEquals(array_values($this->allRequestProducts), $actualRequestBody);

        $product1WithWarningInfo = json_encode($this->badRequestProducts['test_product_missing_productId']);
        $product2WithWarningInfo = json_encode($this->badRequestProducts['test_product_bad_price_boundary']);
        $this->assertEquals(
            [
                "Missing required attributes gtin, productId, minPriceBoundary. Failed to export product {$product1WithWarningInfo}.",
                "Value minPriceBoundary is greater than maxPriceBoundary value. Failed to export product {$product2WithWarningInfo}.",
            ],
            $this->loggerService->messageLog[Logger::WARNING]
        );
    }

    /**
     * Test export data is added to transaction history
     */
    public function testExporterShouldAddExportDataToTransactionHistory()
    {
        $shopProducts = [
            ['id' => 'test_product_1'],
            ['id' => 'test_product_missing_productId'],
            ['id' => 'test_product_bad_price_boundary'],
            ['id' => 'test_product_2']
        ];
        $this->productService->exportProductsResponse = $shopProducts;
        /** @var TransactionHistoryMaster $exportMasterData */
        $exportMasterData = $this->transactionHistory->addedTransactionMasterData[1];
        /** @var TransactionHistoryDetail $exportDetail1 */
        $exportDetail1 = $this->transactionHistory->addedTransactionDetails[1][0];
        /** @var TransactionHistoryDetail $exportDetail3 */
        $exportDetail3 = $this->transactionHistory->addedTransactionDetails[1][2];

        $this->mapperService->convertToPricemonitorResponse = $this->allRequestProducts + $this->badRequestProducts;

        $transactionHistory = TestTransactionHistory::getInstance();
        $exporter = new Exporter($this->proxy, 'test', $transactionHistory);
        $transactionId = $transactionHistory->startTransaction('test', TransactionHistoryType::EXPORT_PRODUCTS);
        $exporter->execute($transactionId);
        
        $this->assertEquals('test', $this->transactionHistory->transactionLastCallParams['contractId']);
        $this->assertEquals($exportMasterData->getType(), TransactionHistoryType::EXPORT_PRODUCTS);
        $this->assertEquals(1, count($this->transactionHistory->addedTransactionMasterData));
        $this->assertArrayHasKey('transactionId', $this->transactionHistory->transactionLastCallParams);
        $this->assertEquals($transactionId, $this->transactionHistory->transactionLastCallParams['transactionId']);
        $this->assertEquals(4, count($this->transactionHistory->addedTransactionDetails[1]));
        $this->assertEquals('Test product', $exportDetail1->getProductName());
        $this->assertEquals(45073939, $exportDetail1->getGtin());
        $this->assertEmpty($exportDetail3->getProductId());
        $this->assertNull($exportDetail3->getMinPrice());
        $this->assertEquals(0, $exportDetail3->getReferencePrice());
        $this->assertEquals(0.0, $exportDetail3->getMaxPrice());
    }

    /**
     * @throws \Exception
     * @expectedException \Exception
     */
    public function testExportShouldFinishWhenProxyThrowsException()
    {
        $this->client->setMockResponses([
            new TestHttpResponse(403),
        ]);

        $shopProducts = [
            ['id' => 'test_product_1'],
            ['id' => 'test_product_missing_productId'],
            ['id' => 'test_product_bad_price_boundary'],
            ['id' => 'test_product_2']
        ];
        $this->productService->exportProductsResponse = $shopProducts;

        $this->mapperService->convertToPricemonitorResponse = $this->allRequestProducts + $this->badRequestProducts;

        try {
            $transactionHistory = TestTransactionHistory::getInstance();
            $exporter = new Exporter($this->proxy, 'test', $transactionHistory);
            $transactionId = $transactionHistory->startTransaction('test', TransactionHistoryType::EXPORT_PRODUCTS);
            $exporter->execute($transactionId);
            $exporter->execute(1);
        } catch (\Exception $ex) {
            /** @var TransactionHistoryMaster $exportMasterData */
            $exportMasterData = $this->transactionHistory->addedTransactionMasterData[1];
            $this->assertEquals('failed', $exportMasterData->getStatus());
            throw $ex;
        }

        $this->fail('Exception should be thrown in case of communication error');
    }

}
