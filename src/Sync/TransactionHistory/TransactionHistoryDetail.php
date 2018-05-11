<?php

namespace Patagona\Pricemonitor\Core\Sync\TransactionHistory;


use Patagona\Pricemonitor\Core\Infrastructure\Logger;

class TransactionHistoryDetail
{

    /** @var  int|null */
    private $id = null;

    /** @var  string */
    private $status;

    /** @var  \DateTime */
    private $time;

    /** @var  int|null */
    private $masterId = null;

    /** @var  string|null */
    private $masterUniqueIdentifier = null;

    /** @var  string|null */
    private $productId = null;

    /** @var  string|null */
    private $gtin = null;

    /** @var  string|null */
    private $productName = null;

    /** @var  string */
    private $note = '';

    /** @var  bool */
    private $updatedInShop = null;

    /** @var  float|null */
    private $referencePrice = null;

    /** @var  float|null */
    private $minPrice = null;

    /** @var  float|null */
    private $maxPrice = null;

    /** @var TransactionHistoryValidator  */
    private $transactionHistoryValidator;

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return \DateTime
     */
    public function getTime()
    {
        return $this->time;
    }

    /**
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->transactionHistoryValidator->validateStatus($status);
        $this->status = $status;
    }

    /**
     * @return int
     */
    public function getMasterId()
    {
        return $this->masterId;
    }

    /**
     * @return string|null
     */
    public function getMasterUniqueIdentifier()
    {
        return $this->masterUniqueIdentifier;
    }

    /**
     * @param null|string $masterUniqueIdentifier
     *
     * @throws ValidationException
     */
    public function setMasterUniqueIdentifier($masterUniqueIdentifier)
    {
        if ($this->masterUniqueIdentifier !== null || empty($masterUniqueIdentifier)) {
            $errorMessage =
                "Unique identifier can not be changed once set, and can not be null. 
                Passed unique identifier: {$masterUniqueIdentifier}";
            Logger::logError($errorMessage);

            throw new ValidationException($errorMessage);
        }

        $this->masterUniqueIdentifier = $masterUniqueIdentifier;
    }

    /**
     * @return string
     */
    public function getProductId()
    {
        return $this->productId;
    }

    /**
     * @return string
     */
    public function getGtin()
    {
        return $this->gtin;
    }

    /**
     * @param string $gtin
     */
    public function setGtin($gtin)
    {
        $this->gtin = $gtin;
    }

    /**
     * @return string
     */
    public function getProductName()
    {
        return $this->productName;
    }

    /**
     * @param null|string $productName
     */
    public function setProductName($productName)
    {
        $this->productName = $productName;
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
     * @return boolean
     */
    public function isUpdatedInShop()
    {
        return $this->updatedInShop;
    }

    /**
     * @param boolean $updatedInShop
     */
    public function setUpdatedInShop($updatedInShop)
    {
        $this->transactionHistoryValidator->validateBooleanForNonNullValue($updatedInShop, 'updatedInShop');
        $this->updatedInShop = $updatedInShop;
    }

    /**
     * @return float
     */
    public function getReferencePrice()
    {
        return $this->referencePrice;
    }

    /**
     * @return float
     */
    public function getMinPrice()
    {
        return $this->minPrice;
    }

    /**
     * @return float
     */
    public function getMaxPrice()
    {
        return $this->maxPrice;
    }


    /**
     * TransactionHistoryDetail constructor.
     *
     * @param string $status
     * @param \DateTime $time
     * @param int|null $id
     * @param int|null $masterId
     * @param string|null $masterUniqueIdentifier
     * @param $productId
     * @param int|null $gtin
     * @param string $productName
     * @param float|null $referencePrice
     * @param float|null $minPrice
     * @param float|null $maxPrice
     *
     * @throws ValidationException
     */
    public function __construct(
        $status,
        \DateTime $time,
        $id = null,
        $masterId = null,
        $masterUniqueIdentifier = null,
        $productId = null,
        $gtin = null,
        $productName = null,
        $referencePrice = null,
        $minPrice = null,
        $maxPrice = null
    ) {
        $this->transactionHistoryValidator = new TransactionHistoryValidator();

        $this->transactionHistoryValidator->validateStatus($status);
        $this->transactionHistoryValidator->validateTransactionIdentifiers($masterId, $masterUniqueIdentifier);

        $this->status = $status;
        $this->time = $time;
        $this->id = $id;
        $this->masterId = $masterId;
        $this->masterUniqueIdentifier = $masterUniqueIdentifier;
        $this->productId = $productId;
        $this->gtin = $gtin;
        $this->productName = $productName;
        $this->referencePrice = $referencePrice;
        $this->minPrice = $minPrice;
        $this->maxPrice = $maxPrice;
    }

}