<?php

namespace Patagona\Pricemonitor\Core\Tests\Sync\Queue;

use Exception;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Interfaces\Queue\Storage;
use Patagona\Pricemonitor\Core\Sync\Queue\DelayedJob;
use Patagona\Pricemonitor\Core\Sync\Queue\Queue;
use Patagona\Pricemonitor\Core\Sync\Runner\Runner;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestLoggerService;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestQueueStorage;
use PHPUnit\Framework\TestCase;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestQueueJob;

/**
 * @covers \Patagona\Pricemonitor\Core\Sync\Queue\Job
 */
class QueueJobTest extends TestCase
{
    /**
     * @var TestQueueJob
     */
    protected $testQueueJob;

    public function setUp()
    {
        $this->testQueueJob = new TestQueueJob();
    }

    /**
     * Test initial job state
     */
    public function testInitialJobState()
    {
        $storageModel = $this->testQueueJob->getStorageModel();
        $this->assertNull($storageModel->getId());
        $this->assertNull($storageModel->getReservationTime());
        $this->assertSame(0, $storageModel->getAttempts());
        $this->assertFalse($this->testQueueJob->isReserved(3600));
    }
    
    /**
     * Test task queue reservation, release and checking if task queue is reserved
     *
     * @throws Exception
     */
    public function testTaskQueue()
    {
        $storageModel = $this->testQueueJob->getStorageModel();

        // Test if task queue is reserved
        $this->testQueueJob->reserve();
        $this->assertTrue($this->testQueueJob->isReserved(3600));

        // Test if task queue is released
        $this->testQueueJob->release();
        $this->assertNull($storageModel->getReservationTime());
    }

    public function testDelayedTaskJob()
    {
        $queueStorage = new TestQueueStorage();
        new ServiceRegister(
            [Storage::class => $queueStorage],
            [new TestLoggerService()]
        );

        $delayQueue = new Queue('test');

        $jobToDelay = new TestQueueJob();
        $delayQueue->enqueue(new DelayedJob($jobToDelay, 'test', 2));
        $delayQueue->enqueue(new DelayedJob(new TestQueueJob(), 'test', 0));

        (new Runner('test', 1))->run();

        $this->assertCount(1, $queueStorage->queue);
        $queueTop = current($queueStorage->queue);
        $this->assertEquals('test', $queueTop['queueName']);
        $this->assertEquals(0, $queueTop['attempts']);
        $this->assertEmpty($queueTop['reservationTime']);

        (new Runner('test', 3))->run();

        $this->assertCount(0, $queueStorage->queue);
        $this->assertGreaterThanOrEqual(4, count($queueStorage->deleteQueue));
        $this->assertContains(TestQueueJob::class, $queueStorage->deleteQueue[3]['payload']);
        $this->assertEquals(1, $queueStorage->deleteQueue[3]['attempts']);

        $lastDeletedJob = array_pop($queueStorage->deleteQueue);
        $this->assertContains(TestQueueJob::class, $lastDeletedJob['payload']);
        $this->assertEquals(1, $lastDeletedJob['attempts']);
    }

}
