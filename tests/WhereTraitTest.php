<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Criteria\BetweenCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\CriteriaCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\RawCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ValueCriterion;
use Finesse\QueryScribe\QueryBricks\Criterion;
use Finesse\QueryScribe\Raw;

/**
 * Tests the WhereTrait trait
 *
 * @author Surgie
 */
class WhereTraitTest extends TestCase
{
    /**
     * Tests the where and orWhere methods
     */
    public function testWhere()
    {
        // Ordinary
        $query = (new Query('pre_'))->where('table.foo', '>', 'bar');
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]);
        $this->assertAttributes(['column' => 'pre_table.foo', 'rule' => '>', 'value' => 'bar', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]);

        // Or where
        $query = (new Query('pre_'))->orWhere('foo', '<', 'bar');
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]);
        $this->assertAttributes(['column' => 'foo', 'rule' => '<', 'value' => 'bar', 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[0]);

        // Omit the rule
        $query = (new Query('pre_'))->where('table.foo', 'bar');
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]);
        $this->assertAttributes(['column' => 'pre_table.foo', 'rule' => '=', 'value' => 'bar'], $query->where[0]);

        // Grouped criteria (by callback)
        $query = (new Query('pre_'))->from('foo', 'f')->where(function (Query $query) {
            $this->assertAttributes(['from' => 'pre_foo', 'fromAlias' => 'f'], $query);
            $query->where('table.column1', 'value1')->orWhere('table.column2', 'value2');
        });
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(CriteriaCriterion::class, $query->where[0]);
        $this->assertCount(2, $query->where[0]->criteria);
        $this->assertFalse($query->where[0]->not);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]->criteria[0]);
        $this->assertAttributes(['column' => 'pre_table.column1', 'rule' => '=', 'value' => 'value1', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]->criteria[0]);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]->criteria[1]);
        $this->assertAttributes(['column' => 'pre_table.column2', 'rule' => '=', 'value' => 'value2', 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[0]->criteria[1]);

        // Grouped criteria (by array)
        $query = (new Query('pre_'))->where([
            ['table.column1', 'value1'],
            ['table.column2', '!=', 'value2']
        ]);
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(CriteriaCriterion::class, $query->where[0]);
        $this->assertCount(2, $query->where[0]->criteria);
        $this->assertFalse($query->where[0]->not);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]->criteria[0]);
        $this->assertAttributes(['column' => 'pre_table.column1', 'rule' => '=', 'value' => 'value1', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]->criteria[0]);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]->criteria[1]);
        $this->assertAttributes(['column' => 'pre_table.column2', 'rule' => '!=', 'value' => 'value2', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]->criteria[1]);

        // Raw clause
        $query = (new Query('pre_'))->where(new Raw('date + ? = NOW()', [10]));
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(RawCriterion::class, $query->where[0]);
        $this->assertStatement('date + ? = NOW()', [10], $query->where[0]->raw);

        // Wrong arguments set
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->orWhere(new \stdClass());
        });

        // Ordinary with complex values
        $query = (new Query('pre_'))
            ->where(
                function (Query $query) {
                    $query->count()->from('bar');
                },
                '>',
                new Raw('NOW()')
            )
            ->where('price', '<=', function (Query $query) {
                return $query->from('prices')->avg('value');
            });
        $this->assertCount(2, $query->where);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]);
        $this->assertInstanceOf(Query::class, $query->where[0]->column);
        $this->assertEquals('>', $query->where[0]->rule);
        $this->assertInstanceOf(Raw::class, $query->where[0]->value);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[1]);
        $this->assertAttributes(['column' => 'price', 'rule' => '<='], $query->where[1]);
        $this->assertInstanceOf(Query::class, $query->where[1]->value);

        // Wrong rule value
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->where(new Raw(''), new Raw(''), new Raw(''));
        });

        // Wrong value
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->where('name', 'like', ['foo', 'bar']);
        });
    }

    /**
     * Tests the whereNot and orWhereNot methods
     */
    public function testWhereNot()
    {
        // Where not
        $query = (new Query('pre_'))->from('foo', 'f')->whereNot(function (Query $query) {
            $this->assertAttributes(['from' => 'pre_foo', 'fromAlias' => 'f'], $query);
            $query->where('table.column1', 'value1')->orWhere('table.column2', 'value2');
        });
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(CriteriaCriterion::class, $query->where[0]);
        $this->assertAttributes(['not' => true, 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]);
        $this->assertCount(2, $query->where[0]->criteria);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]->criteria[0]);
        $this->assertAttributes(['column' => 'pre_table.column1', 'rule' => '=', 'value' => 'value1', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]->criteria[0]);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]->criteria[1]);
        $this->assertAttributes(['column' => 'pre_table.column2', 'rule' => '=', 'value' => 'value2', 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[0]->criteria[1]);

        // Or where not
        $query = (new Query('pre_'))->from('foo', 'f')->orWhereNot(function (Query $query) {
            $query->where('column1', 'value1')->orWhere('column2', 'value2');
        });
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(CriteriaCriterion::class, $query->where[0]);
        $this->assertAttributes(['not' => true, 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[0]);
        $this->assertCount(2, $query->where[0]->criteria);
    }

    /**
     * Tests the whereRaw and orWhereRaw methods
     */
    public function testWhereRaw()
    {
        $query = (new Query())->whereRaw('? = NOW()', [15])->orWhereRaw('DAY(column) = MONTH(column)');
        $this->assertCount(2, $query->where);
        $this->assertInstanceOf(RawCriterion::class, $query->where[0]);
        $this->assertEquals(Criterion::APPEND_RULE_AND, $query->where[0]->appendRule);
        $this->assertStatement('? = NOW()', [15], $query->where[0]->raw);
        $this->assertInstanceOf(RawCriterion::class, $query->where[1]);
        $this->assertEquals(Criterion::APPEND_RULE_OR, $query->where[1]->appendRule);
        $this->assertStatement('DAY(column) = MONTH(column)', [], $query->where[1]->raw);
    }

    /**
     * Tests the whereBetween, orWhereBetween, whereNotBetween and orWhereNotBetween methods
     */
    public function testBetween()
    {
        $query = (new Query('test_'))
            ->whereBetween('table.price', 13, 123891)
            ->orWhereBetween('date', new Raw('YESTERDAY()'), new Raw('NOW()'))
            ->whereNotBetween(
                function (Query $query) {
                    $query->select('foo')->from('bar');
                },
                function (Query $query) {
                    $query->min('weight')->from('items');
                },
                function (Query $query) {
                    $query->max('weight')->from('items');
                }
            )
            ->orWhereNotBetween(
                (new Query('demo_'))->select('name')->from('users'),
                'Alice',
                'Bob'
            );

        $this->assertCount(4, $query->where);
        foreach ($query->where as $criterion) {
            $this->assertInstanceOf(BetweenCriterion::class, $criterion);
        }
        $this->assertAttributes(['column' => 'test_table.price', 'min' => 13, 'max' => 123891, 'not' => false, 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]);
        $this->assertAttributes(['column' => 'date', 'not' => false, 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[1]);
        $this->assertStatement('YESTERDAY()', [], $query->where[1]->min);
        $this->assertStatement('NOW()', [], $query->where[1]->max);
        $this->assertAttributes(['not' => true, 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[2]);
        $this->assertInstanceOf(Query::class, $query->where[2]->column);
        $this->assertInstanceOf(Query::class, $query->where[2]->min);
        $this->assertInstanceOf(Query::class, $query->where[2]->max);
        $this->assertAttributes(['min' => 'Alice', 'max' => 'Bob', 'not' => true, 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[3]);
        $this->assertInstanceOf(Query::class, $query->where[3]->column);
    }
}
