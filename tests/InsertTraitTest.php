<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\InsertFromSelect;
use Finesse\QueryScribe\Raw;

/**
 * Tests the InsertTrait trait
 *
 * @author Surgie
 */
class InsertTraitTest extends TestCase
{
    /**
     * Tests the `insert` method
     */
    public function testInsert()
    {
        $query = (new Query('pr_'))
            ->insert(['foo' => 1, 'bar' => 'Bill'])
            ->insert([
                ['foo' => new Raw('NOW()'), 'bar' => null],
                ['foo' => -123, 'other' => function (Query $query) {
                    $query->max('price')->from('prices');
                }]
            ]);

        $this->assertCount(3, $query->insert);
        $this->assertEquals(['foo' => 1, 'bar' => 'Bill'], $query->insert[0]);
        $this->assertCount(2, $query->insert[1]);
        $this->assertStatement('NOW()', [], $query->insert[1]['foo']);
        $this->assertNull($query->insert[1]['bar']);
        $this->assertCount(2, $query->insert[2]);
        $this->assertEquals(-123, $query->insert[2]['foo']);
        $this->assertInstanceOf(Query::class, $query->insert[2]['other']);
        $this->assertEquals('pr_prices', $query->insert[2]['other']->table);

        // Rows must me arrays
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->insert([
                ['col1' => 1, 'col2' => 2],
                'row2'
            ]);
        });

        // Columns names must be strings
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->insert([
                ['value1', 'value2'],
                ['value1', 'value2']
            ]);
        });

        // Values must be scalar, null or subqueries
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->insert(['value1', new \stdClass()]);
        });

        // insert must reset insertFromSelect
        $query = (new Query())
            ->insertFromSelect(function (Query $query) {
                $query->from('users');
            })
            ->insert(['foo' => 'bar']);
        $this->assertInternalType('array', $query->insert);
        $this->assertCount(1, $query->insert);
    }

    /**
     * Tests the `insertFromSelect` method
     */
    public function testInsertFromSelect()
    {
        $query = (new Query('pr_'))->insertFromSelect(['name', 'address'], function (Query $query) {
            return $query->select(['username', 'home'])->from('users')->where('status', 5);
        });
        $this->assertInstanceOf(InsertFromSelect::class, $query->insert);
        $this->assertEquals(['name', 'address'], $query->insert->columns);
        $this->assertInstanceOf(Query::class, $query->insert->selectQuery);
        $this->assertEquals('pr_users', $query->insert->selectQuery->table);

        // Omit the columns list
        $query = (new Query('pr_'))->insertFromSelect((new Query('pr2_'))->from('users')->where('status', 5));
        $this->assertInstanceOf(InsertFromSelect::class, $query->insert);
        $this->assertNull($query->insert->columns);
        $this->assertInstanceOf(Query::class, $query->insert->selectQuery);
        $this->assertEquals('pr2_users', $query->insert->selectQuery->table);

        // Wrong columns argument
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->insertFromSelect('name', function (Query $query) {
                $query->select('name')->from('users');
            });
        });
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->insertFromSelect([1, 3], function (Query $query) {
                $query->from('users');
            });
        });

        // insertFromSelect insert must reset
        $query = (new Query())
            ->insert(['foo' => 'bar'])
            ->insertFromSelect(function (Query $query) {
                $query->from('users');
            });
        $this->assertInstanceOf(InsertFromSelect::class, $query->insert);
    }
}
