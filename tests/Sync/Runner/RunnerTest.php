<?php

namespace Patagona\Pricemonitor\Core\Tests\Sync\Runner;

use DateTime;
use Patagona\Pricemonitor\Core\Interfaces\Queue\Storage;
use Patagona\Pricemonitor\Core\Sync\Queue\Queue;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestLoggerService;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestSystemJob;
use PHPUnit\Framework\TestCase;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Infrastructure\Logger;
use Patagona\Pricemonitor\Core\Sync\Runner\Runner;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestQueueJob;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestQueueStorage;

/**
 * @covers \Patagona\Pricemonitor\Core\Sync\Runner\Runner
 */
class RunnerTest extends TestCase
{

    /**
     * @var Runner
     */
    private $runner;

    /**
     * @var Queue
     */
    private $queue;

    /**
     * @var TestQueueStorage
     */
    private $queueStorage;

    /**
     * @var TestLoggerService
     */
    private $logger;

    public function setUp()
    {
        parent::setUp();
        $this->queueStorage = new TestQueueStorage();
        $this->logger = new TestLoggerService();
        new ServiceRegister(
            [Storage::class => $this->queueStorage],
            [$this->logger]
        );

        $this->runner = new Runner();
        $this->queue = new Queue();
    }


    /**
     * Test run queue jobs execution from queue goes successful
     */
    public function testSuccessfulRun()
    {
        $queueJobTest1 = new TestQueueJob('price', 'ShopwarePriceImport');
        $queueJobTest2 = new TestQueueJob('product', 'ShopwareProductExport');

        $this->queue->enqueue($queueJobTest1);
        $this->queue->enqueue($queueJobTest2);

        $this->runner->run();
        $this->assertCount(0, $this->queueStorage->queue);
    }

    /**
     * Test run queue jobs execution from queue when there is some job reserved
     */
    public function testReservedRun()
    {
        $queueJobTest1 = new TestQueueJob('price', 'ShopwarePriceImport');
        $queueJobTest2 = new TestQueueJob('product', 'ShopwareProductExport');

        $this->queue->enqueue($queueJobTest1);
        $this->queue->enqueue($queueJobTest2);
        $queueJobTest1->reserve();
        $this->queue->enqueue($queueJobTest1);
        $this->queue->enqueue($queueJobTest2);

        $this->runner->run();
        $this->assertCount(2, $this->queueStorage->queue);
    }

    /**
     * Test run queue jobs execution from queue when some job fails
     */
    public function testFailedRun()
    {
        $queueJobTest1 = new TestQueueJob('price', 'ShopwarePriceImport');
        $queueJobTest2 = new TestQueueJob('product', 'ShopwareProductExport', true);

        $this->queue->enqueue($queueJobTest1);
        $this->queue->enqueue($queueJobTest2);

        $this->runner->run();
        $this->assertCount(2, $this->queueStorage->deleteQueue);
        $this->assertSame(1, $this->queueStorage->deleteQueue[$queueJobTest1->getStorageModel()->getId()]['attempts']);
        $this->assertSame(4, $this->queueStorage->deleteQueue[$queueJobTest2->getStorageModel()->getId()]['attempts']);
    }

    public function testJobShouldFailIWithoutExecutionInCaseOfExpiry()
    {
        $queueJobTest = new TestQueueJob('price', 'ShopwarePriceImport', false, true);
        $queueJobTest->getStorageModel()->setAttempts(10);

        $this->queue->enqueue($queueJobTest);

        $this->runner->run();

        $this->assertCount(1, $this->queueStorage->deleteQueue);
        $this->assertSame(11, $this->queueStorage->deleteQueue[$queueJobTest->getStorageModel()->getId()]['attempts']);
    }

    /**
     * Test checking execution time
     */
    public function testExecutionTime()
    {
        $dateTime = new DateTime();
        $result = $this->runner->executionTimeNotExceeded($dateTime->modify('-31 seconds'));
        $this->assertFalse($result);

        $dateTime = new DateTime();
        $result = $this->runner->executionTimeNotExceeded($dateTime->modify('-30 seconds'));
        $this->assertFalse($result);

        $dateTime = new DateTime();
        $result = $this->runner->executionTimeNotExceeded($dateTime->modify('-29 seconds'));
        $this->assertTrue($result);

        $dateTime = new DateTime();
        $result = $this->runner->executionTimeNotExceeded($dateTime->modify('-15 seconds'));
        $this->assertTrue($result);

        $dateTime = new DateTime();
        $result = $this->runner->executionTimeNotExceeded($dateTime);
        $this->assertTrue($result);
    }

    /**
     * Test if runner should skip logging info for jobs which are instance of system job
     */
    public function testSkipLoggingForSystemJobs()
    {
        $queueJobTest1 = new TestQueueJob('price', 'ShopwarePriceImport');
        $storageModel1 = $queueJobTest1->getStorageModel();
        $queueJobTest2 = new TestSystemJob('product', 'ShopwareProductExport', true);

        $this->queue->enqueue($queueJobTest1);
        $this->queue->enqueue($queueJobTest2);

        $this->runner->run();

        $this->assertCount(2, $this->logger->messageLog[Logger::INFO]);
        $this->assertCount(4, $this->logger->messageLog[Logger::ERROR]);
        $this->assertCount(0, $this->logger->messageLog[Logger::WARNING]);

        $this->assertContains(strval($storageModel1->getId()), $this->logger->messageLog[Logger::INFO][0]);
        $this->assertContains(strval($storageModel1->getId()), $this->logger->messageLog[Logger::INFO][1]);
    }
    
}
