<?php

namespace Patagona\Pricemonitor\Core\Sync\TransactionHistory;


use Patagona\Pricemonitor\Core\Infrastructure\Logger;

class TransactionHistoryMaster
{
    /** @var  int|null */
    private $id;
    
    /**
     * Pricemonitor task id, used for linking transaction id and task on Pricemonitor.
     *
     * @var string|null
     */
    private $uniqueIdentifier;
    
    /** @var  string */
    private $contractId;

    /** @var  \DateTime */
    private $time;

    /** @var  string */
    private $type;

    /** @var  string */
    private $status;

    /** @var  string */
    private $note = '';

    /** @var  int|null */
    private $totalCount = 0;

    /** @var  int|null */
    private $successCount = 0;

    /** @var  int|null */
    private $failedCount = 0;
    
    /** @var TransactionHistoryValidator  */
    private $transactionFieldsValidator;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getUniqueIdentifier()
    {
        return $this->uniqueIdentifier;
    }

    /**
     * @param null|string $uniqueIdentifier
     * 
     * @throws ValidationException
     */
    public function setUniqueIdentifier($uniqueIdentifier)
    {
        if ($this->uniqueIdentifier !== null || empty($uniqueIdentifier)) {
            $errorMessage =
                "Unique identifier can not be changed once set, and can not be null. 
                Passed unique identifier: {$uniqueIdentifier}";
            Logger::logError($errorMessage);

            throw new ValidationException($errorMessage);
        }

        $this->uniqueIdentifier = $uniqueIdentifier;
    }
    
    /**
     * @return string
     */
    public function getContractId()
    {
        return $this->contractId;
    }

    /**
     * @return \DateTime
     */
    public function getTime()
    {
        return $this->time;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     * 
     * @throws ValidationException
     */
    public function setStatus($status)
    {
        $this->transactionFieldsValidator->validateStatus($status);
        $this->status = $status;
    }

    /**
     * @return string
     */
    public function getNote()
    {
        return $this->note;
    }

    /**
     * @param string $note
     */
    public function setNote($note)
    {
        $this->note = $note;
    }

    /**
     * @return int
     * 
     * @throws ValidationException
     */
    public function getTotalCount()
    {
        return $this->totalCount;
    }

    /**
     * @param int $totalCount
     * 
     * @throws ValidationException
     */
    public function setTotalCount($totalCount)
    {
        $this->transactionFieldsValidator->validatePositiveInt($totalCount, 'totalCount');
        $this->totalCount = $totalCount;
    }

    /**
     * @return int
     */
    public function getSuccessCount()
    {
        return $this->successCount;
    }

    /**
     * @param int $successCount
     * 
     * @throws ValidationException
     */
    public function setSuccessCount($successCount)
    {
        $this->transactionFieldsValidator->validatePositiveInt($successCount, 'successCount');
        $this->successCount = $successCount;
    }

    /**
     * @return int
     */
    public function getFailedCount()
    {
        return $this->failedCount;
    }

    /**
     * @param int $failedCount
     * 
     * @throws ValidationException
     */
    public function setFailedCount($failedCount)
    {
        $this->transactionFieldsValidator->validatePositiveInt($failedCount, 'failedCount');
        $this->failedCount = $failedCount;
    }

    /**
     * TransactionHistoryMaster constructor.
     *
     * @param string $contractId
     * @param \DateTime $time
     * @param string $type
     * @param string $status
     * @param int|null $id
     * @param string|null $uniqueIdentifier
     * 
     * @throws ValidationException
     */
    public function __construct($contractId, \DateTime $time, $type, $status, $id = null, $uniqueIdentifier = null)
    {
        $this->transactionFieldsValidator = new TransactionHistoryValidator();
        $this->transactionFieldsValidator->validateContractId($contractId);
        $this->transactionFieldsValidator->validateTime($time);
        $this->transactionFieldsValidator->validateType($type);
        $this->transactionFieldsValidator->validateStatus($status);
        
        $this->contractId = $contractId;
        $this->time = $time;
        $this->type = $type;
        $this->status = $status;
        $this->id = $id;
        $this->uniqueIdentifier = $uniqueIdentifier;
    }

}