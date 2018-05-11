<?php

namespace Patagona\Pricemonitor\Core\Tests\TestComponents;


use Patagona\Pricemonitor\Core\Interfaces\Escaper;

class TestEscaper implements Escaper
{
    public function escapeValue($value, $type = null)
    {
        return $value;
    }

}