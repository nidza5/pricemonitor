<?php

namespace Patagona\Pricemonitor\Core\Interfaces;


use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryDetail;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryDetailFilter;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryMaster;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryMasterFilter;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryStorageDTO;

interface TransactionHistoryStorage
{
    /**
     * @param TransactionHistoryMasterFilter $filter
     * 
     * @return TransactionHistoryMaster[]
     */
    public function getTransactionHistoryMaster(TransactionHistoryMasterFilter $filter);

    /**
     * @param string $contractId
     * @param string $type
     * 
     * @return int
     */
    public function getTransactionHistoryMasterCount($contractId, $type);

    /**
     * @param TransactionHistoryDetailFilter $filter
     * 
     * @return TransactionHistoryDetail[]
     */
    public function getTransactionHistoryDetails(TransactionHistoryDetailFilter $filter);

    /**
     * @param int $masterId
     * 
     * @return int
     */
    public function getTransactionHistoryDetailsCount($masterId);

    /**
     * Saves transaction history master and details. If id is set for master or for details UPDATE should be done.
     * If id is null, INSERT of the new transaction should be done. Array od TransactionHistoryStorageDTO should be
     * returned which will contain affected transactions.
     *
     * @param TransactionHistoryMaster $transactionHistoryMaster
     * @param TransactionHistoryDetail[] $transactionHistoryDetails
     * 
     * @return TransactionHistoryStorageDTO[]
     */
    public function saveTransactionHistory(
        TransactionHistoryMaster $transactionHistoryMaster,
        $transactionHistoryDetails = []
    );

    /**
     * @param int $numberOfDays
     * 
     * @return bool
     */
    public function cleanupMaster($numberOfDays);

    /**
     * @param int $numberOfDays
     * @return bool
     */
    public function cleanupDetails($numberOfDays);
}