<?php

namespace Patagona\Pricemonitor\Core\Sync\Filter;


abstract class Condition
{
    const EQUAL = 'equal';
    const NOT_EQUAL = 'not_equal';
    const GREATER_THAN = 'greater_than';
    const LESS_THAN = 'less_than';
    const GREATER_OR_EQUAL = 'greater_or_equal';
    const LESS_OR_EQUAL = 'less_or_equal';
    const CONTAINS = 'contains';
    const CONTAINS_NOT = 'contains_not';
    const IN = 'in';
    const NOT_IN = 'not_in';
    const IS = 'is';
    const IS_NOT = 'is_not';
}