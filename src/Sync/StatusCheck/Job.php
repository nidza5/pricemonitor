<?php

namespace Patagona\Pricemonitor\Core\Sync\StatusCheck;

use Patagona\Pricemonitor\Core\Infrastructure\Proxy;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Sync\Queue\Job as QueueJob;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistory;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryStatus;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryType;

class Job extends QueueJob
{
    /**
     * @var string Contract id for which product export status will be checked
     */
    private $contractId;
    /**
     * @var string Task id for which product export status will be checked
     */
    private $taskId;

    /** @var TransactionHistory  */
    private $transactionHistory;

    public function __construct($contractId, $taskId)
    {
        $this->contractId = $contractId;
        $this->taskId = $taskId;
        $this->transactionHistory = new TransactionHistory();
    }
    
    /**
     * String representation of object
     *
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        return serialize([$this->contractId, $this->taskId]);
    }

    /**
     * Constructs the object
     *
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        list($this->contractId, $this->taskId) = unserialize($serialized);
    }

    /**
     * Run queue job execution
     */
    public function execute()
    {
        $apiCredentials = ServiceRegister::getConfigService()->getCredentials();
        $checker = new Checker(
            Proxy::createFor($apiCredentials['email'], $apiCredentials['password']), 
            $this->contractId,
            $this->getTransactionHistoryInstance()
        );
        try {
            $checker->execute($this->taskId);
        } catch (\Exception $e) {
            $this->getTransactionHistoryInstance()->finishTransaction(
                $this->contractId,
                TransactionHistoryStatus::FAILED,
                TransactionHistoryType::EXPORT_PRODUCTS,
                null,
                $this->taskId,
                $e->getMessage()
            );
        }
    }

    public function forceFail()
    {
        $this->getTransactionHistoryInstance()->finishTransaction(
            $this->contractId,
            TransactionHistoryStatus::FAILED,
            TransactionHistoryType::EXPORT_PRODUCTS,
            null,
            $this->taskId
        );
    }

    private function getTransactionHistoryInstance()
    {
        if (empty($this->transactionHistory)) {
            return new TransactionHistory();
        }

        return $this->transactionHistory;
    }
}