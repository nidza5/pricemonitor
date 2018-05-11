<?php

namespace Patagona\Pricemonitor\Core\Sync\TransactionHistory;


class TransactionHistoryStorageDTO
{
    /** @var TransactionHistoryMaster  */
    private $transactionMaster;
    
    /** @var TransactionHistoryDetail[] */
    private $transactionDetails;

    /**
     * TransactionHistoryStorageDTO constructor.
     * 
     * @param TransactionHistoryMaster $transactionMaster
     * @param array $transactionDetails
     */
    public function __construct(TransactionHistoryMaster $transactionMaster, array $transactionDetails)
    {
        $this->transactionMaster = $transactionMaster;
        $this->transactionDetails = $transactionDetails;
    }

    /**
     * @return TransactionHistoryMaster
     */
    public function getTransactionMaster()
    {
        return $this->transactionMaster;
    }

    /**
     * @return TransactionHistoryDetail[]
     */
    public function getTransactionDetails()
    {
        return $this->transactionDetails;
    }
}