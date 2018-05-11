<?php

namespace Patagona\Pricemonitor\Core\Sync\Dashboard;

use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistory;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryStorageException;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryType;

class Dashboard
{
    private $transactionHistory;
    
    public function __construct()
    {
        $this->transactionHistory = new TransactionHistory();
    }

    /**
     * @param string $contractId
     * 
     * @return DashboardDTO|null
     * 
     * @throws TransactionHistoryStorageException
     */
    public function getTransactionHistoryMasterLatest($contractId)
    {
        $exportData = $this->transactionHistory->getTransactionHistoryMasterLatest(
            $contractId,  
            TransactionHistoryType::EXPORT_PRODUCTS
        );

        $importData = $this->transactionHistory->getTransactionHistoryMasterLatest(
            $contractId,
            TransactionHistoryType::IMPORT_PRICES
        );
        
        if (empty($exportData) && empty($importData)) {
            return null;
        }
        
        return new DashboardDTO(
            !empty($exportData) ? $exportData->getTime() : null,
            !empty($exportData) ? $exportData->getStatus() : null,
            !empty($exportData) ? $exportData->getSuccessCount() : 0,
            !empty($exportData) ? $exportData->getFailedCount() : 0,
            !empty($importData) ? $importData->getTime() : null,
            !empty($importData) ? $importData->getSuccessCount() : 0,
            !empty($importData) ? $importData->getStatus() : null
        ); 
    }
}