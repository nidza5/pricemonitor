<?php

namespace Patagona\Pricemonitor\Core\Sync\TransactionHistory;


use Patagona\Pricemonitor\Core\Infrastructure\Logger;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Interfaces\TransactionHistoryStorage;

class TransactionHistory
{
    /** @var  TransactionHistoryStorage */
    private $transactionHistoryStorage;

    /** @var  TransactionHistoryValidator */
    private $transactionHistoryValidator;

    public function __construct()
    {
        $this->transactionHistoryStorage = ServiceRegister::getTransactionHistoryStorage();
        $this->transactionHistoryValidator = new TransactionHistoryValidator();
    }

    /**
     * @param $contractId
     * @param $type
     * @param int $limit
     * @param int $offset
     *
     * @return TransactionHistoryMaster[]
     *
     * @throws TransactionHistoryStorageException
     * @throws ValidationException
     */
    public function getTransactionHistoryMaster($contractId, $type, $limit, $offset)
    {
        $filter = new TransactionHistoryMasterFilter(
            $contractId,
            $type,
            null,
            null,
            $limit,
            $offset,
            [TransactionHistorySortFields::DATE_OF_CREATION => 'DESC']
        );

        try {
            /** @var TransactionHistoryMaster[] $transactionHistoryMasterRecords */
            $transactionHistoryMasterRecords = $this->transactionHistoryStorage->getTransactionHistoryMaster($filter);

        } catch (\Exception $e) {
            Logger::logError(
                'Exception thrown in method: getTransactionHistoryMaster with message: ' . $e->getMessage(),
                $contractId
            );
            throw new TransactionHistoryStorageException($e->getMessage(), 0, $e);
        }

        $this->transactionHistoryValidator->validateArray($transactionHistoryMasterRecords, $contractId);

        return $transactionHistoryMasterRecords;
    }

    /**
     * @param $contractId
     * @param $type
     *
     * @return TransactionHistoryMaster
     *
     * @throws TransactionHistoryStorageException
     */
    public function getTransactionHistoryMasterLatest($contractId, $type)
    {
        $filter = new TransactionHistoryMasterFilter(
            $contractId,
            $type,
            null,
            null,
            1,
            null,
            [TransactionHistorySortFields::DATE_OF_CREATION => 'DESC']
        );

        try {
            /** @var TransactionHistoryMaster[] $transactionHistoryMaster */
            $transactionHistoryMaster = $this->transactionHistoryStorage->getTransactionHistoryMaster($filter);

        } catch (\Exception $e) {
            Logger::logError(
                'Exception thrown in method: getTransactionHistoryMasterLatest with message: ' . $e->getMessage(),
                $contractId
            );
            throw new TransactionHistoryStorageException($e->getMessage(), 0, $e);
        }

        $this->transactionHistoryValidator->validateArray($transactionHistoryMaster, $contractId);

        return !empty($transactionHistoryMaster[0]) ? $transactionHistoryMaster[0] : null;
    }

    /**
     * @param string $contractId
     * @param string $type
     *
     * @return int
     *
     * @throws TransactionHistoryStorageException
     */
    public function getTransactionHistoryMasterCount($contractId, $type)
    {
        try {
            /** @var int $transactionHistoryMasterCount */
            $transactionHistoryMasterCount = $this->transactionHistoryStorage->getTransactionHistoryMasterCount(
                $contractId,
                $type
            );

        } catch (\Exception $e) {
            $errorMessage =
                'Exception thrown in method getTransactionHistoryMasterCount. ExceptionMessage:' . $e->getMessage();
            Logger::logError($errorMessage, $contractId);
            throw new TransactionHistoryStorageException($errorMessage, 0, $e);
        }

        $this->transactionHistoryValidator->validatePositiveInt(
            $transactionHistoryMasterCount,
            'transactionHistoryMasterCount'
        );

        return $transactionHistoryMasterCount;
    }


    /**
     * @param string $contractId
     * @param int $masterId
     * @param int $limit
     * @param int $offset
     *
     * @return TransactionHistoryDetail[]
     *
     * @throws TransactionHistoryStorageException
     */
    public function getTransactionHistoryDetails($contractId, $masterId, $limit = null, $offset = null)
    {
        $filter = new TransactionHistoryDetailFilter(
            null,
            $masterId,
            null,
            $limit,
            $offset,
            null,
            [TransactionHistorySortFields::DATE_OF_CREATION => 'DESC']
        );

        try {
            /** @var TransactionHistoryDetail[] $transactionHistoryDetails */
            $transactionHistoryDetails = $this->transactionHistoryStorage->getTransactionHistoryDetails($filter);

        } catch (\Exception $e) {
            $errorMessage =
                'Exception thrown in method getTransactionHistoryDetails. ExceptionMessage:' . $e->getMessage();
            Logger::logError($errorMessage, $contractId);
            throw new TransactionHistoryStorageException($errorMessage, 0, $e);
        }

        $this->transactionHistoryValidator->validateArray($transactionHistoryDetails, $contractId);

        return $this->reformatEmptyGtinDetails($transactionHistoryDetails);
    }

    /**
     * @param TransactionHistoryDetail[] $transactionHistoryDetails
     *
     * @return TransactionHistoryDetail[]
     */
    private function reformatEmptyGtinDetails($transactionHistoryDetails)
    {
        foreach ($transactionHistoryDetails as &$transactionHistoryDetail) {
            if (strpos($transactionHistoryDetail->getGtin(), 'emptyGtin') === 0) {
                $transactionHistoryDetail->setGtin(0);
            }
        }

        return $transactionHistoryDetails;
    }

    /**
     * @param string $contractId
     * @param int $masterId
     *
     * @return int
     *
     * @throws TransactionHistoryStorageException
     */
    public function getTransactionHistoryDetailsCount($contractId, $masterId)
    {
        try {
            /** @var int $transactionHistoryDetailsCount */
            $transactionHistoryDetailsCount = $this->transactionHistoryStorage->getTransactionHistoryDetailsCount(
                $masterId
            );

        } catch (\Exception $e) {
            $errorMessage =
                'Exception thrown in method: getTransactionHistoryDetailsCount. ExceptionMessage:' . $e->getMessage();
            Logger::logError($errorMessage, $contractId);
            throw new TransactionHistoryStorageException($errorMessage, 0, $e);
        }

        $this->transactionHistoryValidator->validatePositiveInt(
            $transactionHistoryDetailsCount,
            '$transactionHistoryDetailsCount'
        );

        return $transactionHistoryDetailsCount;
    }

    /**
     * @param string $contractId
     * @param string $transactionType
     *
     * @return int
     *
     * @throws TransactionHistoryStorageException
     */
    public function startTransaction($contractId, $transactionType)
    {
        /** @var TransactionHistoryMaster $transactionHistoryMaster */
        $transactionHistoryMaster = new TransactionHistoryMaster(
            $contractId,
            new \DateTime(),
            $transactionType,
            TransactionHistoryStatus::IN_PROGRESS,
            null,
            null
        );

        try {
            /** @var TransactionHistoryStorageDTO $savedTransactionStorageDTO */
            $savedTransactionStorageDTO = $this->transactionHistoryStorage->saveTransactionHistory($transactionHistoryMaster);

        } catch (\Exception $e) {
            $errorMessage = 'Exception thrown in method: startTransaction. ExceptionMessage:' . $e->getMessage();
            Logger::logError($errorMessage, $contractId);
            throw new TransactionHistoryStorageException($errorMessage, 0, $e);
        }

        /** @var TransactionHistoryMaster $savedMasterTransaction */
        $savedMasterTransaction = $savedTransactionStorageDTO->getTransactionMaster();

        $this->transactionHistoryValidator->validateOnlyMasterTransactionSaved(
            $savedMasterTransaction,
            $savedTransactionStorageDTO,
            $contractId
        );

        return $savedMasterTransaction->getId();
    }

    /**
     * If transactionId is passed all elements from transactionHistoryDetails will be updated properly. If transactionId
     * is not passed and transactionUniqueIdentifier is passed, all master and details that have that unique identifier 
     * will be updated properly.
     *
     * @param string $contractId
     * @param string $type
     * @param int|null $transactionId
     * @param TransactionHistoryDetail[] $transactionHistoryDetails
     * @param int|null $transactionUniqueIdentifier
     * @param TransactionFailedDTO[] $failedItems
     *
     * @return TransactionHistoryDetail[]
     *
     * @throws TransactionHistoryStorageException
     * @throws ValidationException
     */
    public function updateTransaction(
        $contractId, $type,
        $transactionId = null,
        array $transactionHistoryDetails = [],
        $transactionUniqueIdentifier = null,
        array $failedItems = []
    ) {
        $this->transactionHistoryValidator->validateTransactionIdentifiers(
            $transactionId,
            $transactionUniqueIdentifier
        );

        if ($transactionId !== null) {
            /** @var TransactionHistoryMaster $transactionHistoryMaster */
            $transactionHistoryMaster = $this->getTransactionHistoryMasterById($contractId, $type, $transactionId);
            $transactionHistoryDetailsForSaving = $transactionHistoryDetails;
            $allTransactionsDetailsInProgress = $this->getTransactionHistoryDetailsByFilters(
                $contractId,
                $transactionId,
                null,
                TransactionHistoryStatus::IN_PROGRESS
            );
        } else {
            /** @var TransactionHistoryMaster $transactionHistoryMaster */
            $transactionHistoryMaster = $this->getTransactionHistoryMasterById(
                $contractId,
                $type,
                null,
                $transactionUniqueIdentifier
            );

            $transactionHistoryDetailsForSaving = $this->getTransactionHistoryDetailsByFilters(
                $contractId,
                null,
                $transactionUniqueIdentifier
            );

            $allTransactionsDetailsInProgress = $this->getTransactionHistoryDetailsByFilters(
                $contractId,
                null,
                $transactionUniqueIdentifier,
                TransactionHistoryStatus::IN_PROGRESS
            );
        }

        $transactionHistoryDetailsForSaving = $this->updateTransactionDetailsState(
            $transactionHistoryDetailsForSaving,
            $type,
            $transactionUniqueIdentifier,
            $failedItems
        );

        $transactionHistoryMaster = $this->updateTransactionHistoryMasterState(
            $transactionHistoryMaster,
            $transactionHistoryDetailsForSaving,
            $type,
            $transactionUniqueIdentifier,
            $allTransactionsDetailsInProgress
        );

        return $this->saveTransactionHistory($transactionHistoryMaster, $transactionHistoryDetailsForSaving);
    }

    /**
     * @param string $contractId
     * @param string $transactionStatus
     * @param string $type
     * @param int|null $transactionId
     * @param string|null $uniqueIdentifier
     * @param string|null $note
     *
     * @throws TransactionHistoryStorageException
     * @throws ValidationException
     */
    public function finishTransaction(
        $contractId,
        $transactionStatus,
        $type,
        $transactionId = null,
        $uniqueIdentifier = null,
        $note = ''
    ) {
        $this->transactionHistoryValidator->validateTransactionIdentifiers(
            $transactionId,
            $uniqueIdentifier
        );

        $this->transactionHistoryValidator->validateStatusForFinishedTransaction(
            $transactionStatus,
            $transactionId !== null ? $transactionId : $uniqueIdentifier
        );

        if ($transactionId !== null) {
            /** @var TransactionHistoryMaster $transactionHistoryMaster */
            $transactionHistoryMaster = $this->getTransactionHistoryMasterById($contractId, $type, $transactionId);
        } else {
            /** @var TransactionHistoryMaster $transactionHistoryMaster */
            $transactionHistoryMaster = $this->getTransactionHistoryMasterById(
                $contractId,
                $type,
                null,
                $uniqueIdentifier
            );
        }

        $transactionHistoryMaster->setStatus($transactionStatus);

        if (!empty($note)) {
            $transactionHistoryMaster->setNote($transactionHistoryMaster->getNote() . ' ' . $note . "\n");
        }

        $allTransactionsDetailsInProgress = $this->getTransactionHistoryDetailsByFilters(
            $contractId,
            $transactionHistoryMaster->getId(),
            null,
            TransactionHistoryStatus::IN_PROGRESS
        );

        $transactionHistoryDetails = $this->updateTransactionDetailsWhenMasterTransactionFinished(
            $allTransactionsDetailsInProgress,
            $transactionStatus,
            $transactionHistoryMaster->getNote()
        );

        if ($transactionStatus === TransactionHistoryStatus::FAILED) {
            $transactionHistoryMaster->setFailedCount(
                $transactionHistoryMaster->getFailedCount() + count($transactionHistoryDetails)
            );
        } else {
            $transactionHistoryMaster->setSuccessCount(
                $transactionHistoryMaster->getSuccessCount() + count($transactionHistoryDetails)
            );
        }

        try {
            $this->transactionHistoryStorage->saveTransactionHistory(
                $transactionHistoryMaster,
                $transactionHistoryDetails
            );
        } catch (\Exception $e) {
            $errorMessage = 'Exception thrown in method: finishTransaction. ExceptionMessage:' . $e->getMessage();
            Logger::logError($errorMessage, $contractId);
            throw new TransactionHistoryStorageException($errorMessage, 0, $e);
        }
    }
    
    public function cleanupMaster($numberOfDays)
    {
        $this->transactionHistoryValidator->validatePositiveInt($numberOfDays, 'numberOfDays');
        try {
            $this->transactionHistoryStorage->cleanupMaster($numberOfDays);
        } catch (\Exception $e) {
            $errorMessage = 'Exception thrown in method: cleanupMaster. ExceptionMessage:' . $e->getMessage();
            Logger::logError($errorMessage);
            throw new TransactionHistoryStorageException($errorMessage, 0, $e);
        }
    }
    
    public function cleanupDetails($numberOfDays)
    {
        $this->transactionHistoryValidator->validatePositiveInt($numberOfDays, 'numberOfDays');
        try {
            $this->transactionHistoryStorage->cleanupDetails($numberOfDays);
        } catch (\Exception $e) {
            $errorMessage = 'Exception thrown in method: cleanupDetails. ExceptionMessage:' . $e->getMessage();
            Logger::logError($errorMessage);
            throw new TransactionHistoryStorageException($errorMessage, 0, $e);
        }
    }

    /**
     * @param string $contractId
     * @param string $type
     * @param int|null $transactionHistoryMasterId
     * @param int|null $uniqueIdentifier
     *
     * @return null|TransactionHistoryMaster
     *
     * @throws TransactionHistoryStorageException
     */
    private function getTransactionHistoryMasterById(
        $contractId,
        $type,
        $transactionHistoryMasterId = null,
        $uniqueIdentifier = null
    ) {
        $filter = new TransactionHistoryMasterFilter($contractId, $type, $transactionHistoryMasterId, $uniqueIdentifier);

        try {
            /** @var TransactionHistoryMaster[] $transactionHistoryMasterRecords */
            $transactionHistoryMasterRecords =
                $this->transactionHistoryStorage->getTransactionHistoryMaster($filter);

        } catch (\Exception $e) {
            Logger::logError($e->getMessage(), $contractId);
            throw new TransactionHistoryStorageException($e->getMessage(), 0, $e);
        }

        $this->transactionHistoryValidator->validateRecordsFetchedById(
            $transactionHistoryMasterRecords,
            $transactionHistoryMasterId,
            $uniqueIdentifier,
            $contractId
        );

        return $transactionHistoryMasterRecords[0];
    }

    /**
     * @param TransactionHistoryDetail[] $transactionDetails
     * @param string $type
     * @param string $transactionUniqueIdentifier
     * @param TransactionFailedDTO[] $failedItems
     *
     * @return TransactionHistoryDetail[]
     * @throws ValidationException
     */
    private function updateTransactionDetailsState(
        $transactionDetails,
        $type,
        $transactionUniqueIdentifier,
        $failedItems
    ) {
        /** @var TransactionHistoryDetail $transactionDetail */
        foreach ($transactionDetails as &$transactionDetail) {
            $importFailed = false;

            if (!empty($transactionUniqueIdentifier) && $transactionDetail->getMasterUniqueIdentifier() === null) {
                $transactionDetail->setMasterUniqueIdentifier($transactionUniqueIdentifier);
            }

            $transactionDetailIdentifier = $transactionDetail->getProductId();

            if ($type === TransactionHistoryType::EXPORT_PRODUCTS) {
                $transactionDetailIdentifier = $transactionDetail->getGtin();
            }

            if (empty($transactionDetailIdentifier)) {
                $transactionDetail->setGtin(0);
            }

            /** @var TransactionFailedDTO $failedItem */
            foreach ($failedItems as $failedItem) {
                if ($transactionDetailIdentifier == $failedItem->getId()) {
                    $transactionDetail->setStatus($failedItem->getStatus());
                    $transactionDetail->setNote($failedItem->getErrorMessage());
                    $transactionDetail->setUpdatedInShop(false);
                    $importFailed = true;

                    break;
                }
            }

            if (!$importFailed && $transactionDetail->getId() !== null) {
                $transactionDetail->setUpdatedInShop(true);
                $transactionDetail->setStatus(TransactionHistoryStatus::FINISHED);
            }
        }

        return $transactionDetails;
    }

    /**
     * @param TransactionHistoryMaster $transactionHistoryMaster
     * @param TransactionHistoryDetail[] $transactionDetails
     * @param string $type
     * @param $uniqueIdentifier
     * @param TransactionHistoryDetail[] $allTransactionDetailsInProgress
     *
     * @return TransactionHistoryMaster
     * @throws ValidationException
     */
    private function updateTransactionHistoryMasterState(
        TransactionHistoryMaster $transactionHistoryMaster,
        array $transactionDetails,
        $type,
        $uniqueIdentifier,
        $allTransactionDetailsInProgress
    ) {
        $failedCountBeforeUpdate = $transactionHistoryMaster->getFailedCount();
        $filteredOutCount = 0;

        foreach ($transactionDetails as $transactionDetail) {
            if ($transactionDetail->getId() === null) {
                $transactionHistoryMaster->setTotalCount($transactionHistoryMaster->getTotalCount() + 1);
                $transactionAlreadyCounted = false;
            } else {
                $transactionAlreadyCounted =
                    $this->isTransactionDetailAlreadyCounted($transactionDetail, $allTransactionDetailsInProgress);
            }

            if ($transactionDetail->getStatus() === TransactionHistoryStatus::FINISHED &&
                !$transactionAlreadyCounted
            ) {
                $transactionHistoryMaster->setSuccessCount($transactionHistoryMaster->getSuccessCount() + 1);
            } else if ($transactionDetail->getStatus() === TransactionHistoryStatus::FAILED &&
                !$transactionAlreadyCounted
            ) {
                $transactionHistoryMaster->setFailedCount($transactionHistoryMaster->getFailedCount() + 1);
            } else if ($transactionDetail->getStatus() === TransactionHistoryStatus::FILTERED_OUT &&
                !$transactionAlreadyCounted
            ) {
                $filteredOutCount++;
            }
        }

        if ($failedCountBeforeUpdate < $transactionHistoryMaster->getFailedCount()) {
            $transactionEntityName = ($type === TransactionHistoryType::IMPORT_PRICES) ? 'prices' : 'products';
            $newNote = $transactionHistoryMaster->getFailedCount() . ' of ' .
                $transactionHistoryMaster->getTotalCount() . ' ' . $transactionEntityName  . ' failed.';

            $transactionHistoryMaster->setNote($newNote);
        }
        
        if ($filteredOutCount > 0) {
            $newNote = $filteredOutCount . ' of ' .
                $transactionHistoryMaster->getTotalCount() . ' prices ' . ' filtered out.';
            $transactionHistoryMaster->setNote($newNote);
        }

        if (!empty($uniqueIdentifier) && $transactionHistoryMaster->getUniqueIdentifier() === null) {
            $transactionHistoryMaster->setUniqueIdentifier($uniqueIdentifier);
        }

        return $transactionHistoryMaster;
    }

    /**
     * @param TransactionHistoryDetail $transactionDetail
     * @param TransactionHistoryDetail[] $allTransactionDetailsInProgress
     *
     * @return bool
     */
    private function isTransactionDetailAlreadyCounted($transactionDetail, $allTransactionDetailsInProgress)
    {
        foreach ($allTransactionDetailsInProgress as $savedTransactionDetail) {
            if ($savedTransactionDetail->getId() === $transactionDetail->getId()) {
                return false;
            }
        }

        return true;
    }

    private function saveTransactionHistory(
        TransactionHistoryMaster $transactionHistoryMaster,
        array $transactionHistoryDetails
    ) {
        try {
            /** @var TransactionHistoryStorageDTO $savedTransaction */
            $savedTransaction = $this->transactionHistoryStorage->saveTransactionHistory(
                $transactionHistoryMaster,
                $transactionHistoryDetails
            );

        } catch (\Exception $e) {
            Logger::logError($e->getMessage(), $transactionHistoryMaster->getContractId());
            throw new TransactionHistoryStorageException($e->getMessage(), 0, $e);
        }

        $savedMasterTransaction = $savedTransaction->getTransactionMaster();
        $savedTransactionDetails = $savedTransaction->getTransactionDetails();

        $this->transactionHistoryValidator->validateMasterTransactionWithAllDetailsSaved(
            $savedMasterTransaction,
            $savedTransactionDetails,
            count($transactionHistoryDetails),
            $transactionHistoryMaster->getContractId()
        );

        $this->transactionHistoryValidator->validateSavedTransactionDetails(
            $savedTransactionDetails,
            $savedMasterTransaction->getId()
        );

        return $savedTransactionDetails;
    }

    /**
     * @param string $contractId
     *
     * @param null $masterId
     * @param string $uniqueIdentifier
     * @param string|null $status
     *
     * @return TransactionHistoryDetail[]
     *
     * @throws TransactionHistoryStorageException
     */
    private function getTransactionHistoryDetailsByFilters(
        $contractId,
        $masterId  = null,
        $uniqueIdentifier = null,
        $status = null
    ) {
        if ($status !== null) {
            $filter = new TransactionHistoryDetailFilter(null, $masterId, $uniqueIdentifier, null, null, $status);
        } else {
            $filter = new TransactionHistoryDetailFilter(null, $masterId, $uniqueIdentifier);
        }

        try {
            /** @var TransactionHistoryDetail[] $transactionHistoryMasterRecord */
            $transactionHistoryDetails =
                $this->transactionHistoryStorage->getTransactionHistoryDetails($filter);

        } catch (\Exception $e) {
            Logger::logError($e->getMessage(), $contractId);
            throw new TransactionHistoryStorageException($e->getMessage(), 0, $e);
        }

        $this->transactionHistoryValidator->validateArray($transactionHistoryDetails, $contractId);

        return $transactionHistoryDetails;
    }

    /**
     * @param TransactionHistoryDetail[] $allTransactionsDetailsInProgress
     * @param string $transactionStatus
     * @param string $note
     *
     * @return TransactionHistoryDetail[]
     */
    private function updateTransactionDetailsWhenMasterTransactionFinished(
        $allTransactionsDetailsInProgress,
        $transactionStatus,
        $note
    ) {
        /** @var TransactionHistoryDetail $transactionDetail */
        foreach ($allTransactionsDetailsInProgress as &$transactionDetail) {
            $transactionDetail->setStatus($transactionStatus);
            $transactionDetail->setNote($transactionDetail->getNote() . ' ' .$note);
            $transactionDetail->setUpdatedInShop(true);
            if ($transactionStatus === TransactionHistoryStatus::FAILED ||
                $transactionStatus === TransactionHistoryStatus::FILTERED_OUT
            ) {
                $transactionDetail->setUpdatedInShop(false);
            }
        }

        return $allTransactionsDetailsInProgress;
    }
}