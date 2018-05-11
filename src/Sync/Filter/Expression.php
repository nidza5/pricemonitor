<?php

namespace Patagona\Pricemonitor\Core\Sync\Filter;


use Patagona\Pricemonitor\Core\Infrastructure\Logger;

class Expression extends ExpressionBase
{
    /** @var  string */
    private $field;

    /** @var  string */
    private $condition;

    /** @var  string */
    private $valueType;

    /** @var  string[] */
    private $values;
    
    /** @var  string| null */
    private $alias = null;

    private $primitiveValueTypes = [
        DataTypes::STRING_TYPE,
        DataTypes::INT_TYPE,
        DataTypes::FLOAT_TYPE,
        DataTypes::BOOLEAN_TYPE,
        DataTypes::DATE_TIME_TYPE,
    ];

    private $arrayTypes = [
        DataTypes::STRING_ARRAY,
        DataTypes::INT_ARRAY,
        DataTypes::FLOAT_ARRAY,
        DataTypes::DATE_TIME_ARRAY,
    ];

    private $possibleConditions = [
        Condition::CONTAINS,
        Condition::CONTAINS_NOT,
        Condition::EQUAL,
        Condition::NOT_EQUAL,
        Condition::GREATER_OR_EQUAL,
        Condition::GREATER_THAN,
        Condition::LESS_OR_EQUAL,
        Condition::LESS_THAN,
        Condition::IN,
        Condition::NOT_IN,
        Condition::IS,
        Condition::IS_NOT,
    ];

    /**
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @param string $field
     */
    public function setField($field)
    {
        $this->field = $field;
    }

    /**
     * @return string
     */
    public function getCondition()
    {
        return $this->condition;
    }

    /**
     * @param string $condition
     */
    public function setCondition($condition)
    {
        $this->condition = $condition;
    }

    /**
     * @return string
     */
    public function getValueType()
    {
        return $this->valueType;
    }

    /**
     * @param string $valueType
     */
    public function setValueType($valueType)
    {
        $this->valueType = $valueType;
    }

    public function getValues()
    {
        return $this->values;
    }

    /**
     * @param string[] $values
     */
    public function setValues(array $values)
    {
        $this->values = $values;
    }

    /**
     * @return null|string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @param null|string $alias
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    /**
     * Expression constructor.
     *
     * @param string $field
     * @param string $condition
     * @param string $valueType
     * @param string[] $values
     * @param string $operator
     *
     * @throws \Exception
     */
    public function __construct($field, $condition, $valueType, array $values = [], $operator = Operator::OR_OPERATOR)
    {
        parent::__construct($operator);

        if (empty($field)) {
            $errorMessage = 'Field can not be empty.';
            Logger::logError('$errorMessage');
            throw new \Exception($errorMessage);
        }

        if (!in_array(strtolower($condition), $this->possibleConditions)) {
            $errorMessage = 'Condition value: ' . $condition . ' is not valid.';
            Logger::logError($errorMessage);
            throw new \Exception($errorMessage);
        }

        if (!in_array($valueType, array_merge($this->primitiveValueTypes, $this->arrayTypes))) {
            $errorMessage = 'Value type is not valid: ' . $valueType;
            Logger::logError($errorMessage);
            throw new \Exception($errorMessage);
        }

        $this->field = $field;
        $this->condition = $condition;
        $this->valueType = $valueType;
        $this->values = $this->castValues($values);
    }

    public function getSingleValueType()
    {
        if (strpos($this->valueType, DataTypes::DATE_TIME_TYPE) === 0) {
            return DataTypes::STRING_TYPE;
        }

        $lastIndexForFetchingPrimitiveType = strlen($this->valueType);

        if (strpos($this->valueType, '[') !== false) {
            $lastIndexForFetchingPrimitiveType = strpos($this->valueType, '[');
        }

        return substr($this->valueType, 0, $lastIndexForFetchingPrimitiveType);
    }

    private function castValues($values)
    {
        $typeForCast = $this->getSingleValueType();

        foreach ($values as &$value) {
            if ($typeForCast === DataTypes::STRING_TYPE) {
                $value = (string)$value;
            } else if ($typeForCast === DataTypes::INT_TYPE) {
                $value = (int)$value;
            } else if ($typeForCast === DataTypes::FLOAT_TYPE) {
                $value = (float)$value;
            } else if ($typeForCast === DataTypes::BOOLEAN_TYPE) {
                $value = (bool)$value;
            }
        }

        return $values;
    }

    /**
     * @return mixed
     */
    private function getValueForNonArrayTypes()
    {
        return (count($this->values) > 0) ? reset($this->values) : null;
    }

    /**
     * @return FilterValidationError
     */
    public function validate()
    {
        $error = parent::validate();
        $errorMessages = [];
        
        if (($this->condition === Condition::IS || $this->condition === Condition::IS_NOT)) {
            // If this condition is true don't check other validation rules. Only with this condition values
            // can be an empty array.
            
            if ($this->getValueForNonArrayTypes() !== null) {
                $errorMessages[] = 'Combination of value and condition is not valid. Condition: ' . $this->condition
                    . ' can only be used with null value.';
            }

            $error->addOwnErrors($errorMessages);

            return $error;
        }
        
        if (count($this->values) === 0) {
            $errorMessages[] = 'Values are not valid.';
        }

        if ($this->isWrongConditionWithBooleanType() ||
            $this->isWrongConditionWithNonStringType() ||
            $this->isWrongConditionWithArrayType()
        ) {
            $errorMessages[] = 'Combination of value type and condition is not valid. Type: ' .
                $this->valueType . ', Condition: ' . $this->condition;
        }

        if (!in_array($this->valueType, $this->arrayTypes)) {
            if (count($this->values) !== 1) {
                $errorMessages[] = 'Type: ' . $this->valueType . ' must have only one value.';
            }
        }

        $error->addOwnErrors($errorMessages);

        return $error;
    }

    private function isWrongConditionWithBooleanType()
    {
        return $this->valueType ===  DataTypes::BOOLEAN_TYPE &&
        !in_array($this->condition, [Condition::EQUAL, Condition::NOT_EQUAL]);
    }

    private function isWrongConditionWithNonStringType()
    {
        return $this->valueType !==  DataTypes::STRING_TYPE &&
        ($this->condition === Condition::CONTAINS || $this->condition === Condition::CONTAINS_NOT);
    }

    private function isWrongConditionWithArrayType()
    {
        return in_array($this->valueType, $this->arrayTypes) &&
        ($this->condition !== Condition::IN && $this->condition !== Condition::NOT_IN);
    }

    public function serialize()
    {
        return serialize([
            'operator' => $this->operator,
            'field' => $this->field,
            'condition' => $this->condition,
            'valueType' => $this->valueType,
            'values' => $this->values,
        ]);
    }

    public function unserialize($serialized)
    {
        $unserialized = unserialize($serialized);
        $this->operator = $unserialized['operator'];
        $this->field = $unserialized['field'];
        $this->condition = $unserialized['condition'];
        $this->valueType = $unserialized['valueType'];
        $this->values = $unserialized['values'];
    }

    /**
     * @param ExpressionBase $expression
     * @throws \Exception
     */
    public function addExpression(ExpressionBase $expression)
    {
        throw new \Exception('Simple expression can not contain other expressions.');
    }

    public function removeExpression($index)
    {
        throw new \Exception('Expression can not be removed from simple expression.');
    }
}