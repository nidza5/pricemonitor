<?php

namespace Patagona\Pricemonitor\Core\Interfaces;

interface MapperService
{
    /**
     * Converts integration specific products to Pricemonitor product format
     *
     * @param string $contractId Pricemonitor contract id
     * @param array $shopProduct Shop products that needs to be converted to Pricemonitor product format
     *
     * @return array Converted Pricemonitor product
     */
    public function convertToPricemonitor($contractId, $shopProduct);
}