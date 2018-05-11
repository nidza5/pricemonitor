<?php

namespace Patagona\Pricemonitor\Core\Sync\Filter;


use Serializable;

abstract class ExpressionBase implements Serializable
{
    /** @var  string */
    protected $operator;

    /** @var  ExpressionBase[] */
    protected $expressions = [];

    /**
     * @return string
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * @param string $operator
     */
    public function setOperator($operator)
    {
        $this->operator = $operator;
    }

    /**
     * @return ExpressionBase[]
     */
    public function getExpressions()
    {
        return $this->expressions;
    }
    
    public function __construct($operator)
    {
        $this->operator = $operator;
    }

    /**
     * @return FilterValidationError
     */
    public function validate()
    {
        $error = new FilterValidationError();
        if (!in_array($this->operator, [Operator::AND_OPERATOR, Operator::OR_OPERATOR])) {
            $errorMessage = 'Invalid operator: ' . $this->operator;
            $error->addOwnErrors([$errorMessage]); 
        }
        
        return $error;
    }

    /**
     * @param ExpressionBase $expression
     */
    public function addExpression(ExpressionBase $expression)
    {
        $this->expressions[] = $expression;
    }

    public function removeExpression($index)
    {
        unset($this->expressions[$index]);

        $this->expressions = array_values($this->expressions);
    }

}