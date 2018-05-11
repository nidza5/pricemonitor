<?php


namespace Patagona\Pricemonitor\Core\Sync\Filter;


abstract class DataTypes
{
    const STRING_TYPE = 'string';
    const INT_TYPE = 'integer';
    const FLOAT_TYPE = 'double';
    const BOOLEAN_TYPE = 'boolean';
    const DATE_TIME_TYPE = 'DateTime';
    const STRING_ARRAY = 'string[]';
    const INT_ARRAY = 'integer[]';
    const FLOAT_ARRAY = 'double[]';
    const DATE_TIME_ARRAY = 'DateTime[]';
}