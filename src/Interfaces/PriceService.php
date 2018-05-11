<?php

namespace Patagona\Pricemonitor\Core\Interfaces;


interface PriceService
{
    /**
     * Updates integration system prices based on a Pricemonitor price list
     *
     * @param string $contractId Pricemonitor account contract id that is a source of a given price list
     * @param array $priceList List of PM recommended prices. Each price in a list has fields:
     *  - identifier: Product id in shop (same value sent via productId when exporting products to PM)
     *  - recommendedPrice: New PM recommended price for a product
     *  - gtin: Product gtin
     *  - currency: Price currency in a ISO 4217 code (3 letter currency code, EUR, USD, BGP...)
     * 
     * @return array List of errors if any. Each error in a list will be in format:
     * ['productId' => identifier, errors => ['error message1', 'error message 2', ...]]
     */
    public function updatePrices($contractId, $priceList);
}