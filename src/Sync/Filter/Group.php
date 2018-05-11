<?php

namespace Patagona\Pricemonitor\Core\Sync\Filter;


class Group extends ExpressionBase
{
    /** @var  string */
    private $name = '';

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * @param ExpressionBase[] $expressions
     *
     * @return array
     */
    public function setExpressions(array $expressions)
    {
        $this->expressions = $expressions;
    }

    public function __construct($name, $operator = Operator::AND_OPERATOR)
    {
        parent::__construct($operator);

        $this->name = $name;
    }
    
    public function validate()
    {
        $parentError = parent::validate();
        $error = new FilterValidationError();
        $error->addOwnErrors($parentError->getOwnErrors());
        $error->addChildrenErrors($parentError->getChildrenErrors());
        $childIndex = 0;

        foreach ($this->expressions as $expression) {
            $childrenErrors = $expression->validate();

            if (
                (!empty($childrenErrors->getOwnErrors()) && count($childrenErrors->getOwnErrors()) > 0) ||
                (!empty($childrenErrors->getChildrenErrors()) && count($childrenErrors->getChildrenErrors()) > 0)
            ) {
                $error->addChildrenErrors([$childIndex => $childrenErrors]);
            }
            $childIndex++;
        }

        return $error;
    }

    public function serialize()
    {
       return serialize(['name' => $this->name, 'operator' => $this->operator, 'expressions' => $this->expressions]);
    }

    public function unserialize($serialized)
    {
        $unserialized = unserialize($serialized);
        $this->name = $unserialized['name'];
        $this->operator = $unserialized['operator']; 
        $this->expressions = $unserialized['expressions'];
    }

}