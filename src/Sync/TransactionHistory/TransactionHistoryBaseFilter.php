<?php

namespace Patagona\Pricemonitor\Core\Sync\TransactionHistory;


use Patagona\Pricemonitor\Core\Infrastructure\Logger;

abstract class TransactionHistoryBaseFilter
{
    /** @var  int|null */
    protected $id;

    /** @var  int|null */
    protected $limit;

    /** @var  int|null */
    protected $offset;
    
    /** @var   array */
    protected $orderBy;

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @return mixed
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @return null|string
     */
    public function getOrderBy()
    {
        return $this->orderBy;
    }


    /**
     * @param $limit
     * @param $offset
     *
     * @throws ValidationException
     */
    protected function validatePaginationParameters($limit, $offset)
    {
        $transactionHistoryValidator = new TransactionHistoryValidator();

        if ($limit !== null) {
            $transactionHistoryValidator->validatePositiveInt($limit, 'limit');
        }

        if ($offset !== null) {
            $transactionHistoryValidator->validatePositiveInt($offset, 'offset');
        }
    }

    protected function validateIdAndUniqueIdentifierCombinationForMasterTransaction($id, $uniqueIdentifier)
    {
        if ($id !== null && $uniqueIdentifier !== null) {
            $errorMessage =
                "Transaction id and uniqueIdentifier can not both be set at the same time when fetching transaction.
                 Transaction id: {$id}, unique identifier: {$uniqueIdentifier}.";
            Logger::logError($errorMessage);

            throw new ValidationException($errorMessage);
        }
    }

    protected function validateOrderBy(array $orderByColumns)
    {
        foreach ($orderByColumns as $orderByColumn) {
            if (!in_array($orderByColumn, [ TransactionHistorySortFields::DATE_OF_CREATION])) {
                $errorMessage =
                    "Order column invalid value: " . $orderByColumn;
                Logger::logError($errorMessage);

                throw new ValidationException($errorMessage);
            }
        }
    }
}