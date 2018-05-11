<?php

namespace Patagona\Pricemonitor\Core\Sync\Dashboard;

use DateTime;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryValidator;

class DashboardDTO
{
    /** @var DateTime  */
    private $exportDate;
    
    /** @var  string */
    private $exportState;
    
    /** @var  int */
    private $numberOfExportItemsSuccessful;
    
    /** @var  int */
    private $numberOfExportItemsFailed;
    
    /** @var  DateTime */
    private $importDate;
    
    /** @var  int */
    private $importCount;
    
    /** @var  string */
    private $importState;
    
    /** @var  TransactionHistoryValidator */
    private $transactionHistoryValidator;

    /**
     * @return DateTime
     */
    public function getExportDate()
    {
        return $this->exportDate;
    }

    /**
     * @return string
     */
    public function getExportState()
    {
        return $this->exportState;
    }

    /**
     * @return int
     */
    public function getNumberOfExportItemsSuccessful()
    {
        return $this->numberOfExportItemsSuccessful;
    }

    /**
     * @return int
     */
    public function getNumberOfExportItemsFailed()
    {
        return $this->numberOfExportItemsFailed;
    }

    /**
     * @return DateTime
     */
    public function getImportDate()
    {
        return $this->importDate;
    }

    /**
     * @return int
     */
    public function getImportCount()
    {
        return $this->importCount;
    }

    /**
     * @return string
     */
    public function getImportState()
    {
        return $this->importState;
    }

    public function __construct(
        DateTime $exportDate = null,
        $exportState = null,
        $numberOfExportItemsSuccessful, 
        $numberOfExportItemsFailed,
        DateTime $importDate = null,
        $importCount,
        $importState
    ) {
        $this->transactionHistoryValidator = new TransactionHistoryValidator();
        if ($exportState !== null) {
            $this->transactionHistoryValidator->validateStatus($exportState);
        }
        if ($importState !== null) {
            $this->transactionHistoryValidator->validateStatus($importState);
        }
        
        $this->exportDate = $exportDate;
        $this->exportState = $exportState;
        $this->numberOfExportItemsSuccessful = $numberOfExportItemsSuccessful;
        $this->numberOfExportItemsFailed = $numberOfExportItemsFailed;
        $this->importDate = $importDate;
        $this->importCount = $importCount;
        $this->importState = $importState;
    }
}