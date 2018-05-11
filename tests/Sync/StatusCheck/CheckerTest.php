<?php

namespace Patagona\Pricemonitor\Core\Tests\Sync\StatusCheck;

use Patagona\Pricemonitor\Core\Infrastructure\Logger;
use Patagona\Pricemonitor\Core\Infrastructure\Proxy;
use Patagona\Pricemonitor\Core\Interfaces\HttpClient;
use Patagona\Pricemonitor\Core\Interfaces\Queue\Storage;
use Patagona\Pricemonitor\Core\Interfaces\TransactionHistoryStorage;
use Patagona\Pricemonitor\Core\Sync\Queue\DelayedJob;
use Patagona\Pricemonitor\Core\Sync\StatusCheck\Checker;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Interfaces\LoggerService;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestHttpClient;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestHttpResponse;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestLoggerService;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestQueueStorage;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestTransactionHistory;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestTransactionHistoryStorage;
use PHPUnit\Framework\TestCase;

class CheckerTest extends TestCase
{

    /** @var  TestTransactionHistory */
    protected $transactionHistory;

    /** @var  TestQueueStorage */
    protected $queueStorage;

    /** @var  TestLoggerService */
    protected $loggerService;

    /** @var TestHttpClient */
    protected $client;

    /** @var Proxy */
    protected $proxy;

    protected $priceAPIResponseBody;

    public function setUp()
    {
        parent::setUp();

        $this->queueStorage = new TestQueueStorage();
        $this->loggerService = new TestLoggerService();
        $this->client = new TestHttpClient();
        new ServiceRegister([
            Storage::class => $this->queueStorage,
            HttpClient::class => $this->client,
            TransactionHistoryStorage::class => new TestTransactionHistoryStorage()
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
     * Test get regular export status with succeeded state
     */
    public function testRegularExportStatusWithSucceededState()
    {
        $this->priceAPIResponseBody = $this->getFakeResponseBody("getExportStatus.json");
        
        $contractId = '3p7h3i';
        $taskId = 'e4608054-f6ec-4061-89cb-7534a9933655';

        $this->client->appendMockResponses([
            new TestHttpResponse(200, [], $this->priceAPIResponseBody),
        ]);
        
        $checker = new Checker($this->proxy, $contractId, TestTransactionHistory::getInstance());
        $checker->execute($taskId);

        $this->assertCount(1, $this->client->getHistory());

        $history = $this->client->getHistory();

        $request = $history[0];
        $this->assertContains("/$contractId/", $request['url']);
        $this->assertContains("/$taskId", $request['url']);

        $this->assertCount(0, $this->queueStorage->queue);

        $this->assertEquals('finished', $this->transactionHistory->transactionLastCallParams['status']);
        $this->assertEquals($taskId, $this->transactionHistory->transactionLastCallParams['uniqueIdentifier']);
    }

    /**
     * Test get regular export status with in progress state
     */
    public function testRegularExportStatusWithInProgressState()
    {
        $this->priceAPIResponseBody = $this->getFakeResponseBody("getExportStatusInProgress.json");

        $contractId = '3p7h3i';
        $taskId = 'e4608054-f6ec-4061-89cb-7534a9933655';

        $this->client->appendMockResponses([
            new TestHttpResponse(200, [], $this->priceAPIResponseBody),
        ]);

        $checker = new Checker($this->proxy, $contractId, TestTransactionHistory::getInstance());
        $checker->execute($taskId);

        $this->assertCount(1, $this->queueStorage->queue);
        $queueTop = current($this->queueStorage->queue);
        $this->assertEquals('StatusChecking', $queueTop['queueName']);
        $this->assertContains(DelayedJob::class, $queueTop['payload']);

        $this->assertEquals('finished', $this->transactionHistory->transactionLastCallParams['status']);
    }

    /**
     * Test get regular export status with in progress state
     */
    public function testRegularExportStatusWithSucceededStateAndErrors()
    {
        $this->priceAPIResponseBody = $this->getFakeResponseBody("getExportStatusSucceededWithErrors.json");

        $contractId = '3p7h3i';
        $taskId = 'e4608054-f6ec-4061-89cb-7534a9933655';

        $this->client->appendMockResponses([
            new TestHttpResponse(200, [], $this->priceAPIResponseBody),
        ]);

        $checker = new Checker($this->proxy, $contractId, TestTransactionHistory::getInstance());
        $checker->execute($taskId);
        
        $this->assertCount(3, $this->loggerService->messageLog[Logger::ERROR]);
        $this->assertContains("45073939", $this->loggerService->messageLog[Logger::ERROR][0]);
        $this->assertEquals('finished', $this->transactionHistory->transactionLastCallParams['status']);
    }

}
