<?php

namespace Patagona\Pricemonitor\Core\Tests\TestComponents;

use Patagona\Pricemonitor\Core\Interfaces\MapperService;

class TestMapperService implements MapperService
{
    public $contractId;
    public $productList = [];
    public $convertToPricemonitorResponse = [];

    /**
     * Converts integration specific products to Pricemonitor product format
     *
     * @param string $contractId Pricemonitor contract id
     * @param array $shopProduct Shop products that needs to be converted to Pricemonitor product format
     *
     * @return array Converted Pricemonitor product
     */
    public function convertToPricemonitor($contractId, $shopProduct)
    {
        $this->contractId = $contractId;
        $this->productList[] = $shopProduct;

        if (empty($this->convertToPricemonitorResponse[$shopProduct['id']])) {
            return [];
        }

        return $this->convertToPricemonitorResponse[$shopProduct['id']];
    }
}