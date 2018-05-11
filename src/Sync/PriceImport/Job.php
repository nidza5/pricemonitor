<?php

namespace Patagona\Pricemonitor\Core\Sync\PriceImport;

use Patagona\Pricemonitor\Core\Infrastructure\Proxy;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Sync\Queue\Job as QueueJob;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistory;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryStatus;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryType;

class Job extends QueueJob
{
    
    /**
     * Contract id for which price import job will be triggered
     * 
     * var string
     */
    private $contractId;

    /**
     * Id of transaction that is started.(Import or export)
     * 
     * @var int
     */
    private $transactionId;

    /** @var  TransactionHistory */
    private $transactionHistory;
    
    public function __construct($contractId)
    {
        $this->contractId = $contractId;
        $this->transactionHistory = new TransactionHistory();
    }

    /**
     * String representation of object
     * 
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        return serialize([$this->contractId, $this->transactionId]);
    }

    /**
     * Constructs the object
     * 
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        list($this->contractId, $this->transactionId) = unserialize($serialized);
    }

    /**
     * Run queue job execution
     */
    public function execute()
    {
        $apiCredentials = ServiceRegister::getConfigService()->getCredentials();
        $importer = new Importer(
            Proxy::createFor($apiCredentials['email'], $apiCredentials['password']), 
            $this->contractId,
            new TransactionHistory()
        );
        
        $this->transactionId = $this->getTransactionHistoryInstance()->startTransaction(
            $this->contractId, 
            TransactionHistoryType::IMPORT_PRICES
        );
        $importer->execute($this->transactionId);
    }

    public function forceFail()
    {
        $this->getTransactionHistoryInstance()->finishTransaction(
            $this->contractId,
            TransactionHistoryStatus::FAILED,
            TransactionHistoryType::IMPORT_PRICES,
            $this->transactionId
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