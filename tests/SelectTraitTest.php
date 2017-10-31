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
        $query = (new Query('demo_'))->from('table', 't');
        $this->assertAttributes(['table' => 'demo_table', 'tableAlias' => 't'], $query);
    }

    /**
     * Tests the `select` method
     */
    public function testSelect()
    {
        // No select
        $query = (new Query('pref_'));
        $this->assertEquals([], $query->select);

        // One column
        $query = (new Query('pref_'))->select('name', 'n');
        $this->assertEquals(['n' => 'name'], $query->select);

        // Many columns with different cases
        $query = (new Query('pref_'))->select([
            'value',
            't' => 'table.title',
            function (Query $query) {
                $query->select('foo')->table('bar');
            },
            (new Query('pref2_'))->select('foo')->table('bar'),
            'price' => new Raw('AVG(price) + ?', [14])
        ]);
        $this->assertCount(5, $query->select);
        $this->assertEquals('value', $query->select[0]);
        $this->assertEquals('pref_table.title', $query->select['t']);
        $this->assertInstanceOf(Query::class, $query->select[1]);
        $this->assertAttributes(['table' => 'pref_bar', 'select' => ['foo']], $query->select[1]);
        $this->assertInstanceOf(Query::class, $query->select[2]);
        $this->assertAttributes(['table' => 'pref2_bar', 'select' => ['foo']], $query->select[2]);
        $this->assertInstanceOf(StatementInterface::class, $query->select['price']);
        $this->assertEquals('AVG(price) + ?', $query->select['price']->getSQL());
        $this->assertEquals([14], $query->select['price']->getBindings());

        // Multiple select calls
        $query = (new Query('pref_'))->select('id')->select('name');
        $this->assertEquals(['id', 'name'], $query->select);

        // Wrong argument
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->select([
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
        $query = (new Query('test_'))
            ->count()
            ->avg('table.price', 'price')
            ->sum(new Raw('price * ?', [1.6]))
            ->min(function (Query $query) {
                $query->table('items');
            })
            ->max((new Query('foo_'))->table('bar'));

        $this->assertCount(5, $query->select);
        foreach ($query->select as $column) {
            $this->assertInstanceOf(Aggregate::class, $column);
        }

        $this->assertAttributes(['function' => 'COUNT', 'column' => '*'], $query->select[0]);
        $this->assertAttributes(['function' => 'AVG', 'column' => 'test_table.price'], $query->select['price']);
        $this->assertEquals('SUM', $query->select[1]->function);
        $this->assertInstanceOf(StatementInterface::class, $query->select[1]->column);
        $this->assertEquals('price * ?', $query->select[1]->column->getSQL());
        $this->assertEquals([1.6], $query->select[1]->column->getBindings());
        $this->assertEquals('MIN', $query->select[2]->function);
        $this->assertInstanceOf(Query::class, $query->select[2]->column);
        $this->assertEquals('test_items', $query->select[2]->column->table);
        $this->assertEquals('MAX', $query->select[3]->function);
        $this->assertInstanceOf(Query::class, $query->select[3]->column);
        $this->assertEquals('foo_bar', $query->select[3]->column->table);

        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->avg(['foo', 'bar']);
        });
    }
}
