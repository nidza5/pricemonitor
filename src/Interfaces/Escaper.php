<?php

namespace Patagona\Pricemonitor\Core\Interfaces;


interface Escaper
{
    /**
     * @param $value
     * @param string|null $type
     *
     * @return mixed Escaped value
     */
    public function escapeValue($value, $type = null);
}