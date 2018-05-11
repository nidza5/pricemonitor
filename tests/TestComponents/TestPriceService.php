<?php

namespace Patagona\Pricemonitor\Core\Tests\TestComponents;

use Patagona\Pricemonitor\Core\Interfaces\PriceService;

class TestPriceService implements PriceService
{
    public $contractId;
    public $priceList = [];

    public $updatePricesResponse = [];

    public function updatePrices($contractId, $priceList)
    {
        $this->contractId = $contractId;
        $this->priceList += $priceList;

        return $this->updatePricesResponse;
    }
}