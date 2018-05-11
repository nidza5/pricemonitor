<?php

namespace Patagona\Pricemonitor\Core\Sync\TransactionHistory;


class TransactionFailedDTO
{
    private $id;

    private $errorMessage = '';
    
    /** @var  string */
    private $status;
    
    private $name;

    private $referencePrice;

    private $minPrice;
    
    private $maxPrice;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * @param string $errorMessage
     * 
     * @throws ValidationException
     */
    public function setErrorMessage($errorMessage)
    {
        $this->validateString($errorMessage);

        $this->errorMessage .= $errorMessage;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * TransactionFailedDTO constructor.
     *
     * @param $id
     * @param string $message
     * @param $status
     * @param $name
     * @param $referencePrice
     * @param $minPrice
     * @param $maxPrice
     * @throws ValidationException
     */
    public function __construct($id, $message, $status, $name, $referencePrice, $minPrice, $maxPrice)
    {
        $this->id = $id;
        $this->errorMessage = $message;
        
        (new TransactionHistoryValidator())->validateStatus($status);
        $this->status = $status;
        $this->name = $name;
        $this->referencePrice = $referencePrice;
        $this->minPrice = $minPrice;
        $this->maxPrice = $maxPrice;
        
    }

    /**
     * @param $errorMessage
     * 
     * @throws ValidationException
     */
    private function validateString($errorMessage)
    {
        if (gettype($errorMessage) !== 'string') {
            throw new ValidationException(
                'Exception thrown in method setErrorMessage in TransactionFailedDTO class. Error message must be string.'
            );
        }
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getReferencePrice()
    {
        return $this->referencePrice;
    }

    /**
     * @return mixed
     */
    public function getMinPrice()
    {
        return $this->minPrice;
    }

    /**
     * @return mixed
     */
    public function getMaxPrice()
    {
        return $this->maxPrice;
    }

}