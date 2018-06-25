<?php

namespace Patagona\Pricemonitor\Core\Sync\Queue;

use Exception;
use Patagona\Pricemonitor\Core\Infrastructure\Logger;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Interfaces\Queue\Storage;

class Queue
{
    const EXPIRATION_TIME = 3600;

    /**
     * Queue name
     *
     * @var string
     */
    private $queueName;

    /**
     * Database storage
     *
     * @var Storage
     */
    private $storage;

    /**
     * Get queue name
     *
     * @return string
     */
    public function getName()
    {
        return $this->queueName;
    }

    public function __construct($queueName = 'Default')
    {
        $this->queueName = $queueName;
        $this->storage = ServiceRegister::getQueueStorage();
    }

    /**
     * Reserves queue job from top of queue
     *
     * @return null|Job
     */
    public function reserve()
    {
        try {
            $queueJob = $this->getAvailableJob();
            if (!empty($queueJob)) {
                $queueJob = $this->doReserve();
            }
        } catch (Exception $ex) {
            $this->storage->rollBack();
            Logger::logError('Could not reserve task job');
            
            return null;
        }

        return $queueJob;
    }

    /**
     * Releases queue job
     * 
     * @return bool
     */
    public function release()
    {
        try {
            $storageModel = $this->storage->peek($this->queueName);
            $queueJob = $this->instantiateQueueJob($storageModel);
            $queueJob->release();

            $this->storage->beginTransaction();
            $result = $this->storage->save($this->queueName, $queueJob->getStorageModel());
            $this->storage->commit();
        } catch (Exception $ex) {
            $this->storage->rollBack();
            Logger::logError('Could not release queue job');
            
            return false;
        }
        
        return $result;
    }

    /**
     * Add queue job to the queue
     * 
     * @param Job $queueJob
     *
     * @return bool
     */
    public function enqueue($queueJob)
    {
        try {
            $storageModel = $queueJob->getStorageModel();

            $this->storage->beginTransaction();
            $result = $this->storage->save($this->queueName, $storageModel);
            $this->storage->commit();
        } catch (Exception $ex) {
            $this->storage->rollBack();
            Logger::logError('Could not enqueue queue job');

            return false;
        }
        
        return $result;
    }

    /**
     * Delete task queue job from queue
     *
     * @return bool
     */
    public function dequeue(Job $jobToDequeue)
    {
        try {
            $this->storage->beginTransaction();
            $result = $this->storage->delete($this->queueName, $jobToDequeue->getStorageModel());
            $this->storage->commit();
        } catch (Exception $ex) {
            $this->storage->rollBack();
            Logger::logError('Could not dequeue queue job');

            return false;
        }

        return $result;
    }

    /**
     * Get available job from top of the queue if it's not reserved
     *
     * @param bool $lock
     *
     * @return null|Job
     */
    private function getAvailableJob($lock = false)
    {
        if ($lock) {
            $storageModel = $this->storage->lock($this->queueName);
        } else {
            $storageModel = $this->storage->peek($this->queueName);
        }

        if (empty($storageModel)) {
            return null;
        }

        $queueJob = $this->instantiateQueueJob($storageModel);

        if ($queueJob->isReserved(self::EXPIRATION_TIME)) {
            return null;
        }

        return $queueJob;
    }

    /**
     * Instantiate queue job based on storage model
     *
     * @param StorageModel $storageModel
     *
     * @return Job
     */
    private function instantiateQueueJob($storageModel)
    {
        $queueJob = unserialize($storageModel->getPayload());
        $queueJob->setStorageModel($storageModel);

        return $queueJob;
    }

    /**
     * Do actual job reservation
     *
     * return null|Job
     */
    private function doReserve()
    {
        $this->storage->beginTransaction();
        $queueJob = $this->getAvailableJob(true);

        if (empty($queueJob)) {
            $this->storage->rollBack();
            return null;
        }

        // Reserve will set reservation time and increment number of attempts
        $queueJob->reserve();

        $this->storage->save($this->queueName, $queueJob->getStorageModel());
        $this->storage->commit();

        return $queueJob;
    }

}