<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Aggregate;
use Finesse\QueryScribe\Raw;
use Finesse\QueryScribe\StatementInterface;

/**
 * Tests the SelectTrait trait
 *
 * @author Surgie
 */
class SelectTraitTest extends TestCase
{
    /**
     * Tests the `from` method
     */
    public function testFrom()
    {
        $query = (new Query)->from('table', 't');
        $this->assertAttributes(['table' => 'table', 'tableAlias' => 't'], $query);
    }

    /**
     * Tests the `addSelect` method
     */
    public function testAddSelect()
    {
        // No select
        $query = (new Query);
        $this->assertEquals([], $query->select);

        // One column
        $query = (new Query)->addSelect('name', 'n');
        $this->assertEquals(['n' => 'name'], $query->select);

        // Many columns with different cases
        $query = (new Query)->addSelect([
            'value',
            't' => 'table.title',
            function (Query $query) {
                $query->addSelect('foo')->table('bar');
            },
            (new Query)->addSelect('foo')->table('bar'),
            'price' => new Raw('AVG(price) + ?', [14])
        ]);
        $this->assertAttributeEquals([
            'value',
            't' => 'table.title',
            (new Query)->addSelect('foo')->table('bar'),
            (new Query)->addSelect('foo')->table('bar'),
            'price' => new Raw('AVG(price) + ?', [14])
        ], 'select', $query);

        // Multiple select calls
        $query = (new Query)->addSelect('id')->addSelect('name');
        $this->assertAttributeEquals(['id', 'name'], 'select', $query);

        // Wrong argument
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->addSelect([
                'value',
                ['column', 'alias']
            ]);
        });
    }

    /**
     * Tests the aggregate methods
     */
    public function testAggregates()
    {
        $query = (new Query)
            ->addCount()
            ->addAvg('table.price', 'price')
            ->addSum(new Raw('price * ?', [1.6]))
            ->addMin(function (Query $query) {
                $query->table('items');
            })
            ->addMax((new Query)->table('bar'));

        $this->assertAttributeEquals([
            new Aggregate('COUNT', '*'),
            'price' => new Aggregate('AVG', 'table.price'),
            new Aggregate('SUM', new Raw('price * ?', [1.6])),
            new Aggregate('MIN', (new Query)->table('items')),
            new Aggregate('MAX', (new Query)->table('bar'))
        ], 'select', $query);

        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->addAvg(['foo', 'bar']);
        });
    }
}
