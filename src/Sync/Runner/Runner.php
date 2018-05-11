<?php

namespace Patagona\Pricemonitor\Core\Sync\Runner;

use DateTime;
use Exception;
use Patagona\Pricemonitor\Core\Infrastructure\Logger;
use Patagona\Pricemonitor\Core\Interfaces\SystemJob;
use Patagona\Pricemonitor\Core\Sync\Queue\Job;
use Patagona\Pricemonitor\Core\Sync\Queue\Queue;

class Runner
{
    
    const MAX_NUMBER_OF_ATTEMPTS = 4;

    /**
     * Maximal execution time
     *
     * @var int
     */
    private $maxExecutionTime;

    /**
     * Queue name for execution
     *
     * @var string
     */
    private $queueName;

    /**
     * Queue for execution
     *
     * @var Queue
     */
    private $queue;

    public function __construct($queueName = 'Default', $maxExecutionTime = 30)
    {
        $this->queueName = $queueName;
        $this->queue = new Queue($this->queueName);
        $this->maxExecutionTime = $maxExecutionTime;

        $defaultMaxExecutionTime = ini_get('max_execution_time');
        if ($defaultMaxExecutionTime != 0 && $defaultMaxExecutionTime < $maxExecutionTime) {
            set_time_limit($maxExecutionTime);
        }
    }

    /**
     * Run queue job execution from queue
     */
    public function run()
    {
        $startTime = new DateTime();
        while ($this->executionTimeNotExceeded($startTime) && ($queueJob = $this->queue->reserve()) != null) {
            $storageModel = $queueJob->getStorageModel();
            $queueJobInfo = '(Job id: ' . $storageModel->getId() . '; Queue name: ' . $this->queueName . ';)';
            if (!is_a($queueJob, SystemJob::class)) {
                Logger::logInfo(sprintf(
                    'Queue job reserved. Info %s',
                    $queueJobInfo
                ));
            }

            $numberOfAttempts = $storageModel->getAttempts();
            if ($numberOfAttempts > self::MAX_NUMBER_OF_ATTEMPTS) {
                $this->failJob($queueJob, 'Execution time exceeded.');
                continue;
            }

            try {
                $queueJob->execute();
                $this->queue->dequeue($queueJob);
                if (!is_a($queueJob, SystemJob::class)) {
                    Logger::logInfo(sprintf(
                        'Queue job successfully executed. Info %s',
                        $queueJobInfo
                    ));
                }
            } catch (Exception $ex) {
                if ($numberOfAttempts >= self::MAX_NUMBER_OF_ATTEMPTS) {
                    $this->failJob($queueJob, $ex->getMessage());
                    continue;
                }
                
                $this->queue->release();
                Logger::logError(sprintf(
                    'Queue job execution failed. Releasing job to queue for execution retry. Info %s. Original job failure message: %s',
                    $queueJobInfo,
                    $ex->getMessage()
                ));
            }
        }
    }

    /**
     * Dequeue job from queue and log fail message
     * @param \Patagona\Pricemonitor\Core\Sync\Queue\StorageModel $storageModel
     * @param $message
     */
    private function failJob(Job $job, $message)
    {
        $storageModel = $job->getStorageModel();
        $queueJobInfo = '(Job id: ' . $storageModel->getId() . '; Queue name: ' . $this->queueName . ';)';
        $numberOfAttempts = $storageModel->getAttempts();

        $job->forceFail($storageModel->getId());
        $this->queue->dequeue($job);
        Logger::logError(sprintf(
            'Queue job execution failed %s times. Removing job from queue as failed job. Info %s. Original job failure message: %s',
            $numberOfAttempts,
            $queueJobInfo,
            $message
        ));
}
    
    /**
     * Check if execution time is exceeded
     *
     * @param DateTime $dateTime
     * 
     * @return bool
     */
    public function executionTimeNotExceeded($dateTime)
    {
        return time() < ($dateTime->getTimestamp() + $this->maxExecutionTime);
    }

}