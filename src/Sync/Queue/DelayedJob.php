<?php

namespace Patagona\Pricemonitor\Core\Sync\Queue;

use Patagona\Pricemonitor\Core\Interfaces\SystemJob;

class DelayedJob extends Job implements SystemJob
{
    /** @var \Patagona\Pricemonitor\Core\Sync\Queue\Job Job to delay */
    private $jobToDelay;

    /** @var string Queue to use for job delaying */
    private $delayQueueName;

    /** @var \DateTime Date of job creation */
    private $createdAt;

    /** @var int Delay task execution period in seconds. Set to 0 for immediate execution. */
    private $delayPeriod;

    /**
     * DelayedJob constructor.
     *
     * @param \Patagona\Pricemonitor\Core\Sync\Queue\Job $jobToDelay
     * @param string $delayQueueName Queue name to use for job delaying
     * @param int $delayPeriod Delay period of in seconds. Default is 0 (no delay)
     */
    public function __construct(Job $jobToDelay, $delayQueueName, $delayPeriod = 0)
    {
        $this->jobToDelay = $jobToDelay;
        $this->delayQueueName = $delayQueueName;
        $this->createdAt = new \DateTime();
        $this->delayPeriod = $delayPeriod;
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        return serialize([
            'jobToDelay' => serialize($this->jobToDelay),
            'delayQueueName' => $this->delayQueueName,
            'createdAt' => $this->createdAt->getTimestamp(),
            'delayPeriod' => $this->delayPeriod,
        ]);
    }

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     *
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     *
     * @return void
     * @since 5.1.0
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->jobToDelay = unserialize($data['jobToDelay']);
        $this->delayQueueName = $data['delayQueueName'];
        $this->createdAt = new \DateTime("@{$data['createdAt']}");
        $this->delayPeriod = $data['delayPeriod'];
    }

    /**
     * Run queue job execution
     */
    public function execute()
    {
        if ($this->shouldDelayExecution()) {
            $this->delayExecution();
            return;
        }

        $delayQueue = new Queue($this->delayQueueName);
        $delayQueue->enqueue($this->jobToDelay);
    }

    public function forceFail()
    {
        // There is nothing to clean up if delayed job is forced failed
    }

    private function shouldDelayExecution()
    {
        $now = new \DateTime();
        return ($now->getTimestamp() < ($this->createdAt->getTimestamp() + $this->delayPeriod));
    }

    private function delayExecution()
    {
        $delayQueue = new Queue($this->delayQueueName);
        $delayQueue->dequeue($this);

        $now = new \DateTime();
        $newDelayPeriod = $this->delayPeriod - ($now->getTimestamp() - $this->createdAt->getTimestamp());
        $delayQueue->enqueue(new self($this->jobToDelay, $this->delayQueueName, $newDelayPeriod));
    }
}