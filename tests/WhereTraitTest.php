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
        $query = (new Query())->where('table.foo', '>', 'bar');
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]);
        $this->assertAttributes(['column' => 'table.foo', 'rule' => '>', 'value' => 'bar', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]);

        // Or where
        $query = (new Query())->orWhere('foo', '<', 'bar');
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]);
        $this->assertAttributes(['column' => 'foo', 'rule' => '<', 'value' => 'bar', 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[0]);

        // Omit the rule
        $query = (new Query())->where('table.foo', 'bar');
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]);
        $this->assertAttributes(['column' => 'table.foo', 'rule' => '=', 'value' => 'bar'], $query->where[0]);

        // Grouped criteria (by callback)
        $query = (new Query())->table('foo', 'f')->where(function (Query $query) {
            $this->assertAttributes(['table' => 'foo', 'tableAlias' => 'f'], $query);
            $query->where('table.column1', 'value1')->orWhere('table.column2', 'value2');
        });
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(CriteriaCriterion::class, $query->where[0]);
        $this->assertCount(2, $query->where[0]->criteria);
        $this->assertFalse($query->where[0]->not);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]->criteria[0]);
        $this->assertAttributes(['column' => 'table.column1', 'rule' => '=', 'value' => 'value1', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]->criteria[0]);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]->criteria[1]);
        $this->assertAttributes(['column' => 'table.column2', 'rule' => '=', 'value' => 'value2', 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[0]->criteria[1]);

        // Grouped criteria (by array)
        $query = (new Query())->where([
            ['table.column1', 'value1'],
            ['table.column2', '!=', 'value2']
        ]);
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(CriteriaCriterion::class, $query->where[0]);
        $this->assertCount(2, $query->where[0]->criteria);
        $this->assertFalse($query->where[0]->not);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]->criteria[0]);
        $this->assertAttributes(['column' => 'table.column1', 'rule' => '=', 'value' => 'value1', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]->criteria[0]);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]->criteria[1]);
        $this->assertAttributes(['column' => 'table.column2', 'rule' => '!=', 'value' => 'value2', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]->criteria[1]);

        // Raw clause
        $query = (new Query())->where(new Raw('date + ? = NOW()', [10]));
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(RawCriterion::class, $query->where[0]);
        $this->assertStatement('date + ? = NOW()', [10], $query->where[0]->raw);

        // Wrong arguments set
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->orWhere(new \stdClass());
        });

        // Ordinary with complex values
        $query = (new Query())
            ->where(
                function (Query $query) {
                    $query->addCount()->table('bar');
                },
                '>',
                new Raw('NOW()')
            )
            ->where('price', '<=', function (Query $query) {
                return $query->table('prices')->addAvg('value');
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
        $query = (new Query())->table('foo', 'f')->whereNot(function (Query $query) {
            $this->assertAttributes(['table' => 'foo', 'tableAlias' => 'f'], $query);
            $query->where('table.column1', 'value1')->orWhere('table.column2', 'value2');
        });
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(CriteriaCriterion::class, $query->where[0]);
        $this->assertAttributes(['not' => true, 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]);
        $this->assertCount(2, $query->where[0]->criteria);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]->criteria[0]);
        $this->assertAttributes(['column' => 'table.column1', 'rule' => '=', 'value' => 'value1', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]->criteria[0]);
        $this->assertInstanceOf(ValueCriterion::class, $query->where[0]->criteria[1]);
        $this->assertAttributes(['column' => 'table.column2', 'rule' => '=', 'value' => 'value2', 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[0]->criteria[1]);

        // Or where not
        $query = (new Query())->table('foo', 'f')->orWhereNot(function (Query $query) {
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
        $query = (new Query())
            ->whereBetween('table.price', 13, 123891)
            ->orWhereBetween('date', new Raw('YESTERDAY()'), new Raw('NOW()'))
            ->whereNotBetween(
                function (Query $query) {
                    $query->addSelect('foo')->table('bar');
                },
                function (Query $query) {
                    $query->addMin('weight')->table('items');
                },
                function (Query $query) {
                    $query->addMax('weight')->table('items');
                }
            )
            ->orWhereNotBetween(
                (new Query())->addSelect('name')->table('users'),
                'Alice',
                'Bob'
            );

        $this->assertCount(4, $query->where);
        foreach ($query->where as $criterion) {
            $this->assertInstanceOf(BetweenCriterion::class, $criterion);
        }
        $this->assertAttributes(['column' => 'table.price', 'min' => 13, 'max' => 123891, 'not' => false, 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]);
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
        $query = (new Query())
            ->whereIn('table.name', ['Anna', 'Bill', 'Carl'])
            ->orWhereIn('group', new Raw('TABLES()'))
            ->whereNotIn(
                function (Query $query) {
                    $query->addSelect('foo')->table('bar');
                },
                function (Query $query) {
                    $query->addSelect('title')->table('items');
                }
            )
            ->orWhereNotIn(
                (new Query())->addSelect('name')->table('users'),
                [4, new Raw('foo'), function (Query $query) {
                    $query->addAvg('price')->table('products');
                }]
            );

        $this->assertCount(4, $query->where);
        foreach ($query->where as $criterion) {
            $this->assertInstanceOf(InCriterion::class, $criterion);
        }
        $this->assertAttributes(['column' => 'table.name', 'values' => ['Anna', 'Bill', 'Carl'], 'not' => false, 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]);
        $this->assertAttributes(['column' => 'group', 'not' => false, 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[1]);
        $this->assertStatement('TABLES()', [], $query->where[1]->values);
        $this->assertAttributes(['not' => true, 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[2]);
        $this->assertInstanceOf(Query::class, $query->where[2]->column);
        $this->assertInstanceOf(Query::class, $query->where[2]->values);
        $this->assertAttributes(['not' => true, 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[3]);
        $this->assertInstanceOf(Query::class, $query->where[3]->column);
        $this->assertCount(3, $query->where[3]->values);
        $this->assertEquals(4, $query->where[3]->values[0]);
        $this->assertInstanceOf(Raw::class, $query->where[3]->values[1]);
        $this->assertInstanceOf(Query::class, $query->where[3]->values[2]);

        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->whereIn('name', 'foo');
        });
    }

    /**
     * Tests the whereNull, orWhereNull, whereNotNull and orWhereNotNull methods
     */
    public function testWhereNull()
    {
        $query = (new Query())
            ->whereNull('table.name')
            ->orWhereNull('group')
            ->whereNotNull(function (Query $query) {
                $query->addSelect('foo')->table('bar');
            })
            ->orWhereNotNull((new Query())->addSelect('name')->table('users'));

        $this->assertCount(4, $query->where);
        foreach ($query->where as $criterion) {
            $this->assertInstanceOf(NullCriterion::class, $criterion);
        }
        $this->assertAttributes(['column' => 'table.name', 'isNull' => true, 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]);
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
        $query = (new Query())->whereColumn('table1.foo', '>', 'table2.bar');
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(ColumnsCriterion::class, $query->where[0]);
        $this->assertAttributes(['column1' => 'table1.foo', 'rule' => '>', 'column2' => 'table2.bar', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]);

        // Or where
        $query = (new Query())->orWhereColumn('foo', '<', 'bar');
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(ColumnsCriterion::class, $query->where[0]);
        $this->assertAttributes(['column1' => 'foo', 'rule' => '<', 'column2' => 'bar', 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[0]);

        // Omit the rule
        $query = (new Query())->whereColumn('table.foo', 'bar');
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(ColumnsCriterion::class, $query->where[0]);
        $this->assertAttributes(['column1' => 'table.foo', 'rule' => '=', 'column2' => 'bar'], $query->where[0]);

        // Grouped criteria
        $query = (new Query())->whereColumn([
            ['table1.column1', 'table2.column1'],
            ['table1.column2', '!=', 'table2.column2']
        ]);
        $this->assertCount(1, $query->where);
        $this->assertInstanceOf(CriteriaCriterion::class, $query->where[0]);
        $this->assertCount(2, $query->where[0]->criteria);
        $this->assertFalse($query->where[0]->not);
        $this->assertInstanceOf(ColumnsCriterion::class, $query->where[0]->criteria[0]);
        $this->assertAttributes(['column1' => 'table1.column1', 'rule' => '=', 'column2' => 'table2.column1', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]->criteria[0]);
        $this->assertInstanceOf(ColumnsCriterion::class, $query->where[0]->criteria[1]);
        $this->assertAttributes(['column1' => 'table1.column2', 'rule' => '!=', 'column2' => 'table2.column2', 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]->criteria[1]);

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
        $query = (new Query())
            ->table('table')
            ->whereExists(function (Query $query) {
                $query->table('other_table')->whereColumn('table.foo', 'other_table.bar');
            })
            ->orWhereExists(new Raw('TABLES()'))
            ->whereNotExists(function (Query $query) {
                $query->table('other_table')->whereColumn('table.foo', 'other_table.bar');
            })
            ->orWhereNotExists((new Query())->table('users'));

        $this->assertCount(4, $query->where);
        foreach ($query->where as $criterion) {
            $this->assertInstanceOf(ExistsCriterion::class, $criterion);
        }
        $this->assertAttributes(['not' => false, 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[0]);
        $this->assertInstanceOf(Query::class, $query->where[0]->subQuery);
        $this->assertEquals('other_table', $query->where[0]->subQuery->table);
        $this->assertAttributes(['column1' => 'table.foo', 'column2' => 'other_table.bar'], $query->where[0]->subQuery->where[0]);
        $this->assertAttributes(['not' => false, 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[1]);
        $this->assertStatement('TABLES()', [], $query->where[1]->subQuery);
        $this->assertAttributes(['not' => true, 'appendRule' => Criterion::APPEND_RULE_AND], $query->where[2]);
        $this->assertInstanceOf(Query::class, $query->where[2]->subQuery);
        $this->assertAttributes(['not' => true, 'appendRule' => Criterion::APPEND_RULE_OR], $query->where[3]);
        $this->assertInstanceOf(Query::class, $query->where[3]->subQuery);

        $this->assertException(InvalidArgumentException::class, function () {
            (new Query(''))->whereExists('foo bar');
        });
    }
}
