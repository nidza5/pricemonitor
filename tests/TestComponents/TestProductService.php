<?php

namespace Patagona\Pricemonitor\Core\Tests\TestComponents;

use Patagona\Pricemonitor\Core\Interfaces\ProductService;
use Patagona\Pricemonitor\Core\Sync\Filter\Filter;

class TestProductService implements ProductService
{
    public $contractId;
    public $exportProductsResponse = [];

    /**
     * Return products to be uploaded to Pricemonitor using integration specific keys. Mapper service will be called with
     * response from this method.
     *
     * @param string $contractId Pricemonitor contract id
     * @param Filter $filter
     * @param array $shopCodes
     * 
     * @return array List of integration  products to be sent to Pricemonitor
     */
    public function exportProducts($contractId, Filter $filter, array $shopCodes = array())
    {
        $this->contractId = $contractId;
        return $this->exportProductsResponse;
    }

    public function getProductIdentifier()
    {
        return 'ProductId';
    }
}