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
        $query = (new Query)
            ->addInsert(['foo' => 1, 'bar' => 'Bill'])
            ->addInsert([
                ['foo' => new Raw('NOW()'), 'bar' => null],
                ['foo' => -123, 'other' => function (Query $query) {
                    $query->addMax('price')->from('prices');
                }]
            ]);

        $this->assertAttributeEquals([
            ['foo' => 1, 'bar' => 'Bill'],
            ['foo' => new Raw('NOW()'), 'bar' => null],
            ['foo' => -123, 'other' => (new Query)->addMax('price')->from('prices')]
        ], 'insert', $query);

        // Rows must be arrays
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->addInsert([
                ['col1' => 1, 'col2' => 2],
                'row2'
            ]);
        });

        // Columns names must be strings
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->addInsert([
                ['value1', 'value2'],
                ['value1', 'value2']
            ]);
        });

        // Values must be scalar, null or subqueries
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->addInsert(['value1', new \stdClass()]);
        });

        // insert must not reset insertFromSelect
        $query = (new Query)
            ->addInsertFromSelect(function (Query $query) {
                $query->from('users');
            })
            ->addInsert([ 'foo' => 'bar']);
        $this->assertAttributeCount(2, 'insert', $query);

        // Add zero rows
        $query = (new Query)->addInsert([]);
        $this->assertAttributeCount(0, 'insert', $query);
    }

    /**
     * Tests the `addInsertFromSelect` method
     */
    public function testAddInsertFromSelect()
    {
        $query = (new Query)
            ->addInsertFromSelect(['name', 'address'], function (Query $query) {
                return $query->addSelect(['username', 'home'])->from('users')->where('status', 5);
            })
            ->addInsertFromSelect(function (Query $query) {
                return $query->addSelect(['author', 'contact'])->from('posts')->where('type', 3);
            });
        $this->assertAttributeEquals([
            new InsertFromSelect(['name', 'address'], (new Query)->addSelect(['username', 'home'])->from('users')->where('status', 5)),
            new InsertFromSelect(null, (new Query)->addSelect(['author', 'contact'])->from('posts')->where('type', 3))
        ], 'insert', $query);

        // Wrong columns argument
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->addInsertFromSelect('name', function (Query $query) {
                $query->addSelect('name')->from('users');
            });
        });
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->addInsertFromSelect([1, 3], function (Query $query) {
                $query->from('users');
            });
        });

        // insertFromSelect insert must not reset value inserts
        $query = (new Query)
            ->addInsert(['foo' => 'bar'])
            ->addInsertFromSelect(function (Query $query) {
                $query->from('users');
            });
        $this->assertAttributeCount(2, 'insert', $query);
    }
}
