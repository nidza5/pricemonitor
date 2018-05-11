<?php

namespace Patagona\Pricemonitor\Core\Tests\Sync\Queue;

use PHPUnit\Framework\TestCase;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Sync\Queue\Job;
use Patagona\Pricemonitor\Core\Sync\Queue\Queue;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestQueueJob;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestQueueStorage;

/**
 * @covers \Patagona\Pricemonitor\Core\Sync\Queue\Queue
 */
class QueueTest extends TestCase
{

    /**
     * @var TestQueueStorage
     */
    private $queueStorage;

    /**
     * @var Queue
     */
    private $queue;

    public function setUp()
    {
        parent::setUp();
        $this->queueStorage = new TestQueueStorage();
        ServiceRegister::registerQueueStorage($this->queueStorage);
        $this->queue = new Queue();
    }

    /**
     * Test simple enqueue queue jobs
     */
    public function testEnqueueQueueJobs()
    {
        $queueJobTest1 = new TestQueueJob('price', 'ShopwarePriceImport');
        $queueJobTest2 = new TestQueueJob('product', 'ShopwareProductExport');

        $result = $this->queue->enqueue($queueJobTest1);
        $this->assertTrue($result);

        $result = $this->queue->enqueue($queueJobTest2);
        $this->assertTrue($result);

        $this->assertCount(2, $this->queueStorage->queue);
    }

    /**
     * Test reserving queue job
     */
    public function testReserveQueueJob()
    {
        $queueJobTest1 = new TestQueueJob('price', 'ShopwarePriceImport');

        $this->queue->enqueue($queueJobTest1);
        $result = $this->queue->reserve();

        $this->assertInstanceOf(Job::class, $result);
        $this->assertTrue($result->isReserved(Queue::EXPIRATION_TIME));
        $this->assertCount(1, $this->queueStorage->queue);
    }

    /**
     * Test releasing queue job
     */
    public function testReleaseQueueJob()
    {
        $queueJobTest1 = new TestQueueJob('price', 'ShopwarePriceImport');

        $this->queue->enqueue($queueJobTest1);
        $this->queue->reserve();
        $result = $this->queue->release();

        $queueTop = $this->queueStorage->peek($this->queue->getName());
        $this->assertTrue($result);
        $this->assertCount(1, $this->queueStorage->queue);
        $this->assertNull($queueTop->getReservationTime());
        $this->assertEquals(1, $queueTop->getAttempts());
    }

    /**
     * Test dequeue queue job which is reserved
     */
    public function testDequeueQueueJob()
    {
        $queueJobTest1 = new TestQueueJob('price', 'ShopwarePriceImport');
        $queueJobTest2 = new TestQueueJob('product', 'ShopwareProductExport');
        $this->queue->enqueue($queueJobTest1);
        $this->queue->enqueue($queueJobTest2);

        $this->queue->dequeue($queueJobTest1);

        $queueTop = $this->queueStorage->peek($this->queue->getName());
        $queueTopPayload = unserialize($queueTop->getPayload());

        $this->assertCount(1, $this->queueStorage->queue);
        $this->assertEquals('product', $queueTopPayload->jobType);
        $this->assertEquals('ShopwareProductExport', $queueTopPayload->jobName);
    }

    /**
     * Test reserving already reserved queue job
     */
    public function testReservingReservedQueueJob()
    {
        $queueJobTest1 = new TestQueueJob('price', 'ShopwarePriceImport');
        $queueJobTest2 = new TestQueueJob('product', 'ShopwareProductExport');

        $this->queue->enqueue($queueJobTest1);
        $this->queue->enqueue($queueJobTest2);
        $this->queue->reserve();
        $result = $this->queue->reserve();

        $this->assertNull($result);
        $this->assertCount(2, $this->queueStorage->queue);
    }

}
