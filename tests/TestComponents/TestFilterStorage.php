<?php

namespace Patagona\Pricemonitor\Core\Tests\TestComponents;


use Patagona\Pricemonitor\Core\Interfaces\FilterStorage;
use Patagona\Pricemonitor\Core\Sync\Filter\Filter;

class TestFilterStorage implements FilterStorage
{
    public $params;
    
    public function getFilter($contractId, $type)
    {
        return serialize(new Filter('testFilter', $type));
    }

    public function saveFilter($contractId, $type, $filter)
    {
        $this->params['contractId'] = $contractId;
        $this->params['type'] = $type;
        $this->params['filter'] = $filter;
    }

}