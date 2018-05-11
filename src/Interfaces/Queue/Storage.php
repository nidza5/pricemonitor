<?php

namespace Patagona\Pricemonitor\Core\Interfaces\Queue;

use Patagona\Pricemonitor\Core\Sync\Queue\StorageModel;

interface Storage
{

    /**
     * Picks first queue job from queue and returns it as StorageModel instance
     *
     * @param $queueName
     *
     * @return null|StorageModel
     */
    public function peek($queueName);

    /**
     * Locks queue job
     *
     * @param $queueName
     *
     * @return null|StorageModel
     */
    public function lock($queueName);

    /**
     * Saves or updates queue job
     *
     * @param $queueName
     * @param StorageModel $storageModel
     *
     * @return bool
     */
    public function save($queueName, $storageModel);

    /**
     * Deletes queue job
     *
     * @param $queueName
     * @param StorageModel $storageModel
     * 
     * @return bool
     */
    public function delete($queueName, $storageModel);

    /**
     * Begin transaction
     */
    public function beginTransaction();

    /**
     * Commit the transaction
     */
    public function commit();

    /**
     * Rollback the transaction
     */
    public function rollBack();
    
}