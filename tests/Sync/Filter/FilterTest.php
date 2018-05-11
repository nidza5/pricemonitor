<?php


use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Interfaces\LoggerService;
use Patagona\Pricemonitor\Core\Sync\Filter\Condition;
use Patagona\Pricemonitor\Core\Sync\Filter\DataTypes;
use Patagona\Pricemonitor\Core\Sync\Filter\Expression;
use Patagona\Pricemonitor\Core\Sync\Filter\Filter;
use Patagona\Pricemonitor\Core\Sync\Filter\FilterValidationError;
use Patagona\Pricemonitor\Core\Sync\Filter\Group;
use Patagona\Pricemonitor\Core\Sync\Filter\Operator;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryType;
use Patagona\Pricemonitor\Core\Tests\TestComponents\TestLoggerService;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase
{
    /** @var  TestLoggerService */
    private $loggerService;

    public function setUp()
    {
        parent::setUp();

        $this->loggerService = new TestLoggerService();

        new ServiceRegister([], [LoggerService::class => $this->loggerService,]);
    }

    public function testMakingValidExpression()
    {
        $expression = new Expression('name', Condition::EQUAL, DataTypes::STRING_TYPE, ['Test'], Operator::AND_OPERATOR);

        $errors = $expression->validate();

        $this->assertEmpty($errors->getOwnErrors());
        $this->assertEmpty($errors->getChildrenErrors());
    }

    public function testMakingInvalidExpressionWithWrongOperator()
    {
        $operatorName = 'Test';
        $expression = new Expression('name', Condition::EQUAL, DataTypes::STRING_TYPE, ['Test'], $operatorName);

        $errors = $expression->validate();

        $this->assertNotEmpty($errors->getOwnErrors());
        $this->assertEquals(1, count($errors->getOwnErrors()));
        $this->assertStringStartsWith("Invalid operator: {$operatorName}", $errors->getOwnErrors()[0]);
    }

    public function testMakingInvalidExpressionWithWrongCondition()
    {
        $condition = 'test_condition';
        $exceptionMessage = '';

        try {
            new Expression('name', $condition, DataTypes::STRING_TYPE, ['Test'], Operator::OR_OPERATOR);
        } catch (\Exception $e) {
            $exceptionMessage = $e->getMessage();
        }

        $this->assertNotEmpty($exceptionMessage);
    }

    public function testMakingInvalidExpressionWithWrongType()
    {
        $type = 'Text';
        $exceptionMessage = '';

        try {
            new Expression('name', Condition::EQUAL, $type, ['Test'], Operator::OR_OPERATOR);
        } catch (\Exception $e) {
            $exceptionMessage = $e->getMessage();
        }

        $this->assertNotEmpty($exceptionMessage);
    }

    public function testMakingInvalidExpressionWithWrongValue()
    {
        $exceptionMessage = '';

        try {
            new Expression(
                'name', Condition::EQUAL, DataTypes::STRING_TYPE, null, Operator::OR_OPERATOR
            );
        } catch (\Exception $e) {
            $exceptionMessage = $e->getMessage();
        }

        $this->assertNotEmpty($exceptionMessage);
    }

    public function testMakingInvalidExpressionWithWrongTypeValueCombination()
    {
        $expression = new Expression(
            'name', Condition::EQUAL, DataTypes::STRING_TYPE, ['Test1', 'Test2'], Operator::OR_OPERATOR
        );

        $errors = $expression->validate();

        $this->assertNotEmpty($errors->getOwnErrors());
        $this->assertEquals(1, count($errors->getOwnErrors()));
        $this->assertStringStartsWith('Type: string must have only one value.', $errors->getOwnErrors()[0]);
    }

    public function testMakingInvalidExpressionWithAllParametersWrong()
    {
        $operator = 'Test';
        $condition = 'test_condition';
        $type = 'Text';
        $expression = null;
        try {
            $expression = new Expression(
                '', $condition, $type, ['Test1', 'Test2'], $operator
            );
        } catch (\Exception $e) {

        }

       $this->assertNull($expression);
    }

    public function testAddingValidExpressionsToFilter()
    {
        $groupExpressions = [
            new Expression(
                'name', Condition::EQUAL, DataTypes::STRING_TYPE, ['Test1'], Operator::AND_OPERATOR
            ),
            new Expression(
                'number', Condition::EQUAL, DataTypes::INT_TYPE, [23], Operator::OR_OPERATOR
            ),
        ];
        $group = new Group('Group1');
        $filter = new Filter('filter1', TransactionHistoryType::IMPORT_PRICES);

        $group->setExpressions($groupExpressions);
        $filter->setExpressions([$group]);

        $errors = $filter->validate();

        $this->assertEmpty($errors->getOwnErrors());
        $this->assertEmpty($errors->getChildrenErrors());
        $this->assertNotEmpty($filter->getExpressions());
        $this->assertEquals(1, count($filter->getExpressions()));
    }

    public function testAddingTwoGroupsWithOneInvalidExpressionToFilter()
    {
        $expression2Operator = 'ili';

        $groupExpressions = [
            new Expression(
                'name', Condition::EQUAL, DataTypes::STRING_TYPE, ['Test1'], Operator::AND_OPERATOR
            ),
            new Expression(
                'number', Condition::EQUAL, DataTypes::INT_TYPE, [23], $expression2Operator
            ),
        ];

        $group1 = new Group('Group1');
        $group2 = new Group('Group2');
        $filter = new Filter('filter1', TransactionHistoryType::IMPORT_PRICES);

        $group1->setExpressions($groupExpressions);
        $group2->setExpressions($groupExpressions);
        $filter->setExpressions([$group1, $group2]);
        $errors = $filter->validate();

        /** @var FilterValidationError $firstLevelChildrenErrors */
        $firstLevelChildrenErrors = $errors->getChildrenErrors()[0];
        $this->assertArrayHasKey(1, $firstLevelChildrenErrors->getChildrenErrors());
        /** @var FilterValidationError $secondLevelChildrenErrors */
        $secondLevelChildrenErrors = $firstLevelChildrenErrors->getChildrenErrors()[1];
        $this->assertEquals(1, count($secondLevelChildrenErrors->getOwnErrors()));
        $this->assertStringStartsWith('Invalid operator:', array_pop($secondLevelChildrenErrors->getOwnErrors()));
        /** @var FilterValidationError $firstLevelChildrenErrors */
        $firstLevelChildrenErrors = $errors->getChildrenErrors()[1];
        $this->assertArrayHasKey(1, $firstLevelChildrenErrors->getChildrenErrors());
        /** @var FilterValidationError $secondLevelChildrenErrors */
        $secondLevelChildrenErrors = $firstLevelChildrenErrors->getChildrenErrors()[1];
        $this->assertEquals(1, count($secondLevelChildrenErrors->getOwnErrors()));
        $this->assertStringStartsWith('Invalid operator:', array_pop($secondLevelChildrenErrors->getOwnErrors()));
    }

    public function testAddingTwoGroupsWithOneInvalidExpressionAndGroupInGroupToFilter()
    {
        $expression2Operator = 'ili';

        $groupExpressions = [
            new Expression(
                'name', Condition::EQUAL, DataTypes::STRING_TYPE, ['Test1'], Operator::AND_OPERATOR
            ),
            new Expression(
                'number', Condition::EQUAL, DataTypes::INT_TYPE, [23], $expression2Operator
            ),
        ];

        $group1 = new Group('Group1');
        $group2 = new Group('Group2');
        $filter = new Filter('filter1', TransactionHistoryType::IMPORT_PRICES);

        $group1->setExpressions($groupExpressions);
        $group2->setExpressions(array_merge($groupExpressions, [$group1]));
        $filter->setExpressions([$group1, $group2]);
        $errors = $filter->validate();
        /** @var FilterValidationError $childrenErrors */
        $childrenErrors = $errors->getChildrenErrors()[1];
        $childrenErrors = $childrenErrors->getChildrenErrors()[2];
        $childrenErrors = $childrenErrors->getChildrenErrors()[1];
        $this->assertStringStartsWith('Invalid operator:', array_pop($childrenErrors->getOwnErrors()));
    }

    public function testSerializeDeserializeFilter()
    {
        $groupExpressions = [
            new Expression(
                'name', Condition::EQUAL, DataTypes::STRING_TYPE, ['Test1'], Operator::AND_OPERATOR
            ),
            new Expression(
                'number', Condition::EQUAL, DataTypes::INT_TYPE, [23], Operator::OR_OPERATOR
            ),
        ];
        $group = new Group('Group1');
        $filter = new Filter('filter1', TransactionHistoryType::IMPORT_PRICES);

        $group->setExpressions($groupExpressions);
        $filter->setExpressions([$group]);
        $filterBeforeSerialization = $filter;
        $filter->unserialize($filter->serialize());

        $this->assertEquals($filterBeforeSerialization, $filter);
    }
}