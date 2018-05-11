<?php

namespace Patagona\Pricemonitor\Core\Sync\Filter;


use Patagona\Pricemonitor\Core\Infrastructure\Logger;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Interfaces\FilterStorage;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryValidator;

class FilterRepository
{
    /** @var  FilterStorage */
    private $filterStorage;

    public function __construct()
    {
        $this->filterStorage = ServiceRegister::getFilterStorage();
    }

    /**
     * @param string $contractId
     * @param Filter $filter
     *
     * @throws \Exception
     */
    public function saveFilter($contractId, Filter $filter)
    {
        $error = $filter->validate();

        if (!empty($error->getChildrenErrors()) || !empty($error->getOwnErrors())) {
            Logger::logError('Filter is not valid. Save can not be done.', $contractId);
            throw new \Exception("Filter for contract {$contractId} is not valid.");
        }

        $this->filterStorage->saveFilter($contractId, $filter->getType(), serialize($filter));
    }

    /**
     * @param string $contractId
     * @param string $type
     *
     * @return Filter|null
     *
     * @throws \Exception
     * @throws \Patagona\Pricemonitor\Core\Sync\TransactionHistory\ValidationException
     */
    public function getFilter($contractId, $type)
    {
        (new TransactionHistoryValidator())->validateType($type);

        $serializedFilter = $this->filterStorage->getFilter($contractId, $type);

        if (empty($serializedFilter)) {
            return null;
        }

        /** @var [] $unserializedFilter */
        $unserializedFilter = unserialize($serializedFilter);

        if (empty($unserializedFilter) || $unserializedFilter->getType() !== $type) {
            Logger::logError('Filter not properly fetched from DB.', $contractId);
            throw new \Exception('Filter not properly fetched from DB.');
        }

        return $unserializedFilter;
    }
}