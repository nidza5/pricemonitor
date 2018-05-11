<?php

namespace Patagona\Pricemonitor\Core\Sync\Filter;


use Patagona\Pricemonitor\Core\Infrastructure\Logger;
use Patagona\Pricemonitor\Core\Interfaces\Escaper;

class FilterAdapter
{
    /** @var  Escaper */
    private $escaper;

    /**
     * Generates sql clause based on the passed params.
     *
     * @param Filter $filter
     * @param Escaper $escaper
     *
     * @return string
     *
     * @throws \Exception
     */
    public function generateWhereSql(Filter $filter, Escaper $escaper)
    {
        $error = $filter->validate();

        if (count($error->getChildrenErrors()) > 0 || count($error->getOwnErrors())) {
            Logger::logError('Invalid filter');
            throw new \Exception('Invalid filter.');
        }

        $this->escaper = $escaper;
        $expressions = $filter->getExpressions();
        $sqlWhere = $this->generateWhereQuery($expressions);

        return !empty($sqlWhere) ? 'WHERE ' . $sqlWhere : '';
    }

    /**
     * @param ExpressionBase[]|Expression[]|Group[] $expressions
     *
     * @return string
     */
    private function generateWhereQuery($expressions)
    {
        $whereQuery = '';

        foreach ($expressions as $expression) {
            if (!empty($whereQuery)) {
                $whereQuery .= ' ' . $expression->getOperator() . ' ';
            }

            $subExpressions = $expression->getExpressions();

            if (count($subExpressions) === 0) {
                if ($expression instanceof Expression) {
                    $whereQuery .= $this->generateWhereExpression($expression);
                }
            } else {
                $whereQuery .= '(' . $this->generateWhereQuery($subExpressions) . ')';
            }
        }

        return $whereQuery;
    }

    /**
     * @param Expression $expression
     *
     * @return string
     */
    private function generateWhereExpression(Expression $expression)
    {
        $fieldName = $expression->getField();
        $alias = $expression->getAlias();

        if ($alias !== null) {
            $fieldName = $alias . '.' . $fieldName;
        }

        $expressionCondition = $expression->getCondition();
        $fieldValues = $this->formatValuesInFieldValues($expression);
        $fieldValue = null;

        if (in_array($expressionCondition, [Condition::IN, Condition::NOT_IN])) {
            $fieldValue = '(' . implode(', ', $fieldValues) . ')';
        } else {
            $fieldValue = (count($fieldValues) > 0) ? reset($fieldValues) : null;
        }

        return implode(' ', [
                $fieldName,
                $this->generateSqlCondition($expressionCondition),
                $fieldValue !== null ? $fieldValue : 'NULL'
            ]
        );
    }

    /**
     * @param Expression $expression
     *
     * @return int|mixed|string
     */
    private function formatValuesInFieldValues(Expression $expression)
    {
        $fieldValues = $expression->getValues();
        $fieldValueType = $expression->getValueType();
        $singleValueType = $expression->getSingleValueType();
        $expressionCondition = $expression->getCondition();

        foreach ($fieldValues as &$fieldValue) {
            $fieldValue = $this->escaper->escapeValue($fieldValue, $fieldValueType);

            if ($singleValueType === DataTypes::STRING_TYPE) {
                if (in_array($expressionCondition, [Condition::CONTAINS, Condition::CONTAINS_NOT])) {
                    $fieldValue = "'%{$fieldValue}%'";
                } else {
                    $fieldValue = "'" . $fieldValue . "'";
                }
            }

            if ($fieldValueType === DataTypes::BOOLEAN_TYPE) {
                $fieldValue = (int)$fieldValue;
            }
        }

        return $fieldValues;
    }

    private function generateSqlCondition($expressionCondition)
    {
        $conditionsMap = [
            Condition::EQUAL => '=',
            Condition::GREATER_THAN => '>',
            Condition::GREATER_OR_EQUAL => '>=',
            Condition::LESS_THAN => '<',
            Condition::LESS_OR_EQUAL => '<=',
            Condition::NOT_EQUAL => '<>',
            Condition::CONTAINS => 'LIKE',
            Condition::CONTAINS_NOT => 'NOT LIKE',
            Condition::IN => 'IN',
            Condition::NOT_IN => 'NOT IN',
            Condition::IS => 'IS',
            Condition::IS_NOT => 'IS NOT',
        ];

        return $conditionsMap[$expressionCondition];
    }

}