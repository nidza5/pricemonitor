<?php


use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Interfaces\LoggerService;
use Patagona\Pricemonitor\Core\Sync\Filter\Condition;
use Patagona\Pricemonitor\Core\Sync\Filter\DataTypes;
use Patagona\Pricemonitor\Core\Sync\Filter\Expression;
use Patagona\Pricemonitor\Core\Sync\Filter\Filter;
use Patagona\Pricemonitor\Core\Sync\Filter\Group;
use Patagona\Pricemonitor\Core\Sync\Filter\Operator;
use Patagona\Pricemonitor\Core\Sync\Filter\FilterAdapter;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryType;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestEscaper;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestLoggerService;
use PHPUnit\Framework\TestCase;

class SqlFilterAdapterTest extends TestCase
{
    /** @var  TestLoggerService */
    private $loggerService;

    /** @var  FilterAdapter */
    private $sqlFilterAdapter;

    public function setUp()
    {
        parent::setUp();

        $this->sqlFilterAdapter = new FilterAdapter();
        $this->loggerService = new TestLoggerService();

        new ServiceRegister([], [LoggerService::class => $this->loggerService,]);
    }
    
    public function testGenerateWhereForValidExpression()
    {
        $expectedResult = 'WHERE name = \'Test\'';
        $expression = new Expression('name', Condition::EQUAL, DataTypes::STRING_TYPE, ['Test'], Operator::AND_OPERATOR);
        $filter = new Filter('filter1', TransactionHistoryType::EXPORT_PRODUCTS);
        $filter->setExpressions([$expression]);

        $result = $this->sqlFilterAdapter->generateWhereSql($filter, new TestEscaper());

        $this->assertEquals($expectedResult, $result);
    }

    public function testGenerateWhereForMultipleValidExpressions()
    {
        $expectedResult = 
            'WHERE name = \'Test\' OR number < 3 AND name2 LIKE \'%Test2%\' AND flag = 1 AND decimal IS NOT NULL OR decimal2 > 5.5 OR range NOT IN (10, 16)';
        $expression1 = new Expression('name', Condition::EQUAL, DataTypes::STRING_TYPE, ['Test'], Operator::AND_OPERATOR);
        $expression2 = new Expression('number', Condition::LESS_THAN, DataTypes::INT_TYPE, [3], Operator::OR_OPERATOR);
        $expression3 = new Expression('name2', Condition::CONTAINS, DataTypes::STRING_TYPE, ['Test2'], Operator::AND_OPERATOR);
        $expression4 = new Expression('flag', Condition::EQUAL, DataTypes::BOOLEAN_TYPE, [true], Operator::AND_OPERATOR);
        $expression5 = new Expression('decimal', Condition::IS_NOT, DataTypes::FLOAT_TYPE, [], Operator::AND_OPERATOR);
        $expression6 = new Expression('decimal2', Condition::GREATER_THAN, DataTypes::FLOAT_TYPE, [5.5], Operator::OR_OPERATOR);
        $expression7 = new Expression('range', Condition::NOT_IN, DataTypes::FLOAT_ARRAY, [10, 16], Operator::OR_OPERATOR);
        $filter = new Filter('filter1', TransactionHistoryType::EXPORT_PRODUCTS);
        $filter->setExpressions([$expression1, $expression2, $expression3, $expression4, $expression5, $expression6, $expression7]);

        $result = $this->sqlFilterAdapter->generateWhereSql($filter, new TestEscaper());

        $this->assertEquals($expectedResult, $result);
    }

    public function testGenerateWhereForMultipleValidGroupsOneLevelDepth()
    {
        $expectedResult =
            'WHERE (name = \'Test\' OR number < 3) OR (name2 LIKE \'%Test2%\' AND flag = 1) AND (decimal IS NOT NULL OR decimal2 > 5.5 OR range NOT IN (10, 16))';
        $expression1 = new Expression('name', Condition::EQUAL, DataTypes::STRING_TYPE, ['Test'], Operator::AND_OPERATOR);
        $expression2 = new Expression('number', Condition::LESS_THAN, DataTypes::INT_TYPE, [3], Operator::OR_OPERATOR);
        $expression3 = new Expression('name2', Condition::CONTAINS, DataTypes::STRING_TYPE, ['Test2'], Operator::AND_OPERATOR);
        $expression4 = new Expression('flag', Condition::EQUAL, DataTypes::BOOLEAN_TYPE, [true], Operator::AND_OPERATOR);
        $expression5 = new Expression('decimal', Condition::IS_NOT, DataTypes::FLOAT_TYPE, [], Operator::AND_OPERATOR);
        $expression6 = new Expression('decimal2', Condition::GREATER_THAN, DataTypes::FLOAT_TYPE, [5.5], Operator::OR_OPERATOR);
        $expression7 = new Expression('range', Condition::NOT_IN, DataTypes::FLOAT_ARRAY, [10, 16], Operator::OR_OPERATOR);
        $filter = new Filter('filter1', TransactionHistoryType::EXPORT_PRODUCTS);
        $group1 = new Group('Group 1');
        $group2 = new Group('Group 2', Operator::OR_OPERATOR);
        $group3 = new Group('Group 3');
        $group1->setExpressions([$expression1, $expression2]);
        $group2->setExpressions([$expression3, $expression4]);
        $group3->setExpressions([$expression5, $expression6, $expression7]);
        $filter->setExpressions([$group1, $group2, $group3]);

        $result = $this->sqlFilterAdapter->generateWhereSql($filter, new TestEscaper());

        $this->assertEquals($expectedResult, $result);
    }

    public function testGenerateWhereForMultipleValidGroupsMultipleLevelDepth()
    {
        $expectedResult =
            'WHERE (name = \'Test\' OR number < 3) OR (name2 LIKE \'%Test2%\' AND flag = 1) AND (decimal IS NOT NULL OR decimal2 > 5.5 OR range NOT IN (10, 16)) AND ((name2 LIKE \'%Test2%\' AND flag = 1) AND (decimal IS NOT NULL OR decimal2 > 5.5 OR range NOT IN (10, 16)) OR datetime <= \'2014-12-12\')';
        $expression1 = new Expression('name', Condition::EQUAL, DataTypes::STRING_TYPE, ['Test'], Operator::AND_OPERATOR);
        $expression2 = new Expression('number', Condition::LESS_THAN, DataTypes::INT_TYPE, [3], Operator::OR_OPERATOR);
        $expression3 = new Expression('name2', Condition::CONTAINS, DataTypes::STRING_TYPE, ['Test2'], Operator::AND_OPERATOR);
        $expression4 = new Expression('flag', Condition::EQUAL, DataTypes::BOOLEAN_TYPE, [true], Operator::AND_OPERATOR);
        $expression5 = new Expression('decimal', Condition::IS_NOT, DataTypes::FLOAT_TYPE, [], Operator::AND_OPERATOR);
        $expression6 = new Expression('decimal2', Condition::GREATER_THAN, DataTypes::FLOAT_TYPE, [5.5], Operator::OR_OPERATOR);
        $expression7 = new Expression('range', Condition::NOT_IN, DataTypes::FLOAT_ARRAY, [10, 16], Operator::OR_OPERATOR);
        $expression8 = new Expression('datetime', Condition::LESS_OR_EQUAL, DataTypes::DATE_TIME_TYPE, ['2014-12-12']);

        $filter = new Filter('filter1', TransactionHistoryType::EXPORT_PRODUCTS);
        $group1 = new Group('Group 1');
        $group2 = new Group('Group 2', Operator::OR_OPERATOR);
        $group3 = new Group('Group 3');
        $group4 = new Group('Group 4', Operator::AND_OPERATOR);

        $group1->setExpressions([$expression1, $expression2]);
        $group2->setExpressions([$expression3, $expression4]);
        $group3->setExpressions([$expression5, $expression6, $expression7]);
        $group4->setExpressions([$group2, $group3, $expression8]);
        $filter->setExpressions([$group1, $group2, $group3, $group4]);

        $result = $this->sqlFilterAdapter->generateWhereSql($filter, new TestEscaper());

        $this->assertEquals($expectedResult, $result);
    }

    public function testGenerateWhereForMultipleValidGroupsMultipleLevelDepthWithAliases()
    {
        $expectedResult =
            'WHERE (name = \'Test\' OR number < 3) OR (name2 LIKE \'%Test2%\' AND flag = 1) AND (decimal IS NOT NULL OR expr6.decimal2 > 5.5 OR expr7.range NOT IN (10, 16)) AND ((name2 LIKE \'%Test2%\' AND flag = 1) AND (decimal IS NOT NULL OR expr6.decimal2 > 5.5 OR expr7.range NOT IN (10, 16)) OR datetime <= \'2014-12-12\')';
        $expression1 = new Expression('name', Condition::EQUAL, DataTypes::STRING_TYPE, ['Test'], Operator::AND_OPERATOR);
        $expression2 = new Expression('number', Condition::LESS_THAN, DataTypes::INT_TYPE, [3], Operator::OR_OPERATOR);
        $expression3 = new Expression('name2', Condition::CONTAINS, DataTypes::STRING_TYPE, ['Test2'], Operator::AND_OPERATOR);
        $expression4 = new Expression('flag', Condition::EQUAL, DataTypes::BOOLEAN_TYPE, [true], Operator::AND_OPERATOR);
        $expression5 = new Expression('decimal', Condition::IS_NOT, DataTypes::FLOAT_TYPE, [], Operator::AND_OPERATOR);
        $expression6 = new Expression('decimal2', Condition::GREATER_THAN, DataTypes::FLOAT_TYPE, [5.5], Operator::OR_OPERATOR);
        $expression6->setAlias('expr6');
        $expression7 = new Expression('range', Condition::NOT_IN, DataTypes::FLOAT_ARRAY, [10, 16], Operator::OR_OPERATOR);
        $expression7->setAlias('expr7');
        $expression8 = new Expression('datetime', Condition::LESS_OR_EQUAL, DataTypes::DATE_TIME_TYPE, ['2014-12-12']);

        $filter = new Filter('filter1', TransactionHistoryType::EXPORT_PRODUCTS);
        $group1 = new Group('Group 1');
        $group2 = new Group('Group 2', Operator::OR_OPERATOR);
        $group3 = new Group('Group 3');
        $group4 = new Group('Group 4', Operator::AND_OPERATOR);

        $group1->setExpressions([$expression1, $expression2]);
        $group2->setExpressions([$expression3, $expression4]);
        $group3->setExpressions([$expression5, $expression6, $expression7]);
        $group4->setExpressions([$group2, $group3, $expression8]);
        $filter->setExpressions([$group1, $group2, $group3, $group4]);

        $result = $this->sqlFilterAdapter->generateWhereSql($filter, new TestEscaper());

        $this->assertEquals($expectedResult, $result);
    }
}