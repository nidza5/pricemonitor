<?php

namespace Patagona\Pricemonitor\Core\Sync\Filter;


use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryValidator;

class Filter extends Group
{
    /** @var  string */
    private $type;

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
    
    public function __construct($name, $type, $operator = Operator::OR_OPERATOR)
    {
        parent::__construct($name, $operator);

        (new TransactionHistoryValidator())->validateType($type);
        
        $this->type = $type;
    }

    public function serialize()
    {
        return serialize(
            [
                'type' => $this->type,
                'operator' => $this->operator,
                'expressions' => $this->expressions
            ]
        );
    }

    public function unserialize($serialized)
    {
        $unserialized = unserialize($serialized);
        $this->type = $unserialized['type']; 
        $this->operator = $unserialized['operator']; 
        $this->expressions = $unserialized['expressions'];
    }

}