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
     * Tests the `addInsert` method
     */
    public function testAddInsert()
    {
        $query = (new Query('pr_'))
            ->addInsert([ 'foo' => 1, 'bar' => 'Bill'])
            ->addInsert([
                ['foo' => new Raw('NOW()'), 'bar' => null],
                ['foo' => -123, 'other' => function (Query $query) {
                    $query->addMax('price')->from('prices');
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
            (new Query())->addInsert([
                ['col1' => 1, 'col2' => 2],
                'row2'
            ]);
        });

        // Columns names must be strings
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->addInsert([
                ['value1', 'value2'],
                ['value1', 'value2']
            ]);
        });

        // Values must be scalar, null or subqueries
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->addInsert(['value1', new \stdClass()]);
        });

        // insert must reset insertFromSelect
        $query = (new Query())
            ->setInsertFromSelect(function (Query $query) {
                $query->from('users');
            })
            ->addInsert([ 'foo' => 'bar']);
        $this->assertInternalType('array', $query->insert);
        $this->assertCount(1, $query->insert);
    }

    /**
     * Tests the `setInsertFromSelect` method
     */
    public function testSetInsertFromSelect()
    {
        $query = (new Query('pr_'))->setInsertFromSelect(['name', 'address'], function (Query $query) {
            return $query->addSelect(['username', 'home'])->from('users')->where('status', 5);
        });
        $this->assertInstanceOf(InsertFromSelect::class, $query->insert);
        $this->assertEquals(['name', 'address'], $query->insert->columns);
        $this->assertInstanceOf(Query::class, $query->insert->selectQuery);
        $this->assertEquals('pr_users', $query->insert->selectQuery->table);

        // Omit the columns list
        $query = (new Query('pr_'))->setInsertFromSelect((new Query('pr2_'))->from('users')->where('status', 5));
        $this->assertInstanceOf(InsertFromSelect::class, $query->insert);
        $this->assertNull($query->insert->columns);
        $this->assertInstanceOf(Query::class, $query->insert->selectQuery);
        $this->assertEquals('pr2_users', $query->insert->selectQuery->table);

        // Wrong columns argument
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->setInsertFromSelect('name', function (Query $query) {
                $query->addSelect('name')->from('users');
            });
        });
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->setInsertFromSelect([1, 3], function (Query $query) {
                $query->from('users');
            });
        });

        // insertFromSelect insert must reset
        $query = (new Query())
            ->addInsert([ 'foo' => 'bar'])
            ->setInsertFromSelect(function (Query $query) {
                $query->from('users');
            });
        $this->assertInstanceOf(InsertFromSelect::class, $query->insert);
    }
}
