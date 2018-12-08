<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\ClosureResolverInterface;
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
        $query = (new Query)->where('table.foo', '>', 'bar');
        $this->assertAttributeEquals([new ValueCriterion('table.foo', '>', 'bar', 'AND')], 'where', $query);

        // Or where
        $query = (new Query)->orWhere('foo', '<', 'bar');
        $this->assertAttributeEquals([new ValueCriterion('foo', '<', 'bar', 'OR')], 'where', $query);

        // Omit the rule
        $query = (new Query)->where('table.foo', 'bar');
        $this->assertAttributeEquals([new ValueCriterion('table.foo', '=', 'bar', 'AND')], 'where', $query);

        // Grouped criteria (by callback)
        $query = (new Query)->table('foo', 'f')->where(function (Query $query) {
            $this->assertAttributes(['table' => 'foo', 'tableAlias' => 'f'], $query);
            $query->where('table.column1', 'value1')->orWhere('table.column2', 'value2');
        });
        $this->assertAttributeEquals([
            new CriteriaCriterion([
                new ValueCriterion('table.column1', '=', 'value1', 'AND'),
                new ValueCriterion('table.column2', '=', 'value2', 'OR')
            ], false, 'AND')
        ], 'where', $query);

        // Grouped criteria (by array)
        $query = (new Query)->where([
            ['table.column1', 'value1'],
            ['table.column2', '!=', 'value2']
        ]);
        $this->assertAttributeEquals([
            new CriteriaCriterion([
                new ValueCriterion('table.column1', '=', 'value1', 'AND'),
                new ValueCriterion('table.column2', '!=', 'value2', 'AND')
            ], false, 'AND')
        ], 'where', $query);

        // Raw clause
        $query = (new Query)->where(new Raw('date + ? = NOW()', [10]));
        $this->assertAttributeEquals([new RawCriterion(new Raw('date + ? = NOW()', [10]), 'AND')], 'where', $query);

        // Null value
        $query = (new Query)->where('foo', '<', null)->where('bar', null);
        $this->assertAttributeEquals([
            new ValueCriterion('foo', '<', null, 'AND'),
            new ValueCriterion('bar', '=', null, 'AND')
        ], 'where', $query);

        // Ordinary with complex values
        $query = (new Query)
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
        $this->assertAttributeEquals([
            new ValueCriterion((new Query)->addCount()->table('bar'), '>', new Raw('NOW()'), 'AND'),
            new ValueCriterion('price', '<=', (new Query)->table('prices')->addAvg('value'), 'AND'),
        ], 'where', $query);

        // Wrong single argument
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->orWhere('name');
        });

        // Wrong column
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->orWhere(new \stdClass(), 'foo');
        });

        // Wrong rule value
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->where(new Raw(''), new Raw(''), new Raw(''));
        });

        // Wrong value
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->where('name', 'like', ['foo', 'bar']);
        });

        // Too many arguments
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->where('name', 'like', 'foo', 'bar');
        });

        // Too few arguments
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->where();
        });
    }

    /**
     * Tests the whereNot and orWhereNot methods
     */
    public function testWhereNot()
    {
        // Where not
        $query = (new Query)->table('foo', 'f')->whereNot(function (Query $query) {
            $this->assertAttributes(['table' => 'foo', 'tableAlias' => 'f'], $query);
            $query->where('table.column1', 'value1')->orWhere('table.column2', 'value2');
        });
        $this->assertAttributeEquals([
            new CriteriaCriterion([
                new ValueCriterion('table.column1', '=', 'value1', 'AND'),
                new ValueCriterion('table.column2', '=', 'value2', 'OR')
            ], true, 'AND')
        ], 'where', $query);

        // Or where not
        $query = (new Query)->table('foo', 'f')->orWhereNot(function (Query $query) {
            $query->where('column1', 'value1')->orWhere('column2', 'value2');
        });
        $this->assertAttributeEquals([
            new CriteriaCriterion([
                new ValueCriterion('column1', '=', 'value1', 'AND'),
                new ValueCriterion('column2', '=', 'value2', 'OR')
            ], true, 'OR')
        ], 'where', $query);
    }

    /**
     * Tests the whereRaw and orWhereRaw methods
     */
    public function testWhereRaw()
    {
        $query = (new Query)->whereRaw('? = NOW()', [15])->orWhereRaw('DAY(column) = MONTH(column)');
        $this->assertAttributeEquals([
            new RawCriterion(new Raw('? = NOW()', [15]), 'AND'),
            new RawCriterion(new Raw('DAY(column) = MONTH(column)'), 'OR')
        ], 'where', $query);
    }

    /**
     * Tests the whereBetween, orWhereBetween, whereNotBetween and orWhereNotBetween methods
     */
    public function testBetween()
    {
        $query = (new Query)
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
                (new Query)->addSelect('name')->table('users'),
                'Alice',
                'Bob'
            );

        $this->assertAttributeEquals([
            new BetweenCriterion('table.price', 13, 123891, false, 'AND'),
            new BetweenCriterion('date', new Raw('YESTERDAY()'), new Raw('NOW()'), false, 'OR'),
            new BetweenCriterion(
                (new Query)->addSelect('foo')->table('bar'),
                (new Query)->addMin('weight')->table('items'),
                (new Query)->addMax('weight')->table('items'),
                true,
                'AND'
            ),
            new BetweenCriterion((new Query)->addSelect('name')->table('users'), 'Alice', 'Bob', true, 'OR')
        ], 'where', $query);
    }

    /**
     * Tests the whereIn, orWhereIn, whereNotIn and orWhereNotIn methods
     */
    public function testWhereIn()
    {
        $query = (new Query)
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
                (new Query)->addSelect('name')->table('users'),
                [4, new Raw('foo'), function (Query $query) {
                    $query->addAvg('price')->table('products');
                }]
            );

        $this->assertAttributeEquals([
            new InCriterion('table.name', ['Anna', 'Bill', 'Carl'], false, 'AND'),
            new InCriterion('group', new Raw('TABLES()'), false, 'OR'),
            new InCriterion(
                (new Query)->addSelect('foo')->table('bar'),
                (new Query)->addSelect('title')->table('items'),
                true,
                'AND'
            ),
            new InCriterion(
                (new Query)->addSelect('name')->table('users'),
                [4, new Raw('foo'), (new Query)->addAvg('price')->table('products')],
                true,
                'OR'
            )
        ], 'where', $query);

        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->whereIn('name', 'foo');
        });
    }

    /**
     * Tests the whereNull, orWhereNull, whereNotNull and orWhereNotNull methods
     */
    public function testWhereNull()
    {
        $query = (new Query)
            ->whereNull('table.name')
            ->orWhereNull('group')
            ->whereNotNull(function (Query $query) {
                $query->addSelect('foo')->table('bar');
            })
            ->orWhereNotNull((new Query)->addSelect('name')->table('users'));

        $this->assertAttributeEquals([
            new NullCriterion('table.name', true, 'AND'),
            new NullCriterion('group', true, 'OR'),
            new NullCriterion((new Query)->addSelect('foo')->table('bar'), false, 'AND'),
            new NullCriterion((new Query)->addSelect('name')->table('users'), false, 'OR')
        ], 'where', $query);
    }

    /**
     * Tests the whereColumn and orWhereColumn methods
     */
    public function testWhereColumn()
    {
        // Ordinary
        $query = (new Query)->whereColumn('table1.foo', '>', 'table2.bar');
        $this->assertAttributeEquals([new ColumnsCriterion('table1.foo', '>', 'table2.bar', 'AND')], 'where', $query);

        // Or where
        $query = (new Query)->orWhereColumn('foo', '<', 'bar');
        $this->assertAttributeEquals([new ColumnsCriterion('foo', '<', 'bar', 'OR')], 'where', $query);

        // Omit the rule
        $query = (new Query)->whereColumn('table.foo', 'bar');
        $this->assertAttributeEquals([new ColumnsCriterion('table.foo', '=', 'bar', 'AND')], 'where', $query);

        // Grouped criteria
        $query = (new Query)->whereColumn([
            ['table1.column1', 'table2.column1'],
            ['table1.column2', '!=', 'table2.column2']
        ]);
        $this->assertAttributeEquals([
            new CriteriaCriterion([
                new ColumnsCriterion('table1.column1', '=', 'table2.column1', 'AND'),
                new ColumnsCriterion('table1.column2', '!=', 'table2.column2', 'AND'),
            ], false, 'AND')
        ], 'where', $query);

        // Wrong single argument
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->whereColumn('column1');
        });

        // Wrong rule value
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->whereColumn(new Raw(''), new Raw(''), new Raw(''));
        });

        // Too many arguments
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->whereColumn('column1', '!=', 'column2', 'foo');
        });

        // Too few arguments
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->whereColumn();
        });
    }

    /**
     * Tests the whereExists, orWhereExists, whereNotExists and orWhereNotExists methods
     */
    public function testWhereExists()
    {
        $query = (new Query)
            ->table('table')
            ->whereExists(function (Query $query) {
                $query->table('other_table')->whereColumn('table.foo', 'other_table.bar');
            })
            ->orWhereExists(new Raw('TABLES()'))
            ->whereNotExists(function (Query $query) {
                $query->table('other_table')->whereColumn('table.foo', 'other_table.bar');
            })
            ->orWhereNotExists((new Query)->table('users'));

        $this->assertAttributeEquals([
            new ExistsCriterion((new Query)->table('other_table')->whereColumn('table.foo', 'other_table.bar'), false, 'AND'),
            new ExistsCriterion(new Raw('TABLES()'), false, 'OR'),
            new ExistsCriterion((new Query)->table('other_table')->whereColumn('table.foo', 'other_table.bar'), true, 'AND'),
            new ExistsCriterion((new Query)->table('users'), true, 'OR')
        ], 'where', $query);

        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->whereExists('foo bar');
        });
    }

    public function testWhereWithCustomClosureResolver()
    {
        $closureResolver = new class implements ClosureResolverInterface {
            public function resolveSubQueryClosure(\Closure $callback): Query
            {
                return new Query;
            }
            public function resolveCriteriaGroupClosure(\Closure $callback): Query
            {
                $callback('foo');
                return (new Query)->where('column2', 'value2');
            }
        };

        $query = new Query;
        $query->setClosureResolver($closureResolver);
        $query->where(function ($arg) {
            $this->assertEquals('foo', $arg);
        });
        $this->assertAttributeEquals([
            new CriteriaCriterion([
                new ValueCriterion('column2', '=', 'value2', 'AND')
            ], false, 'AND')
        ], 'where', $query);

        $query = new Query;
        $query->setClosureResolver($closureResolver);
        $query->where([
            ['column1', 'value1'],
            [function ($arg) {
                $this->assertEquals('foo', $arg);
            }]
        ]);
        $this->assertAttributeEquals([
            new CriteriaCriterion([
                new ValueCriterion('column1', '=', 'value1', 'AND'),
                new CriteriaCriterion([
                    new ValueCriterion('column2', '=', 'value2', 'AND')
                ], false, 'AND')
            ], false, 'AND')
        ], 'where', $query);
    }
}
