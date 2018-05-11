<?php

namespace Patagona\Pricemonitor\Core\Tests\TestComponents;

use Exception;
use Patagona\Pricemonitor\Core\Sync\Queue\Job;

class TestQueueJob extends Job
{

    public $throwException;

    public $jobType;
    public $jobName;
    public $simulateExpiry;

    public function __construct($jobType = '', $jobName = '', $throwException = false, $simulateExpiry = false)
    {
        $this->jobType = $jobType;
        $this->jobName = $jobName;
        $this->throwException = $throwException;
        $this->simulateExpiry = $simulateExpiry;
    }

    /**
     * String representation of object
     */
    public function serialize()
    {
        return serialize([
            $this->jobType,
            $this->jobName,
            $this->throwException,
            $this->simulateExpiry
        ]);
    } 

    /**
     * Constructs the object
     */
    public function unserialize($serialized)
    {
        list(
            $this->jobType,
            $this->jobName,
            $this->throwException,
            $this->simulateExpiry
            ) = unserialize($serialized);
    }

    public function reserve()
    {
        parent::reserve();

        if ($this->simulateExpiry) {
            $this->getStorageModel()->setReservationTime(new \DateTime('now -1 month'));
        }

    }

    /**
     * Run task execution
     */
    public function execute()
    {
        if ($this->throwException) {
            throw new Exception('Test exception!');
        }

        return true;
    }

    public function forceFail()
    {

    }

}