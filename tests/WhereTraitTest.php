<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Criteria\BetweenCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ColumnsCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\CriteriaCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ExistsCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\InCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\NullCriterion;
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

    /**
     * Tests the whereIn, orWhereIn, whereNotIn and orWhereNotIn methods
     */
    public function testWhereIn()
    {
        $query = (new Query('test_'))
            ->whereIn('table.name', ['Anna', 'Bill', 'Carl'])
            ->orWhereIn('group', new Raw('TABLES()'))
            ->whereNotIn(
                function (Query $query) {
                    $query->select('foo')->from('bar');
                },
                function (Query $query) {
                    $query->select('title')->from('items');
                }
            )
            ->orWhereNotIn(
                (new Query('demo_'))->select('name')->from('users'),
                [1, 4, 10, 20]
            );

        $this->assertCount(4, $query->where);
        foreach ($query->where as $criterion) {
            $this->assertInstanceOf(InCriterion::class, $criterion);
        }
        $this->assertAttributes(['column' => 'test_table.name', 'values' => ['Anna', 'Bill', 'Carl'], 'not' => false, 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]);
        $this->assertAttributes(['column' => 'group', 'not' => false, 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[1]);
        $this->assertStatement('TABLES()', [], $query->where[1]->values);
        $this->assertAttributes(['not' => true, 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[2]);
        $this->assertInstanceOf(Query::class, $query->where[2]->column);
        $this->assertInstanceOf(Query::class, $query->where[2]->values);
        $this->assertAttributes(['values' => [1, 4, 10, 20], 'not' => true, 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[3]);
        $this->assertInstanceOf(Query::class, $query->where[3]->column);

        $this->assertException(InvalidArgumentException::class, function () {
            (new Query('test_'))->whereIn('name', 'foo');
        });
    }

    /**
     * Tests the whereNull, orWhereNull, whereNotNull and orWhereNotNull methods
     */
    public function testWhereNull()
    {
        $query = (new Query('test_'))
            ->whereNull('table.name')
            ->orWhereNull('group')
            ->whereNotNull(function (Query $query) {
                $query->select('foo')->from('bar');
            })
            ->orWhereNotNull((new Query('demo_'))->select('name')->from('users'));

        $this->assertCount(4, $query->where);
        foreach ($query->where as $criterion) {
            $this->assertInstanceOf(NullCriterion::class, $criterion);
        }
        $this->assertAttributes(['column' => 'test_table.name', 'isNull' => true, 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]);
        $this->assertAttributes(['column' => 'group', 'isNull' => true, 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[1]);
        $this->assertAttributes(['isNull' => false, 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[2]);
        $this->assertInstanceOf(Query::class, $query->where[2]->column);
        $this->assertAttributes(['isNull' => false, 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[3]);
        $this->assertInstanceOf(Query::class, $query->where[3]->column);
    }

    /**
     * Tests the whereColumn and orWhereColumn methods
     */
    public function testWhereColumn()
    {
        // Ordinary
        $query = (new Query('pre_'))->whereColumn('table1.foo', '>', 'table2.bar');
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(ColumnsCriterion::class, $query->where[0]);
        $this->assertAttributes(['column1' => 'pre_table1.foo', 'rule' => '>', 'column2' => 'pre_table2.bar', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]);

        // Or where
        $query = (new Query('pre_'))->orWhereColumn('foo', '<', 'bar');
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(ColumnsCriterion::class, $query->where[0]);
        $this->assertAttributes(['column1' => 'foo', 'rule' => '<', 'column2' => 'bar', 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[0]);

        // Omit the rule
        $query = (new Query('pre_'))->whereColumn('table.foo', 'bar');
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(ColumnsCriterion::class, $query->where[0]);
        $this->assertAttributes(['column1' => 'pre_table.foo', 'rule' => '=', 'column2' => 'bar'], $query->where[0]);

        // Grouped criteria
        $query = (new Query('pre_'))->whereColumn([
            ['table1.column1', 'table2.column1'],
            ['table1.column2', '!=', 'table2.column2']
        ]);
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(CriteriaCriterion::class, $query->where[0]);
        $this->assertCount(2, $query->where[0]->criteria);
        $this->assertFalse($query->where[0]->not);
        $this->assertInstanceOf(ColumnsCriterion::class, $query->where[0]->criteria[0]);
        $this->assertAttributes(['column1' => 'pre_table1.column1', 'rule' => '=', 'column2' => 'pre_table2.column1', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]->criteria[0]);
        $this->assertInstanceOf(ColumnsCriterion::class, $query->where[0]->criteria[1]);
        $this->assertAttributes(['column1' => 'pre_table1.column2', 'rule' => '!=', 'column2' => 'pre_table2.column2', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]->criteria[1]);

        // Wrong rule value
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->whereColumn(new Raw(''), new Raw(''), new Raw(''));
        });
    }

    /**
     * Tests the whereExists, orWhereExists, whereNotExists and orWhereNotExists methods
     */
    public function testWhereExists()
    {
        $query = (new Query('test_'))
            ->from('table')
            ->whereExists(function (Query $query) {
                $query->from('other_table')->whereColumn('table.foo', 'other_table.bar');
            })
            ->orWhereExists(new Raw('TABLES()'))
            ->whereNotExists(function (Query $query) {
                $query->from('other_table')->whereColumn('table.foo', 'other_table.bar');
            })
            ->orWhereNotExists((new Query('demo_'))->from('users'));

        $this->assertCount(4, $query->where);
        foreach ($query->where as $criterion) {
            $this->assertInstanceOf(ExistsCriterion::class, $criterion);
        }
        $this->assertAttributes(['not' => false, 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]);
        $this->assertInstanceOf(Query::class, $query->where[0]->subQuery);
        $this->assertEquals('test_other_table', $query->where[0]->subQuery->from);
        $this->assertAttributes(['column1' => 'test_table.foo', 'column2' => 'test_other_table.bar'], $query->where[0]->subQuery->where[0]);
        $this->assertAttributes(['not' => false, 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[1]);
        $this->assertStatement('TABLES()', [], $query->where[1]->subQuery);
        $this->assertAttributes(['not' => true, 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[2]);
        $this->assertInstanceOf(Query::class, $query->where[2]->subQuery);
        $this->assertAttributes(['not' => true, 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[3]);
        $this->assertInstanceOf(Query::class, $query->where[3]->subQuery);

        $this->assertException(InvalidArgumentException::class, function () {
            (new Query('test_'))->whereExists('foo bar');
        });
    }
}
