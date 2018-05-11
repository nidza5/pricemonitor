<?php

namespace Patagona\Pricemonitor\Core\Interfaces;


interface FilterStorage
{
    /**
     * @param string $contractId
     * @param string $type
     * @param string $filter - Serialized Filter object
     */
    public function saveFilter($contractId, $type, $filter);

    /**
     * @param string $contractId
     * @param string $type
     * 
     * @return string serialized Filter object
     */
    public function getFilter($contractId, $type);
}