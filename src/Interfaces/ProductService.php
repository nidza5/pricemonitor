<?php

namespace Patagona\Pricemonitor\Core\Interfaces;

use Patagona\Pricemonitor\Core\Sync\Filter\Filter;

interface ProductService
{
    /**
     * Return products to be uploaded to Pricemonitor using integration specific keys. Mapper service will be called with
     * response from this method.
     *
     * @param string $contractId Pricemonitor contract id
     * @param Filter $filter Filter based on which products for export will be exported
     * @param array $shopCodes
     *
     * @return array List of integration  products to be sent to Pricemonitor
     */
    public function exportProducts($contractId, Filter $filter, array $shopCodes = array());

    /**
     * Gets product identifier field name, that will be used for querying integration storage.
     *
     * @return string
     */
    public function getProductIdentifier();
}