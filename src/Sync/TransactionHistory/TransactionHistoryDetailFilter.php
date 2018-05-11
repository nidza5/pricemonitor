<?php

namespace Patagona\Pricemonitor\Core\Sync\TransactionHistory;


use Patagona\Pricemonitor\Core\Infrastructure\Logger;

class TransactionHistoryDetailFilter extends TransactionHistoryBaseFilter
{
    /** @var  int|null */
    private $masterId;

    /** @var string|null  */
    private $masterUniqueIdentifier;
    
    /** @var string|null */
    private $status;

    /**
     * @return int|null
     */
    public function getMasterId()
    {
        return $this->masterId;
    }

    /**
     * @return null|string
     */
    public function getMasterUniqueIdentifier()
    {
        return $this->masterUniqueIdentifier;
    }

    /**
     * @return null|string
     */
    public function getStatus()
    {
        return $this->status;
    }

    public function __construct(
        $id = null,
        $masterId = null,
        $masterUniqueIdentifier = null,
        $limit = null,
        $offset = null, 
        $status = null, 
        array $orderBy = []
    ) {
        $this->validatePaginationParameters($limit, $offset);
        $this->validateIdAndUniqueIdentifierCombinationForMasterTransaction($masterId, $masterUniqueIdentifier);
        $this->validateIdAndOtherFilters($id, $masterId, $masterUniqueIdentifier, $limit, $offset, $status);
        $this->validateOrderBy(array_keys($orderBy));
        
        if ($status !== null) {
            (new TransactionHistoryValidator())->validateStatus($status);
        }
        
        $this->id = $id;
        $this->masterId = $masterId;
        $this->masterUniqueIdentifier = $masterUniqueIdentifier;
        $this->limit = $limit;
        $this->offset = $offset;
        $this->status = $status;
        $this->orderBy = $orderBy;
    }

    private function validateIdAndOtherFilters($id, $masterId, $masterUniqueIdentifier, $limit, $offset, $status)
    {
        if ($id !== null && 
            ($masterId !== null || $masterUniqueIdentifier !== null || $limit !== null || $offset !== null || $status !== null)
        ) {
            $errorMessage =
                "Id of transaction detail and master transaction identifiers can not be set at the same time. 
                Transaction detail Id: {$id}, master id: {$masterId},
                 masterUniqueIdentifier: {$masterUniqueIdentifier}.";
            Logger::logError($errorMessage);

            throw new ValidationException($errorMessage);
        }
    }

}