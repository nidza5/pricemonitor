<?php

namespace Patagona\Pricemonitor\Core\Sync\TransactionHistory;


use Patagona\Pricemonitor\Core\Infrastructure\Logger;

class TransactionHistoryMasterFilter extends TransactionHistoryBaseFilter
{
    /** @var  string */
    private $contractId;
    
    /** @var  string */
    private $type;
    
    /** @var string|null  */
    private $uniqueIdentifier;

    /** @var TransactionHistoryValidator  */
    private $transactionFieldsValidator;

    /**
     * @return string
     */
    public function getContractId()
    {
        return $this->contractId;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return int|null
     */
    public function getUniqueIdentifier()
    {
        return $this->uniqueIdentifier;
    }

    /**
     * TransactionHistoryMasterFilter constructor.
     *
     * @param string $contractId
     * @param string $type
     * @param null $id
     * @param null $uniqueIdentifier
     * @param null $limit
     * @param null $offset
     * @param array $orderBy
     * 
     * @throws ValidationException
     */
    public function __construct(
        $contractId,
        $type,
        $id = null,
        $uniqueIdentifier = null,
        $limit = null,
        $offset = null,
        array $orderBy = []
    ) {
        $this->transactionFieldsValidator = new TransactionHistoryValidator();

        $this->transactionFieldsValidator->validateContractId($contractId);
        $this->transactionFieldsValidator->validateType($type);
        $this->validatePaginationParameters($limit, $offset);
        $this->validateIdAndUniqueIdentifierCombinationForMasterTransaction($id, $uniqueIdentifier);
        $this->validateIdentifiersPaginationCombination($id, $uniqueIdentifier, $limit, $offset);
        $this->validateOrderBy(array_keys($orderBy));

        $this->contractId = $contractId;
        $this->type = $type;
        $this->id = $id;
        $this->uniqueIdentifier = $uniqueIdentifier;
        $this->limit = $limit;
        $this->offset = $offset;
        $this->orderBy = $orderBy;
    }
    
    private function validateIdentifiersPaginationCombination($id, $uniqueIdentifier, $limit, $offset)
    {
        if ($id !== null && ($limit !== null || $offset !== null)) {
            $errorMessage =
                "Id and pagination parameters can not be set at the same time. Id: {$id}, limit: {$limit}, offset: {$offset}.";
            Logger::logError($errorMessage);

            throw new ValidationException($errorMessage);
        }

        if ($uniqueIdentifier !== null && ($limit !== null || $offset !== null)) {
            $errorMessage =
                "Unique identifier  and pagination parameters in transaction filters can not be set at the same time. 
                uniqueIdentifier: {$id}, limit: {$limit}, offset: {$offset}.";
            Logger::logError($errorMessage);

            throw new ValidationException($errorMessage);
        }
    }
}