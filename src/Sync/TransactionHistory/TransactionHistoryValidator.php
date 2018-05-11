<?php

namespace Patagona\Pricemonitor\Core\Sync\TransactionHistory;


use Patagona\Pricemonitor\Core\Infrastructure\Logger;

class TransactionHistoryValidator
{
    /**
     * @param string $contractId
     *
     * @throws ValidationException
     */
    public function validateContractId($contractId)
    {
        if (empty($contractId)) {
            $errorMessage =
                "Contract id must not be empty in transaction. Passed value is: {$contractId}.";
            Logger::logError($errorMessage, $contractId);

            throw new ValidationException($errorMessage);
        }
    }

    /**
     * @param $time
     * 
     * @throws ValidationException
     */
    public function validateTime($time)
    {
        if (empty($time) || (!empty($time) && $time > (new \DateTime()))) {
            $errorMessage =
                "Transaction start time can not be in future. Time: {$time}.";
            Logger::logError($errorMessage);

            throw new ValidationException($errorMessage);
        }
    }

    /**
     * @param string $type
     *
     * @throws ValidationException
     */
    public function validateType($type)
    {
        $transactionHistoryPossibleTypes = [
            TransactionHistoryType::IMPORT_PRICES,
            TransactionHistoryType::EXPORT_PRODUCTS
        ];

        if (!in_array($type, $transactionHistoryPossibleTypes)) {
            $possibleTypesRangeText = implode(', ', $transactionHistoryPossibleTypes);
            $errorMessage =
                "Type of transaction master must be value in this set of values: ({$possibleTypesRangeText}). 
                Passed value: {$type} is not valid.";

            Logger::logError($errorMessage);
            throw new ValidationException($errorMessage);
        }
    }

    /**
     * @param int|null $value
     * @param $fieldName
     * 
     * @throws ValidationException
     */
    public function validatePositiveInt($value, $fieldName)
    {
        if (gettype($value) !== 'integer' || $value < 0) {
            $errorMessage =
                "{$fieldName} in must be integer. {$fieldName}: {$value}.";
            Logger::logError($errorMessage);

            throw new ValidationException($errorMessage);
        }
    }
    
    /**
     * @param bool $value
     * @param $fieldName
     *
     * @throws ValidationException
     */
    public function validateBooleanForNonNullValue($value, $fieldName)
    {
        if ($value !== null && gettype($value) !== 'boolean') {
            $errorMessage =
                "{$fieldName} in transaction must be boolean. {$fieldName}: {$value}.";
            Logger::logError($errorMessage);

            throw new ValidationException($errorMessage);
        }
    }

    /**
     * @param $status
     *
     * @throws ValidationException
     */
    public function validateStatus($status)
    {
        $transactionHistoryPossibleStatuses = [
            TransactionHistoryStatus::IN_PROGRESS,
            TransactionHistoryStatus::FINISHED,
            TransactionHistoryStatus::FAILED,
            TransactionHistoryStatus::FILTERED_OUT
        ];

        if (!in_array($status, $transactionHistoryPossibleStatuses)) {
            $possibleStatusesRangeText = implode(', ', $transactionHistoryPossibleStatuses);
            $errorMessage =
                "Status of transaction must be value in this set of values: ({$possibleStatusesRangeText}). 
                Passed value: {$status} is not valid.";

            Logger::logError($errorMessage);
            throw new ValidationException($errorMessage);
        }
    }

    /**
     * @param $transactionRecords
     * @param $contractId
     *
     * @throws TransactionHistoryStorageException
     */
    public function validateArray($transactionRecords, $contractId)
    {
        if (!is_array($transactionRecords)) {
            $errorMessage =
                "Transaction records can not be found for contract id: {$contractId}. Records are not an array.";

            Logger::logError($errorMessage, $contractId);
            throw new TransactionHistoryStorageException($errorMessage);
        }

    }

    public function validateStatusForFinishedTransaction($status, $transactionId)
    {
        if (!in_array($status, [TransactionHistoryStatus::FAILED, TransactionHistoryStatus::FINISHED])) {
            $errorMessage =
                "Status of transaction not valid when transaction finished. 
                Passed value: {$status} is not valid for transactionId: {$transactionId}.";

            Logger::logError($errorMessage);
            throw new ValidationException($errorMessage);
        }
    }

    /**
     * @param TransactionHistoryMaster $savedMasterTransaction
     * @param TransactionHistoryStorageDTO $savedTransactionStorageDTO
     * @param $contractId
     *
     * @throws TransactionHistoryStorageException
     */
    public function validateOnlyMasterTransactionSaved(
        $savedMasterTransaction,
        TransactionHistoryStorageDTO $savedTransactionStorageDTO,
        $contractId
    ) {
        if ($savedMasterTransaction === null ||
            $savedMasterTransaction->getId() === null ||
            !empty($savedTransactionStorageDTO->getTransactionDetails())
        ) {
            $errorMessage = 'Validation failed for saving master transaction. Only master transaction should be saved.';

            Logger::logError($errorMessage, $contractId);
            throw new TransactionHistoryStorageException($errorMessage);
        }
    }

    /**
     * @param TransactionHistoryMaster $savedMasterTransaction
     * @param $savedTransactionDetails
     * @param int $numberOfAllDetailsForSaving
     * @param string $contractId
     * 
     * @throws TransactionHistoryStorageException
     */
    public function validateMasterTransactionWithAllDetailsSaved(
        $savedMasterTransaction,
        $savedTransactionDetails,
        $numberOfAllDetailsForSaving,
        $contractId
    ) {
        if ($savedMasterTransaction === null ||
            $savedMasterTransaction->getId() === null ||
            !is_array($savedTransactionDetails) ||
            $numberOfAllDetailsForSaving !== count($savedTransactionDetails)
        ) {
            $numberOfNotAdded = $numberOfAllDetailsForSaving - count($savedTransactionDetails);
            $errorMessage =
                "Exception thrown in addTransaction method in TransactionHistory.
                Number of not saved transactions {$numberOfNotAdded}.";
            Logger::logError($errorMessage, $contractId);
            throw new TransactionHistoryStorageException($errorMessage);
        }
    }

    /**
     * @param TransactionHistoryDetail[] $transactionHistoryDetails
     * @param $transactionId
     *
     * @throws ValidationException
     */
    public function validateSavedTransactionDetails($transactionHistoryDetails, $transactionId)
    {
        $numberOfInvalidItems = 0;

        foreach ($transactionHistoryDetails as $transactionDetail) {
            if ($transactionDetail->getId() === null) {
                $numberOfInvalidItems++;
            }
        }

        if ($numberOfInvalidItems > 0) {
            throw new ValidationException(
                "{$numberOfInvalidItems} invalid transaction details because 
                they don't have id for transaction with id: {$transactionId}"
            );
        }
    }

    /**
     * @param TransactionHistoryMaster[] $transactionRecords
     * @param $transactionId
     * @param $uniqueIdentifier
     * @param $contractId
     *
     * @throws TransactionHistoryStorageException
     */
    public function validateRecordsFetchedById($transactionRecords, $transactionId, $uniqueIdentifier, $contractId)
    {
        if (!is_array($transactionRecords) ||
            count($transactionRecords) !== 1 ||
            $transactionRecords[0] === null ||
            ($transactionId !== null && $transactionRecords[0]->getId() !== $transactionId) ||
            ($uniqueIdentifier !== null && $transactionRecords[0]->getUniqueIdentifier() !== $uniqueIdentifier)
        ) {
            $errorMessage =
                "Transaction record with id: {$transactionId} can not be found.";
            Logger::logError($errorMessage, $contractId);
            throw new TransactionHistoryStorageException($errorMessage);
        }
    }

    /**
     * @param $transactionId
     * @param $uniqueIdentifier
     * 
     * @throws ValidationException
     */
    public function validateTransactionIdentifiers($transactionId, $uniqueIdentifier)
    {
        if ($transactionId === null && $uniqueIdentifier === null) {
            $errorMessage =
                "Transaction id and uniqueIdentifier can not both be null at the same time when fetching transaction by 
                id or setting transaction details master data. Transaction id: {$transactionId}, unique identifier: {$uniqueIdentifier}.";
            Logger::logError($errorMessage);

            throw new ValidationException($errorMessage);
        }
    }
    
}