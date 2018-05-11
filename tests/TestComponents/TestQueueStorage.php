<?php

namespace Patagona\Pricemonitor\Core\Tests\TestComponents;

use Patagona\Pricemonitor\Core\Interfaces\Queue\Storage;
use Patagona\Pricemonitor\Core\Sync\Queue\StorageModel;

class TestQueueStorage implements Storage
{
    /**
     * Autoincrement id
     *
     * @var int
     */
    private $incrementId = 0;
    
    /**
     * Queue represents database
     *
     * @var array
     */
    public $queue = [];

    /**
     * Delete queue represents deleted queue job from database 
     *
     * @var array
     */
    public $deleteQueue;

    /**
     * Picks first task job from queue and returns StorageModel
     *
     * @param $queueName
     *
     * @return StorageModel
     */
    public function peek($queueName)
    {
        $data = reset($this->queue);
        
        if ($data) {
            return new StorageModel($data);
        }
        
        return null;
    }

    /**
     * Locks and returns queue job
     *
     * @param $queueName
     *
     * @return StorageModel
     */
    public function lock($queueName)
    {
        $data = reset($this->queue);

        if ($data) {
            return new StorageModel($data);
        }

        return null;
    }

    /**
     * Saves queue job
     *
     * @param $queueName
     * @param StorageModel $storageModel
     *
     * @return bool
     */
    public function save($queueName, $storageModel)
    {
        $id = $storageModel->getId();
        if (empty($id)) {
            $id = ++$this->incrementId;
        }

        $storageModel->setId($id);

        $this->queue[$id] = [
            'id' => $id,
            'reservationTime' => $storageModel->getReservationTime(),
            'attempts' => $storageModel->getAttempts(),
            'payload' => $storageModel->getPayload(),
            'queueName' => $queueName,
        ];

        return true;
    }

    /**
     * Deletes queue job
     *
     * @param $queueName
     * @param StorageModel $storageModel
     *
     * @return bool
     */
    public function delete($queueName, $storageModel)
    {
        $queueJobId = $storageModel->getId();
        $this->deleteQueue[$queueJobId] = $this->queue[$queueJobId];
        unset($this->queue[$queueJobId]);
        
        return true;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        // TODO: Implement beginTransaction() method.
    }

    /**
     * Commit the transaction
     */
    public function commit()
    {
        // TODO: Implement commit() method.
    }

    /**
     * Rollback the transaction
     */
    public function rollBack()
    {
        // TODO: Implement rollBack() method.
    }

}