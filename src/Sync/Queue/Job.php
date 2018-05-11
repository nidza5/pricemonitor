<?php

namespace Patagona\Pricemonitor\Core\Sync\Queue;

use DateTime;
use Serializable;

abstract class Job implements Serializable
{

    /**
     * @var StorageModel
     */
    private $storageModel;

    /**
     * @return StorageModel
     */
    public function getStorageModel()
    {
        if (!isset($this->storageModel)) {
            $this->storageModel = new StorageModel();
        }

        $this->storageModel->setPayload(serialize($this));

        return $this->storageModel;
    }

    /**
     * @param StorageModel $storageModel
     */
    public function setStorageModel($storageModel)
    {
        $this->storageModel = $storageModel;
    }

    /**
     * Set reservation time to current time and increment number of attempts
     */
    public function reserve()
    {
        $this->storageModel->setAttempts($this->storageModel->getAttempts() + 1);
        $this->storageModel->setReservationTime(new DateTime());
    }

    /**
     * Set reservation time to default value
     */
    public function release()
    {
        $this->storageModel->setReservationTime(null);
    }

    /**
     * Check if task is reserved by checking if reservation time is set and if it's set if expiration time did not run out
     *
     * @param $expiry
     * @return bool
     */
    public function isReserved($expiry)
    {
        $reservationTime = $this->storageModel->getReservationTime();
        $currentTime = new DateTime();
        
        return !empty($reservationTime) && $currentTime->getTimestamp() < ($reservationTime->getTimestamp() + $expiry);
    }

    /**
     * Run queue job execution
     */
    public abstract function execute();

    public abstract function forceFail();

}