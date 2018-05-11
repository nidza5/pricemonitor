<?php

namespace Patagona\Pricemonitor\Core\Tests\TestComponents;


use Patagona\Pricemonitor\Core\Interfaces\TransactionHistoryStorage;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryDetailFilter;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryMasterFilter;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryDetail;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryMaster;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryStorageDTO;

class TestTransactionHistoryStorage implements TransactionHistoryStorage
{
    public $transactionHistoryAddedMasterData = [];

    public $transactionHistoryAddedDetails = [];
    
    public $cleanupCallStack = [];

    /**
     * @param TransactionHistoryDetailFilter $filter
     * 
     * @return array
     */
    public function getTransactionHistoryDetails(TransactionHistoryDetailFilter $filter)
    {
        $masterId = $filter->getMasterId();
        $status = $filter->getStatus();

        if ($masterId !== null) {
            $transactionDetails =  isset($this->transactionHistoryAddedDetails[$masterId]) ?
                $this->transactionHistoryAddedDetails[$masterId] : [];

            if ($status !== null) {
                $transactionDetailsTemp = $transactionDetails;
                $transactionDetails = [];
                foreach ($transactionDetailsTemp as $transactionDetail) {
                    if ($transactionDetail->getStatus() === $status) {
                        $transactionDetails[] = $transactionDetail;
                    }
                }
            }

            return $transactionDetails;
        }

        $uniqueIdentifier = $filter->getMasterUniqueIdentifier();

        if ($uniqueIdentifier !== null) {
            $transactionDetails = [];
            /** @var TransactionHistoryDetail[] $transactionHistoryAddedDetailsByMasterId */
            foreach ($this->transactionHistoryAddedDetails as $transactionHistoryAddedDetailsByMasterId) {
                /** @var TransactionHistoryDetail $transactionHistoryDetail */
                foreach ($transactionHistoryAddedDetailsByMasterId as $transactionHistoryDetail) {
                    if ($transactionHistoryDetail->getMasterUniqueIdentifier() === $uniqueIdentifier) {
                        if ($status !== null) {
                            if ($transactionHistoryDetail->getStatus() === $status) {
                                $transactionDetails[] = $transactionHistoryDetail;
                            }
                        } else {
                            $transactionDetails[] = $transactionHistoryDetail;
                        }
                    }
                }
            }

            return $transactionDetails;
        }

        return [];
    }

    public function getTransactionHistoryMaster(TransactionHistoryMasterFilter $filter)
    {
        $transactionId = $filter->getId();

        if ($transactionId !== null) {
            return  [$this->transactionHistoryAddedMasterData[$transactionId]];
        }

        $uniqueIdentifier = $filter->getUniqueIdentifier();

        if ($uniqueIdentifier !== null) {
            /** @var TransactionHistoryMaster $transactionMaster */
            foreach ($this->transactionHistoryAddedMasterData as $transactionMaster) {
                if ($transactionMaster->getUniqueIdentifier() === $uniqueIdentifier) {
                    return [$transactionMaster];
                }
            }
        }

        return [];
    }

    public function getTransactionHistoryMasterCount($contractId, $type)
    {
        return count($this->transactionHistoryAddedMasterData);
    }

    /**
     * @param TransactionHistoryMaster $transactionHistoryMaster
     * @param TransactionHistoryDetail[] $transactionHistoryDetails
     * 
     * @return TransactionHistoryStorageDTO[]
     */
    public function saveTransactionHistory(
        TransactionHistoryMaster $transactionHistoryMaster,
        $transactionHistoryDetails = []
    ) {
        $formattedTransactionHistoryMaster = $transactionHistoryMaster;
        $formattedTransactionHistoryDetails = [];

        if ($transactionHistoryMaster->getId() === null) {
            $formattedTransactionHistoryMaster = new TransactionHistoryMaster(
                $transactionHistoryMaster->getContractId(),
                $transactionHistoryMaster->getTime(),
                $transactionHistoryMaster->getType(),
                $transactionHistoryMaster->getStatus(),
                count($this->transactionHistoryAddedMasterData),
                $transactionHistoryMaster->getUniqueIdentifier()
            );

            $formattedTransactionHistoryMaster->setTotalCount($transactionHistoryMaster->getTotalCount());
            $formattedTransactionHistoryMaster->setNote($transactionHistoryMaster->getNote());
            $formattedTransactionHistoryMaster->setFailedCount($transactionHistoryMaster->getFailedCount());
            $formattedTransactionHistoryMaster->setSuccessCount($transactionHistoryMaster->getSuccessCount());
        }

        $this->transactionHistoryAddedMasterData[$formattedTransactionHistoryMaster->getId()] = 
            $formattedTransactionHistoryMaster;

        /** @var TransactionHistoryDetail $transactionHistoryDetail */
        foreach ($transactionHistoryDetails as $transactionHistoryDetail) {
            $formattedTransactionHistoryDetail = $transactionHistoryDetail;
            
            if ($transactionHistoryDetail->getId() === null) {
                /** @var TransactionHistoryDetail $formattedTransactionHistoryDetail */
                $formattedTransactionHistoryDetail = new TransactionHistoryDetail(
                    $transactionHistoryDetail->getStatus(),
                    $transactionHistoryDetail->getTime(),
                    count($formattedTransactionHistoryDetails),
                    $transactionHistoryDetail->getMasterId(),
                    $transactionHistoryDetail->getMasterUniqueIdentifier(),
                    $transactionHistoryDetail->getProductId(),
                    $transactionHistoryDetail->getGtin(),
                    $transactionHistoryDetail->getProductName(),
                    $transactionHistoryDetail->getReferencePrice(),
                    $transactionHistoryDetail->getMinPrice(),
                    $transactionHistoryDetail->getMaxPrice()
                );

                $formattedTransactionHistoryDetail->setUpdatedInShop($transactionHistoryDetail->isUpdatedInShop());
            }

            $formattedTransactionHistoryDetails[] = $formattedTransactionHistoryDetail;

            $this->transactionHistoryAddedDetails[$formattedTransactionHistoryDetail->getMasterId()] =
                $formattedTransactionHistoryDetails;
        }
        
        return new TransactionHistoryStorageDTO($formattedTransactionHistoryMaster, $formattedTransactionHistoryDetails);
    }

    public function getTransactionHistoryDetailsCount($masterId)
    {
       return count($this->transactionHistoryAddedDetails);
    }

    public function cleanupDetails($numberOfDays)
    {
        $this->cleanupCallStack['cleanupDetails'] = $numberOfDays;
    }

    public function cleanupMaster($numberOfDays)
    {
        $this->cleanupCallStack['cleanupMaster'] = $numberOfDays;
    }
}